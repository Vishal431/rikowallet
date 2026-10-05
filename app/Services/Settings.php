<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class Settings
{
    public function get(string $key, mixed $default = null): mixed
    {
        $v = DB::table('settings')->where('key', $key)->value('value');

        return $v === null ? $default : json_decode($v, true);
    }

    public function put(string $key, mixed $value): void
    {
        DB::table('settings')->updateOrInsert(['key' => $key], ['value' => json_encode($value, JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now()]);
    }

    public function policy(): array
    {
        return $this->get('policy', [
            'earn_global' => true, 'earn_web' => true, 'earn_app' => true, 'contest' => false,
            'kyc_actions' => ['withdraw', 'issue_card', 'load_card', 'earn', 'claim', 'redeem'],
            'referral_kyc' => true, 'newcomer_kyc' => true, 'referrer_kyc' => true,
            'referral_mode' => 'C', 'referral_percent' => '10', 'newcomer_bonus' => '50', 'referrer_bonus' => '50',
            'rc_usd_rate' => '0.01', 'swap_fee_percent' => '0.5', 'swap_max_usd' => '500',
        ]);
    }
}
