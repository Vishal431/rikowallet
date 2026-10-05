<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Ledger;
use App\Services\ManualWithdrawals;
use App\Services\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ManualWithdrawalsTest extends TestCase
{
    use RefreshDatabase;

    public function test_logo_upload_is_raster_only_and_publicly_cacheable(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $data = ['name' => 'Logo method', 'category' => 'Wallet', 'payout_currency' => 'INR', 'countries' => ['IN'], 'minimum' => '100', 'maximum' => '1000', 'fee_mode' => 'flat', 'fee_value' => '0', 'fee_tax_percent' => '0', 'instructions' => 'Bank details', 'enabled' => '1'];
        $this->actingAs($this->admin)->post('/admin/actions/manual-method', $data + ['image' => \Illuminate\Http\UploadedFile::fake()->image('logo.png', 512, 512)])->assertRedirect();
        $file = DB::table('payment_methods')->where('name', 'Logo method')->value('image_file');
        $this->assertNotNull($file);
        $this->get('/method-image/'.$file)->assertOk()->assertHeader('Cache-Control', 'immutable, max-age=31536000, public');
        $this->postJson('/admin/actions/manual-method', $data + ['image' => \Illuminate\Http\UploadedFile::fake()->create('script.svg', 1, 'image/svg+xml')])->assertUnprocessable();
    }

    public function test_confirmed_failed_processing_returns_hold_without_leaking_destination(): void
    {
        $s = app(ManualWithdrawals::class);
        $q = $s->quote($this->user, $this->method, '1000', 'web');
        $id = (string) Str::uuid();
        $s->request($this->user, $q['quote_token'], 'private account 123', $id, 'web');
        $snapshot = app(Wallet::class)->snapshot($this->user, 'web');
        $this->assertArrayNotHasKey('destination_encrypted', $snapshot['records']->first()->data);
        $s->review($this->admin, $id, 'processing', 'Assigned');
        $s->review($this->admin, $id, 'failed', 'Bank confirmed transfer failed');
        $this->assertEquals(2000, DB::table('accounts')->where('owner', 'user:'.$this->user->id)->value('balance'));
    }

    private User $user;

    private User $admin;

    private int $method;

    protected function setUp(): void
    {
        parent::setUp();
        config(['riko.rewards_only' => true]);
        $this->user = User::factory()->create(['country' => 'IN', 'kyc_status' => 'approved']);
        $this->admin = User::factory()->create(['role' => 'admin', 'country' => 'IN']);
        DB::table('payout_currencies')->insert(['code' => 'INR', 'precision' => 2, 'per_rc' => '0.5']);
        $this->method = DB::table('payment_methods')->insertGetId(['name' => 'Bank payout', 'direction' => 'withdraw', 'countries' => '["IN"]', 'assets' => '["RC"]', 'enabled' => true, 'manual' => true, 'category' => 'Wallet', 'payout_currency' => 'INR', 'minimum' => '100', 'maximum' => '10000', 'fee_mode' => 'capped', 'fee_value' => '10', 'fee_cap' => '20', 'instructions' => 'Bank account and IFSC']);
        $l = app(Ledger::class);
        $l->post('fund', 'sandbox_funding', $this->user->id, 'RC', '2000', [$l->account('sandbox:source', 'RC') => '-2000', $l->account('user:'.$this->user->id, 'RC', $this->user->id) => '2000']);
    }

    public function test_rate_cap_hold_and_rejection_refund_are_exact_and_replay_safe(): void
    {
        $s = app(ManualWithdrawals::class);
        $q = $s->quote($this->user, $this->method, '1000', 'web');
        $this->assertSame('500.00', $q['gross']);
        $this->assertSame('20.00', $q['total_fee']);
        $this->assertSame('480.00', $q['net']);
        $id = (string) Str::uuid();
        $s->request($this->user, $q['quote_token'], 'Account 123 IFSC TEST', $id, 'web');
        $s->request($this->user, $q['quote_token'], 'Account 123 IFSC TEST', $id, 'web');
        $this->assertEquals(1000, DB::table('accounts')->where('owner', 'user:'.$this->user->id)->value('balance'));
        $this->assertDatabaseCount('records', 1);
        $s->review($this->admin, $id, 'rejected', 'Invalid beneficiary');
        $this->assertEquals(2000, DB::table('accounts')->where('owner', 'user:'.$this->user->id)->value('balance'));
        $this->actingAs($this->admin)->postJson('/admin/actions/manual-review', ['record_id' => $id, 'decision' => 'rejected', 'reference' => 'Repeated'])->assertConflict();
        $this->assertEquals(0, DB::table('entries')->sum('amount'));
    }

    public function test_paid_requires_claim_and_cannot_refund_after_payment(): void
    {
        $s = app(ManualWithdrawals::class);
        $q = $s->quote($this->user, $this->method, '1000', 'web');
        $id = (string) Str::uuid();
        $s->request($this->user, $q['quote_token'], 'Account 123', $id, 'web');
        $this->actingAs($this->admin)->postJson('/admin/actions/manual-review', ['record_id' => $id, 'decision' => 'paid', 'reference' => 'BANK-123'])->assertConflict();
        $s->review($this->admin, $id, 'processing', 'Assigned');
        $other = User::factory()->create(['role' => 'admin']);
        $this->actingAs($other)->postJson('/admin/actions/manual-review', ['record_id' => $id, 'decision' => 'paid', 'reference' => 'BANK-123'])->assertConflict();
        $s->review($this->admin, $id, 'paid', 'BANK-123');
        $this->assertDatabaseHas('records', ['id' => $id, 'status' => 'paid']);
        $this->actingAs($this->admin)->postJson('/admin/actions/manual-review', ['record_id' => $id, 'decision' => 'rejected', 'reference' => 'No refund'])->assertConflict();
    }

    public function test_flat_percent_rates_and_changed_quote_protection(): void
    {
        $s = app(ManualWithdrawals::class);
        DB::table('payment_methods')->where('id', $this->method)->update(['fee_mode' => 'flat', 'fee_value' => '5', 'fee_tax_percent' => '18']);
        $q = $s->quote($this->user, $this->method, '1000', 'web');
        $this->assertSame('494.10', $q['net']);
        DB::table('payment_methods')->where('id', $this->method)->update(['fee_mode' => 'percent', 'fee_value' => '2']);
        $this->assertSame('488.20', $s->quote($this->user, $this->method, '1000', 'web')['net']);
        $this->expectException(ValidationException::class);
        $s->request($this->user, $q['quote_token'], 'Account 123', (string) Str::uuid(), 'web');
    }

    public function test_rewards_only_catalog_pages_and_security_boundaries(): void
    {
        $snapshot = app(Wallet::class)->snapshot($this->user, 'web');
        $this->assertSame(['RC'], $snapshot['balances']->pluck('asset')->all());
        $this->assertCount(0, $snapshot['deposit_methods']);
        $this->actingAs($this->user)->get('/app/withdraw')->assertOk()->assertSee('Withdraw RikoCoin');
        $this->postJson('/actions/swap-quote', ['from' => 'USD', 'to' => 'EUR', 'amount' => '1'])->assertUnprocessable();
        $this->postJson('/actions/fee-quote', ['direction' => 'withdraw', 'method_id' => $this->method, 'amount' => '99', 'asset' => 'RC'])->assertUnprocessable();
        $this->actingAs($this->admin)->get('/admin/withdrawals')->assertOk();
        $this->user->country = 'BD';
        $this->user->save();
        $this->assertCount(0, app(Wallet::class)->methods($this->user, 'withdraw'));
    }
}
