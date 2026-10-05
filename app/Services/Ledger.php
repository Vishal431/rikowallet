<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class Ledger
{
    public function amount(mixed $value, string $asset): string
    {
        if (! in_array($asset, config('riko.enabled_assets'), true)) {
            throw ValidationException::withMessages(['asset' => 'This currency is not available yet.']);
        }
        $scale = config('riko.assets')[$asset] ?? null;
        if ($scale === null || ! is_string($value) || ! preg_match('/^(0|[1-9][0-9]{0,11})(\\.[0-9]{1,8})?$/D', $value) || bccomp($value, '0', 8) <= 0 || (bccomp($value, bcadd($value, '0', $scale), 8) !== 0)) {
            throw ValidationException::withMessages(['amount' => 'Enter a positive amount using the currency precision.']);
        }

        return bcadd($value, '0', 8);
    }

    public function account(string $owner, string $asset, ?int $user = null): int
    {
        DB::table('accounts')->insertOrIgnore(['owner' => $owner, 'asset' => $asset, 'user_id' => $user, 'balance' => '0', 'allow_negative' => $owner === 'sandbox:source', 'created_at' => now(), 'updated_at' => now()]);

        return DB::table('accounts')->where(['owner' => $owner, 'asset' => $asset])->value('id');
    }

    /** Entries are signed balance deltas; every asset must sum to exactly zero. */
    public function post(string $key, string $type, ?int $user, string $asset, string $amount, array $lines, array $meta = []): object
    {
        ksort($lines, SORT_NUMERIC);
        $fingerprint = hash('sha256', json_encode([$type, $user, $asset, $amount, $lines, $meta], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($key, $type, $user, $asset, $amount, $lines, $meta, $fingerprint) {
            $accounts = DB::table('accounts')->whereIn('id', array_keys($lines))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            if ($old = DB::table('operations')->where('key', $key)->first()) {
                if (! hash_equals($old->fingerprint, $fingerprint)) {
                    throw ValidationException::withMessages(['request' => 'This request key was already used for a different operation.']);
                }

                return $old;
            }
            if (count($accounts) !== count($lines)) {
                throw new \LogicException('Unknown ledger account.');
            }
            $sums = [];
            foreach ($lines as $id => $delta) {
                $a = $accounts[$id];
                $sums[$a->asset] = bcadd($sums[$a->asset] ?? '0', $delta, 8);
                if (! $a->allow_negative && bccomp(bcadd($a->balance, $delta, 8), '0', 8) < 0) {
                    throw ValidationException::withMessages(['amount' => 'Insufficient available balance or funded platform inventory.']);
                }
                if ($a->allow_negative && (! config('riko.sandbox') || ! app()->environment(['local', 'testing']))) {
                    throw new \LogicException('Sandbox source is disabled.');
                }
            }
            foreach ($sums as $sum) {
                if (bccomp($sum, '0', 8) !== 0) {
                    throw new \LogicException('Unbalanced journal.');
                }
            }
            $id = (string) Str::uuid();
            DB::table('operations')->insert(['id' => $id, 'key' => $key, 'fingerprint' => $fingerprint, 'user_id' => $user, 'type' => $type, 'status' => 'completed', 'asset' => $asset, 'amount' => $amount, 'meta' => json_encode($meta), 'created_at' => now(), 'updated_at' => now()]);
            foreach ($lines as $account => $delta) {
                DB::table('entries')->insert(['operation_id' => $id, 'account_id' => $account, 'amount' => $delta, 'created_at' => now()]);
                DB::table('accounts')->where('id', $account)->update(['balance' => bcadd($accounts[$account]->balance, $delta, 8), 'updated_at' => now()]);
            }

            return DB::table('operations')->where('id', $id)->first();
        }, 5);
    }

    public function transfer(int $from, int $to, string $asset, string $amount, string $key, array $meta = []): object
    {
        $amount = $this->amount($amount, $asset);
        if ($from === $to) {
            throw ValidationException::withMessages(['recipient' => 'You cannot pay yourself.']);
        }

        return $this->post($key, 'transfer', $from, $asset, $amount, [
            $this->account('user:'.$from, $asset, $from) => bcsub('0', $amount, 8),
            $this->account('user:'.$to, $asset, $to) => $amount,
        ], ['recipient_id' => $to] + $meta);
    }
}
