<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PaymentFeesTest extends TestCase
{
    use RefreshDatabase;

    public function test_tax_is_on_fees_and_quote_never_moves_money(): void
    {
        $u = User::factory()->create(['country' => 'IN', 'kyc_status' => 'approved']);
        $id = DB::table('payment_methods')->insertGetId(['name' => 'Example', 'direction' => 'deposit', 'countries' => '["IN"]', 'assets' => '["INR"]', 'enabled' => true, 'processing_percent' => '2.99', 'fee_tax_percent' => '18']);
        $this->actingAs($u)->postJson('/actions/fee-quote', ['direction' => 'deposit', 'method_id' => $id, 'asset' => 'INR', 'amount' => '1000', 'fee' => '0', 'net' => '1000'])
            ->assertOk()->assertJsonPath('result.processing_fee', '29.90')->assertJsonPath('result.tax', '5.38')->assertJsonPath('result.net', '964.72');
        $this->assertDatabaseCount('operations', 0);
    }

    public function test_withdrawal_uses_its_own_schedule_and_blocks_unsupported_country(): void
    {
        $u = User::factory()->create(['country' => 'IN', 'kyc_status' => 'approved']);
        $id = DB::table('payment_methods')->insertGetId(['name' => 'Example', 'direction' => 'withdraw', 'countries' => '["IN"]', 'assets' => '["INR"]', 'enabled' => true, 'processing_percent' => '1', 'fee_tax_percent' => '18', 'fee' => '5']);
        $data = ['direction' => 'withdraw', 'method_id' => $id, 'asset' => 'INR', 'amount' => '1000'];
        $this->actingAs($u)->postJson('/actions/fee-quote', $data)->assertOk()->assertJsonPath('result.net', '982.30')->assertJsonPath('result.tax', '2.70');
        $this->postJson('/actions/fee-quote', array_replace($data, ['amount' => '1']))->assertUnprocessable();
        $u->country = 'BD';
        $u->save();
        $this->postJson('/actions/fee-quote', $data)->assertUnprocessable();
    }

    public function test_unknown_schedule_does_not_claim_zero_fees(): void
    {
        $u = User::factory()->create(['country' => 'IN', 'kyc_status' => 'approved']);
        $id = DB::table('payment_methods')->insertGetId(['name' => 'Pending', 'direction' => 'deposit', 'countries' => '["IN"]', 'assets' => '["INR"]', 'enabled' => true]);
        $this->actingAs($u)->postJson('/actions/fee-quote', ['direction' => 'deposit', 'method_id' => $id, 'asset' => 'INR', 'amount' => '1000'])->assertUnprocessable();
    }
}
