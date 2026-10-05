<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Validation\ValidationException;

class Access
{
    public function __construct(private Settings $settings) {}

    public function verified(User $u): bool
    {
        return $u->kyc_status === 'approved' && (! $u->kyc_expires_at || now()->lt($u->kyc_expires_at));
    }

    public function blocked(User $u, string $scope): bool
    {
        $r = $u->restrictions ?? [];
        foreach (['account', $scope] as $s) {
            if (isset($r[$s]) && ($r[$s] === 'permanent' || now()->lt($r[$s]))) {
                return true;
            }
        }

        return false;
    }

    public function check(User $u, string $action, string $channel = 'web'): void
    {
        if (config('riko.rewards_only') && in_array($action, ['deposit', 'send', 'receive', 'swap', 'redeem', 'business'])) {
            $this->deny('This feature is coming later. Earn RikoCoin and request a manual withdrawal for now.');
        }
        if ($this->blocked($u, 'login')) {
            $this->deny('Account access is restricted. Please contact support.');
        }
        $earning = in_array($action, ['earn', 'claim', 'redeem']);
        if ($this->blocked($u, $earning ? 'earning' : ($action === 'support' ? 'support' : 'financial'))) {
            $this->deny('This feature is restricted on your account.');
        }
        $p = $this->settings->policy();
        if ($earning && (! $p['earn_global'] || ! ($p['earn_'.$channel] ?? false))) {
            $this->deny('Earning is currently unavailable on this platform.');
        }
        if ($earning && $channel === 'app' && ! config('riko.sandbox') && ! config('riko.app_integrity_ready')) {
            $this->deny('App earning requires production integrity verification.');
        }
        if ($this->requiresKyc($u, $action) && ! $this->verified($u)) {
            $this->deny('Complete identity verification to unlock this feature.');
        }
    }

    public function requiresKyc(User $u, string $action): bool
    {
        if (in_array($action, config('riko.provider_required')) && ! ($action === 'withdraw' && config('riko.rewards_only'))) {
            return true;
        }
        $p = $this->settings->policy();
        if (($p['kyc_scope'] ?? 'all') === 'new' && isset($p['kyc_effective_at']) && $u->created_at->lt($p['kyc_effective_at'])) {
            return false;
        }

        return in_array($action, $p['kyc_actions']);
    }

    public function deny(string $message): never
    {
        throw ValidationException::withMessages(['action' => $message]);
    }
}
