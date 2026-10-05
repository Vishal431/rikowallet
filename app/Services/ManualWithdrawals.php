<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class ManualWithdrawals
{
    public function __construct(private Ledger $ledger, private Access $access) {}

    public function quote(User $u, int $methodId, string $amount, string $channel): array
    {
        $this->access->check($u, 'withdraw', $channel);
        $amount = $this->ledger->amount($amount, 'RC');
        $m = DB::table('payment_methods')->where(['id' => $methodId, 'manual' => true, 'direction' => 'withdraw', 'enabled' => true])->first();
        if (! $m || ! in_array($u->country, json_decode($m->countries, true))) {
            $this->access->deny('Withdrawal method is unavailable in your country.');
        }
        if (bccomp($amount, $m->minimum, 8) < 0 || bccomp($amount, $m->maximum, 8) > 0) {
            $this->access->deny('Enter between '.$m->minimum.' and '.$m->maximum.' RikoCoin.');
        }
        $rate = DB::table('payout_currencies')->where('code', $m->payout_currency)->first();
        if (! $rate || bccomp($rate->per_rc, '0', 12) <= 0) {
            $this->access->deny('Payout currency rate is not configured.');
        }
        $scale = $rate->precision;
        $round = fn ($v) => bcadd($v, $scale ? '0.'.str_repeat('0', $scale).'5' : '0.5', $scale);
        $gross = bcadd(bcmul($amount, $rate->per_rc, 12), '0', $scale);
        $fee = $m->fee_mode === 'flat' ? $m->fee_value : bcdiv(bcmul($gross, $m->fee_value, 12), '100', 12);
        if ($m->fee_mode === 'capped' && bccomp($fee, $m->fee_cap, 12) > 0) {
            $fee = $m->fee_cap;
        }
        $fee = $round($fee);
        if ($m->fee_mode === 'capped' && bccomp($fee, $m->fee_cap, 8) > 0) {
            $fee = bcadd($m->fee_cap, '0', $scale);
        }
        $tax = $round(bcdiv(bcmul($fee, $m->fee_tax_percent, 12), '100', 12));
        $total = bcadd($fee, $tax, $scale);
        $net = bcsub($gross, $total, $scale);
        if (bccomp($net, '0', $scale) <= 0) {
            $this->access->deny('Amount is too small after fees.');
        }
        $q = ['direction' => 'withdraw', 'method_id' => $m->id, 'method_name' => $m->name, 'category' => $m->category, 'asset' => $rate->code, 'source_asset' => 'RC', 'source_amount' => bcadd($amount, '0', 2), 'rate' => (string) $rate->per_rc, 'gross' => $gross, 'processing_fee' => $fee, 'platform_fee' => '0', 'tax' => $tax, 'tax_percent' => (string) $m->fee_tax_percent, 'total_fee' => $total, 'net' => $net, 'estimate' => true, 'message' => 'Manual payout estimate. Your RikoCoin amount is held on request. Fees and conversion are locked for this request; external recipient charges are not included.'];
        $q['quote_token'] = Crypt::encryptString(json_encode(['user_id' => $u->id, 'expires' => now()->addMinutes(5)->timestamp, 'quote' => $q]));

        return $q;
    }

    public function request(User $u, string $token, string $destination, string $key, string $channel): array
    {
        $this->access->check($u, 'withdraw', $channel);
        try {
            $signed = json_decode(Crypt::decryptString($token), true, flags: JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            $this->access->deny('Invalid quote. Calculate fees again.');
        }
        if (($signed['user_id'] ?? null) !== $u->id) {
            $this->access->deny('This quote does not belong to you.');
        }

        return DB::transaction(function () use ($u, $signed, $destination, $key, $channel) {
            $u = User::whereKey($u->id)->lockForUpdate()->firstOrFail();
            $this->access->check($u, 'withdraw', $channel);
            $fingerprint = hash('sha256', json_encode([$signed['quote'], $destination]));
            $old = DB::table('records')->where(['id' => $key, 'user_id' => $u->id, 'kind' => 'manual_withdrawal'])->first();
            if ($old) {
                if ((json_decode($old->data, true)['fingerprint'] ?? null) !== $fingerprint) {
                    $this->access->deny('Request key already used for different withdrawal details.');
                }

                return ['id' => $old->id, 'message' => 'Withdrawal request already received.'];
            }
            if (now()->timestamp > $signed['expires']) {
                $this->access->deny('Quote expired. Calculate fees again.');
            }
            $q = $signed['quote'];
            DB::table('payment_methods')->where('id', $q['method_id'])->lockForUpdate()->first();
            DB::table('payout_currencies')->where('code', $q['asset'])->lockForUpdate()->first();
            $fresh = $this->quote($u, $q['method_id'], $q['source_amount'], $channel);
            unset($fresh['quote_token']);
            if ($fresh !== $q) {
                $this->access->deny('Fees or rates changed. Review a new quote before submitting.');
            }
            $this->ledger->post('withdraw:hold:'.$key, 'withdrawal_hold', $u->id, 'RC', $q['source_amount'], [
                $this->ledger->account('user:'.$u->id, 'RC', $u->id) => bcsub('0', $q['source_amount'], 8),
                $this->ledger->account('withdrawal:'.$key, 'RC') => $q['source_amount'],
            ]);
            DB::table('records')->insert(['id' => $key, 'user_id' => $u->id, 'kind' => 'manual_withdrawal', 'status' => 'pending', 'asset' => 'RC', 'amount' => $q['source_amount'], 'data' => json_encode(['quote' => $q, 'destination_encrypted' => Crypt::encryptString($destination), 'fingerprint' => $fingerprint]), 'created_at' => now(), 'updated_at' => now()]);

            return ['id' => $key, 'message' => 'Withdrawal requested. RikoCoin is held while your manual payout is reviewed.'];
        }, 5);
    }

    public function review(User $admin, string $id, string $decision, string $reference): void
    {
        DB::transaction(function () use ($admin, $id, $decision, $reference) {
            $r = DB::table('records')->where(['id' => $id, 'kind' => 'manual_withdrawal'])->lockForUpdate()->first();
            abort_unless($r && $admin->role === 'admin' && $r->user_id !== $admin->id, 403);
            $d = json_decode($r->data, true);
            if ($decision === 'processing') {
                abort_unless($r->status === 'pending', 409, 'Request is already assigned or settled.');
                $this->access->check(User::findOrFail($r->user_id), 'withdraw');
                $d['assigned_admin'] = $admin->id;
            } else {
                abort_unless(($decision === 'rejected' && $r->status === 'pending') || (in_array($decision, ['paid', 'failed']) && $r->status === 'processing' && ($d['assigned_admin'] ?? null) === $admin->id), 409, 'Invalid payout state or assigned administrator.');
                $owner = $decision === 'paid' ? 'company:redeemed' : 'user:'.$r->user_id;
                $this->ledger->post('withdraw:'.$decision.':'.$id, 'withdrawal_'.$decision, $r->user_id, 'RC', $r->amount, [
                    $this->ledger->account('withdrawal:'.$id, 'RC') => bcsub('0', $r->amount, 8),
                    $this->ledger->account($owner, 'RC', $decision !== 'paid' ? $r->user_id : null) => $r->amount,
                ]);
                $d['reference'] = $reference;
            }
            DB::table('records')->where('id', $id)->update(['status' => $decision, 'data' => json_encode($d), 'updated_at' => now()]);
        }, 5);
    }
}
