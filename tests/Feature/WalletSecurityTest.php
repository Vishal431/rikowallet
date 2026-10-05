<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Access;
use App\Services\Ledger;
use App\Services\Rewards;
use App\Services\Settings;
use App\Services\Wallet;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class WalletSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_fiat_launch_hides_crypto_and_rejects_crypto_api_requests(): void
    {
        $u = $this->user();
        $this->assertSame(['USD', 'INR', 'EUR', 'RC'], array_column(app(Wallet::class)->snapshot($u, 'web')['balances']->all(), 'asset'));
        DB::table('payment_methods')->insert(['name' => 'Crypto', 'direction' => 'deposit', 'countries' => '["IN"]', 'assets' => '["BTC","USDT"]', 'enabled' => true]);
        $this->assertCount(0, app(Wallet::class)->methods($u, 'deposit'));
        $this->actingAs($u)->postJson('/actions/swap-quote', ['from' => 'USD', 'to' => 'BTC', 'amount' => '10'])->assertUnprocessable();
        $this->postJson('/actions/deposit', ['method_id' => 1, 'asset' => 'BTC', 'amount' => '1'])->assertUnprocessable();
        $this->get('/app/cards')->assertSee('Coming soon')->assertDontSee('Check card eligibility');
        $this->postJson('/actions/card-apply')->assertUnprocessable()->assertJsonFragment(['Cards are coming soon. Applications are not open yet.']);
        $this->expectException(ValidationException::class);
        app(Ledger::class)->amount('1', 'USDT');
    }

    public function test_admin_country_reward_rates_are_validated_and_used_at_reservation(): void
    {
        $admin = $this->user(role: 'admin');
        $data = ['title' => 'Country rates', 'category' => 'app_surf', 'instructions' => 'Complete partner task', 'package_name' => 'com.partner.test', 'reward' => '50', 'budget' => '1000', 'countries' => ['IN'], 'devices' => ['desktop'], 'channels' => ['web'], 'rates' => ['IN' => '75.25'], 'enabled' => '1'];
        $this->actingAs($admin)->post('/admin/actions/task', $data)->assertRedirect();
        $task = DB::table('tasks')->first();
        $this->assertSame('75.25000000', json_decode($task->rates, true)['IN']);
        $this->postJson('/admin/actions/task', array_replace($data, ['rates' => ['US' => '99']]))->assertUnprocessable();
        $this->postJson('/admin/actions/task', array_replace($data, ['rates' => ['IN' => '0.001']]))->assertUnprocessable();
        $l = app(Ledger::class);
        $l->post('budget', 'sandbox_funding', null, 'RC', '1000', [$l->account('sandbox:source', 'RC') => '-1000', $l->account('company:rewards', 'RC') => '1000']);
        DB::table('tasks')->where('id', $task->id)->update(['provider_ready' => true]);
        $session = app(Rewards::class)->start($admin, $task->id, 'web', 'desktop');
        $this->assertEquals(75.25, $session['reward']);
        $this->assertEquals(924.75, DB::table('tasks')->where('id', $task->id)->value('budget'));
    }

    public function test_expired_reservations_restore_budget_once_and_reject_late_completion(): void
    {
        $u = $this->user();
        $l = app(Ledger::class);
        $l->post('budget', 'sandbox_funding', null, 'RC', '1000', [$l->account('sandbox:source', 'RC') => '-1000', $l->account('company:rewards', 'RC') => '1000']);
        $task = DB::table('tasks')->insertGetId(['title' => 'Expiry', 'category' => 'app_surf', 'instructions' => 'Test', 'package_name' => 'com.partner.test', 'countries' => '["IN"]', 'devices' => '["desktop"]', 'channels' => '["web"]', 'reward' => '100', 'budget' => '1000', 'enabled' => true, 'provider_ready' => true]);
        $r = app(Rewards::class);
        $s = $r->start($u, $task, 'web', 'desktop');
        $this->travel(25)->hours();
        $this->assertSame(1, $r->expireReservations());
        $this->assertSame(0, $r->expireReservations());
        $this->assertEquals(1000, DB::table('tasks')->where('id', $task)->value('budget'));
        $this->assertEquals(1000, DB::table('accounts')->where('owner', 'company:rewards')->value('balance'));
        $this->assertEquals(0, DB::table('accounts')->where('owner', 'task:'.$s['id'])->value('balance'));
        $this->expectException(HttpException::class);
        $r->complete($s['pairing_code'], 'com.partner.test', 'late_event_123456');
    }

    public function test_verified_pending_reward_is_not_returned_on_expiry(): void
    {
        $u = $this->user();
        $l = app(Ledger::class);
        $l->post('budget', 'sandbox_funding', null, 'RC', '1000', [$l->account('sandbox:source', 'RC') => '-1000', $l->account('company:rewards', 'RC') => '1000']);
        $task = DB::table('tasks')->insertGetId(['title' => 'Held', 'category' => 'app_surf', 'instructions' => 'Test', 'package_name' => 'com.partner.test', 'countries' => '["IN"]', 'devices' => '["desktop"]', 'channels' => '["web"]', 'reward' => '100', 'budget' => '1000', 'enabled' => true, 'provider_ready' => true]);
        $r = app(Rewards::class);
        $s = $r->start($u, $task, 'web', 'desktop');
        $u->kyc_status = 'pending';
        $u->save();
        $r->complete($s['pairing_code'], 'com.partner.test', 'pending_event_12345');
        $this->travel(25)->hours();
        $this->assertSame(0, $r->expireReservations());
        $this->assertSame('verified_pending', $r->complete($s['pairing_code'], 'com.partner.test', 'pending_event_12345')['status']);
        $this->assertDatabaseHas('task_sessions', ['id' => $s['id'], 'status' => 'verified_pending']);
        $u->kyc_status = 'approved';
        $u->save();
        $r->release($s['id']);
        $this->assertDatabaseHas('task_sessions', ['id' => $s['id'], 'status' => 'credited']);
    }

    public function test_daily_active_counts_survive_next_day_login_and_use_india_day(): void
    {
        $this->travelTo(Carbon::parse('2026-10-03 19:00:00', 'UTC'));
        $u = $this->user();
        app(Wallet::class)->snapshot($u, 'web');
        app(Wallet::class)->snapshot($u, 'web');
        $this->assertDatabaseCount('user_activity_days', 1);
        $this->assertDatabaseHas('user_activity_days', ['day' => '2026-10-04']);
        $this->travel(1)->days();
        app(Wallet::class)->snapshot($u, 'web');
        $this->artisan('riko:stats', ['--day' => '2026-10-04'])->assertSuccessful();
        $stats = json_decode(DB::table('daily_stats')->where('day', '2026-10-04')->value('data'), true);
        $this->assertSame(1, $stats['active_users']);
        $this->assertSame(1, $stats['signups']);
    }

    public function test_new_user_kyc_scope_never_bypasses_provider_minimum(): void
    {
        $u = $this->user();
        $u->created_at = now()->subMonth();
        $u->kyc_status = 'pending';
        $u->save();
        $p = app(Settings::class)->policy();
        $p['kyc_scope'] = 'new';
        $p['kyc_effective_at'] = now()->toIso8601String();
        app(Settings::class)->put('policy', $p);
        $access = app(Access::class);
        $this->assertFalse($access->requiresKyc($u, 'earn'));
        $this->assertTrue($access->requiresKyc($u, 'withdraw'));
        $new = $this->user('7391026485');
        $this->assertTrue($access->requiresKyc($new, 'earn'));
    }

    public function test_checkout_uses_server_amount_and_cannot_be_paid_twice(): void
    {
        $merchant = $this->user();
        $payer = $this->user('7391026485');
        $this->fund($payer);
        $id = app(Wallet::class)->record($merchant, 'link', ['title' => 'Design services'], 'open', 'USD', '25.00000000');
        $this->actingAs($payer)->get('/pay/'.$id)->assertOk();
        $this->postJson('/actions/pay-record', ['record_id' => $id, 'password' => 'TestPassword123!', 'amount' => '1'])->assertOk();
        $this->postJson('/actions/pay-record', ['record_id' => $id, 'password' => 'TestPassword123!'])->assertUnprocessable();
        $this->assertEquals(25, DB::table('accounts')->where(['owner' => 'user:'.$merchant->id, 'asset' => 'USD'])->value('balance'));
    }

    private function user(string $wallet = '5826149037', string $role = 'user'): User
    {
        $u = User::factory()->create(['name' => 'Test User', 'email' => $wallet.'@example.test', 'password' => Hash::make('TestPassword123!')]);
        $u->wallet_id = $wallet;
        $u->role = $role;
        $u->country = 'IN';
        $u->kyc_status = 'approved';
        $u->save();

        return $u;
    }

    private function fund(User $u, string $asset = 'USD', string $amount = '100'): void
    {
        $l = app(Ledger::class);
        $l->post('fund:'.$u->id.':'.$asset, 'sandbox_funding', $u->id, $asset, $amount, [$l->account('sandbox:source', $asset) => bcsub('0', $amount, 8), $l->account('user:'.$u->id, $asset, $u->id) => $amount]);
    }

    public function test_all_user_and_admin_screens_render(): void
    {
        $u = $this->user(role: 'admin');
        $this->actingAs($u);
        foreach (['home', 'pay', 'receive', 'requests', 'deposit', 'withdraw', 'swap', 'cards', 'earn', 'referrals', 'profile', 'support', 'activity', 'business', 'invoices', 'links', 'subscriptions', 'kyc'] as $p) {
            $this->get('/app/'.$p)->assertOk();
        }
        foreach (['overview', 'users', 'kyc', 'earning', 'methods', 'referrals', 'contest', 'liquidity', 'support', 'operations', 'cron', 'audit', 'providers'] as $p) {
            $this->get('/admin/'.$p)->assertOk();
        }
    }

    public function test_transfer_is_balanced_and_replay_safe(): void
    {
        $a = $this->user();
        $b = $this->user('7391026485');
        $this->fund($a);
        $l = app(Ledger::class);
        $first = $l->transfer($a->id, $b->id, 'USD', '40', 'test-transfer');
        $second = $l->transfer($a->id, $b->id, 'USD', '40', 'test-transfer');
        $this->assertSame($first->id, $second->id);
        $this->assertEquals(60, DB::table('accounts')->where(['owner' => 'user:'.$a->id, 'asset' => 'USD'])->value('balance'));
        $this->assertEquals(40, DB::table('accounts')->where(['owner' => 'user:'.$b->id, 'asset' => 'USD'])->value('balance'));
        $this->assertEquals(0, DB::table('entries')->where('operation_id', $first->id)->sum('amount'));
    }

    public function test_overdraft_and_reused_key_with_different_amount_are_rejected(): void
    {
        $a = $this->user();
        $b = $this->user('7391026485');
        $this->fund($a);
        $this->actingAs($a);
        $data = ['wallet_id' => $b->wallet_id, 'asset' => 'USD', 'amount' => '40', 'key' => (string) Str::uuid(), 'password' => 'TestPassword123!'];
        $this->postJson('/actions/send', $data)->assertOk();
        $this->postJson('/actions/send', array_merge($data, ['amount' => '41']))->assertUnprocessable();
        $this->postJson('/actions/send', array_merge($data, ['amount' => '999', 'key' => (string) Str::uuid()]))->assertUnprocessable();
        $this->postJson('/actions/send', array_merge($data, ['amount' => '0.001', 'key' => (string) Str::uuid()]))->assertUnprocessable();
    }

    public function test_kyc_switch_and_channel_switch_block_direct_api_action(): void
    {
        $u = $this->user();
        $u->kyc_status = 'pending';
        $u->save();
        $this->actingAs($u);
        $this->postJson('/actions/task-start', ['task_id' => 1, 'device' => 'desktop'])->assertUnprocessable()->assertJsonFragment(['Complete identity verification to unlock this feature.']);
        $p = app(Settings::class)->policy();
        $p['earn_web'] = false;
        app(Settings::class)->put('policy', $p);
        $this->postJson('/actions/redeem', ['amount' => '100', 'key' => (string) Str::uuid()])->assertUnprocessable();
        $this->get('/app/earn')->assertNotFound();
    }

    public function test_country_filter_cannot_be_spoofed(): void
    {
        $u = $this->user();
        $u->country = 'BD';
        $u->save();
        DB::table('payment_methods')->insert(['name' => 'UPI', 'direction' => 'deposit', 'countries' => '["IN"]', 'assets' => '["INR"]', 'enabled' => true]);
        $this->assertCount(0, app(Wallet::class)->methods($u, 'deposit'));
        $this->actingAs($u)->postJson('/actions/deposit', ['method_id' => 1, 'asset' => 'INR', 'amount' => '100', 'country' => 'IN'])->assertUnprocessable();
    }

    public function test_non_admin_cannot_change_policy_and_admin_cannot_self_approve_adjustment(): void
    {
        $u = $this->user();
        $this->actingAs($u)->get('/admin')->assertForbidden();
        $admin = $this->user('9012345678', 'admin');
        $this->actingAs($admin);
        $this->post('/admin/actions/adjustment', ['user_id' => $u->id, 'asset' => 'USD', 'amount' => '5', 'reason' => 'Correction'])->assertRedirect();
        $id = DB::table('records')->where('kind', 'adjustment')->value('id');
        $this->postJson('/admin/actions/approve-adjustment', ['record_id' => $id])->assertForbidden();
    }

    public function test_unsigned_and_test_callbacks_cannot_mint_rewards(): void
    {
        config(['riko.callback_secret' => str_repeat('x', 40)]);
        $this->postJson('/api/earn/verify', ['user_code' => 'PRO123', 'is_test' => true])->assertUnauthorized();
        $this->assertDatabaseCount('operations', 0);
    }

    public function test_approved_task_commission_waits_for_referrer_kyc_and_releases_once(): void
    {
        $parent = $this->user();
        $child = $this->user('7391026485');
        $parent->kyc_status = 'pending';
        $parent->save();
        $child->referrer_id = $parent->id;
        $child->referral_policy = app(Settings::class)->policy();
        $child->save();
        $l = app(Ledger::class);
        $l->post('budget', 'sandbox_funding', null, 'RC', '1000', [$l->account('sandbox:source', 'RC') => '-1000', $l->account('company:rewards', 'RC') => '1000']);
        $task = DB::table('tasks')->insertGetId(['title' => 'Test', 'category' => 'app_surf', 'instructions' => 'Test', 'package_name' => 'com.partner.test', 'countries' => '["IN"]', 'devices' => '["desktop"]', 'channels' => '["web"]', 'reward' => '100', 'budget' => '1000', 'enabled' => true, 'provider_ready' => true]);
        $r = app(Rewards::class);
        $s = $r->start($child, $task, 'web', 'desktop');
        $r->complete($s['pairing_code'], 'com.partner.test', 'event_1234567890');
        $this->assertDatabaseHas('referral_rewards', ['status' => 'pending', 'user_id' => $parent->id]);
        $parent->kyc_status = 'approved';
        $parent->save();
        $r->releaseReferrals();
        $r->releaseReferrals();
        $r->complete($s['pairing_code'], 'com.partner.test', 'event_1234567890');
        $this->assertEquals(100, DB::table('accounts')->where(['owner' => 'user:'.$child->id, 'asset' => 'RC'])->value('balance'));
        $this->assertEquals(10, DB::table('accounts')->where(['owner' => 'user:'.$parent->id, 'asset' => 'RC'])->value('balance'));
        $this->assertSame(1, DB::table('operations')->where('type', 'referral_commission')->count());
    }

    public function test_documents_are_not_public_and_other_users_records_are_not_returned(): void
    {
        $u = $this->user();
        $other = $this->user('7391026485');
        $id = app(Wallet::class)->record($other, 'kyc', ['private_path' => 'kyc/test.pdf'], 'pending');
        $this->actingAs($u)->get('/admin-document/'.$id)->assertForbidden();
        $this->assertCount(0, app(Wallet::class)->snapshot($u, 'web')['records']);
    }
}
