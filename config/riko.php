<?php

return [
    'rewards_only' => env('RIKO_REWARDS_ONLY', true),
    'cron_secret' => env('RIKO_CRON_SECRET'),
    'sandbox' => env('RIKO_SANDBOX', false),
    'assets' => ['USD' => 2, 'INR' => 2, 'EUR' => 2, 'BTC' => 8, 'TRX' => 6, 'USDT' => 6, 'RC' => 2],
    'enabled_assets' => ['USD', 'INR', 'EUR', 'RC'],
    'payment_assets' => ['USD', 'INR', 'EUR'],
    'countries' => ['IN' => 'India', 'US' => 'United States', 'GB' => 'United Kingdom', 'BD' => 'Bangladesh', 'AE' => 'United Arab Emirates', 'DE' => 'Germany', 'SG' => 'Singapore'],
    'actions' => ['deposit', 'withdraw', 'send', 'receive', 'swap', 'earn', 'claim', 'redeem', 'issue_card', 'load_card', 'business'],
    'provider_required' => ['deposit', 'withdraw', 'issue_card', 'load_card'],
    'callback_secret' => env('RIKO_CALLBACK_SECRET'),
    'app_integrity_ready' => false,
];
