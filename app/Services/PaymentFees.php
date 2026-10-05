<?php

namespace App\Services;

use App\Models\User;

class PaymentFees
{
    public function __construct(private Wallet $wallet, private Ledger $ledger, private Access $access) {}

    public function quote(User $user, string $direction, int $methodId, string $asset, string $amount, string $channel): array
    {
        $this->access->check($user, $direction, $channel);
        $amount = $this->ledger->amount($amount, $asset);
        $method = $this->wallet->methods($user, $direction)->firstWhere('id', $methodId);
        if (! $method || ! in_array($asset, json_decode($method->assets, true))) {
            $this->access->deny('Select a method supporting this currency in your country.');
        }
        if ($method->processing_percent === null) {
            $this->access->deny('Fees are not configured for this method yet. No funds will move.');
        }
        if (bccomp($amount, $method->minimum, 8) < 0 || bccomp($amount, $method->maximum, 8) > 0) {
            $this->access->deny('Amount is outside the method limits.');
        }
        $scale = config('riko.assets')[$asset];
        $round = fn ($v) => bcadd($v, '0.'.str_repeat('0', $scale).'5', $scale);
        $processing = $round(bcdiv(bcmul($amount, $method->processing_percent, 12), '100', 12));
        $platform = $round(bcadd($method->fee, bcdiv(bcmul($amount, $method->platform_percent, 12), '100', 12), 12));
        $tax = $round(bcdiv(bcmul(bcadd($processing, $platform, 12), $method->fee_tax_percent, 12), '100', 12));
        $total = bcadd(bcadd($processing, $platform, $scale), $tax, $scale);
        $net = bcsub($amount, $total, $scale);
        if (bccomp($net, '0', $scale) <= 0) {
            $this->access->deny('Amount must be greater than the total fees.');
        }

        return ['direction' => $direction, 'method_id' => $methodId, 'asset' => $asset, 'gross' => bcadd($amount, '0', $scale), 'processing_fee' => $processing, 'platform_fee' => $platform, 'tax' => $tax, 'tax_percent' => (string) $method->fee_tax_percent, 'total_fee' => $total, 'net' => $net, 'estimate' => true, 'message' => 'Estimated using the configured schedule. Tax applies to fees, not the deposit or withdrawal amount. Provider approval and final pricing are pending. No currency conversion is included.'];
    }
}
