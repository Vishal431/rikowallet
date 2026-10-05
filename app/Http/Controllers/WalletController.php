<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Access;
use App\Services\Ledger;
use App\Services\ManualWithdrawals;
use App\Services\PaymentFees;
use App\Services\Rewards;
use App\Services\Settings;
use App\Services\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class WalletController extends Controller
{
    public function __construct(private Wallet $wallet, private Ledger $ledger, private Access $access, private Rewards $rewards, private Settings $settings) {}

    public function snapshot(Request $r)
    {
        return $this->wallet->snapshot($r->user(), $r->attributes->get('channel', 'web'));
    }

    public function action(Request $r, string $action)
    {
        $u = $r->user();
        $channel = $r->attributes->get('channel', 'web');
        $result = [];
        if ($this->access->blocked($u, 'login')) {
            $this->access->deny('Account access is restricted.');
        }
        switch ($action) {
            case 'manual-withdraw':
                $d = $r->validate(['quote_token' => 'required|string|max:10000', 'destination' => 'required|string|max:1000', 'key' => 'required|uuid', 'password' => 'required|string']);
                if (! Hash::check($d['password'], $u->password)) {
                    $this->access->deny('Confirm your password to request a withdrawal.');
                }
                $result = app(ManualWithdrawals::class)->request($u, $d['quote_token'], $d['destination'], $d['key'], $channel);
                break;
            case 'fee-quote':
                $d = $r->validate(['direction' => 'required|in:deposit,withdraw', 'method_id' => 'required|integer', 'asset' => 'required|string', 'amount' => 'required|string']);
                $result = config('riko.rewards_only') && $d['direction'] === 'withdraw'
                    ? app(ManualWithdrawals::class)->quote($u, $d['method_id'], $d['amount'], $channel)
                    : app(PaymentFees::class)->quote($u, $d['direction'], $d['method_id'], $d['asset'], $d['amount'], $channel);
                break;
            case 'recipient':
                $this->access->check($u, 'send', $channel);
                $d = $r->validate(['wallet_id' => 'required|digits_between:6,12']);
                $to = User::where('wallet_id', $d['wallet_id'])->first();
                if (! $to) {
                    $this->access->deny('Recipient not found.');
                }
                $result = ['name' => $to->name, 'wallet_id' => $to->wallet_id];
                break;
            case 'pay-record':
                $this->access->check($u, 'send', $channel);
                $d = $r->validate(['record_id' => 'required|uuid', 'password' => 'required|string']);
                if (! Hash::check($d['password'], $u->password)) {
                    $this->access->deny('Password confirmation failed.');
                }
                $result = DB::transaction(function () use ($u, $d, $channel) {
                    $rec = DB::table('records')->where('id', $d['record_id'])->lockForUpdate()->first();
                    abort_unless($rec && in_array($rec->kind, ['link', 'invoice', 'request']), 404);
                    $details = json_decode($rec->data, true);
                    if ($rec->kind === 'request' && ($details['wallet_id'] ?? null) !== $u->wallet_id) {
                        abort(403);
                    }
                    if ($rec->status === 'paid') {
                        $this->access->deny('This payment is already completed.');
                    }
                    if ($rec->status !== 'open') {
                        $this->access->deny('This payment is no longer available.');
                    }
                    $owner = User::findOrFail($rec->user_id);
                    $this->access->check($owner, 'receive', $channel);
                    $op = $this->ledger->transfer($u->id, $owner->id, $rec->asset, (string) $rec->amount, 'checkout:'.$rec->id, ['record_id' => $rec->id]);
                    $details['payer_id'] = $u->id;
                    $details['operation_id'] = $op->id;
                    DB::table('records')->where('id', $rec->id)->update(['status' => 'paid', 'data' => json_encode($details), 'updated_at' => now()]);

                    return (array) $op;
                }, 5);
                break;
            case 'send':
                $this->access->check($u, 'send', $channel);
                $d = $r->validate(['wallet_id' => 'required|digits_between:6,12', 'asset' => 'required|string', 'amount' => 'required|string', 'key' => 'required|uuid', 'password' => 'required|string']);
                if (! Hash::check($d['password'], $u->password)) {
                    $this->access->deny('Confirm your password to authorise this payment.');
                }
                $to = User::where('wallet_id', $d['wallet_id'])->first();
                if (! $to) {
                    $this->access->deny('Recipient not found.');
                }
                $this->access->check($to, 'receive', $channel);
                if ($d['asset'] === 'RC') {
                    $this->access->deny('Redeem reward points before sending wallet funds.');
                }
                $result = (array) $this->ledger->transfer($u->id, $to->id, $d['asset'], $d['amount'], 'send:'.$u->id.':'.$d['key']);
                break;
            case 'swap-quote':
                $d = $r->validate(['from' => 'required|string', 'to' => 'required|string', 'amount' => 'required|string']);
                $result = $this->wallet->swapQuote($u, $d['from'], $d['to'], $d['amount'], $channel);
                break;
            case 'swap-confirm':
                $d = $r->validate(['quote_id' => 'required|uuid', 'password' => 'required|string']);
                if (! Hash::check($d['password'], $u->password)) {
                    $this->access->deny('Password confirmation failed.');
                }
                $result = (array) $this->wallet->acceptSwap($u, $d['quote_id'], $channel);
                break;
            case 'deposit':case 'withdraw':
                $this->access->check($u, $action, $channel);
                $d = $r->validate(['method_id' => 'required|integer', 'asset' => 'required|string', 'amount' => 'required|string', 'destination' => 'nullable|string|max:200']);
                $amount = $this->ledger->amount($d['amount'], $d['asset']);
                $m = $this->wallet->methods($u, $action)->firstWhere('id', (int) $d['method_id']);
                if (! $m || ! in_array($d['asset'], json_decode($m->assets, true))) {
                    $this->access->deny('Payment method is not available.');
                }
                if (bccomp($amount, $m->minimum, 8) < 0 || bccomp($amount, $m->maximum, 8) > 0) {
                    $this->access->deny('Amount is outside the method limits.');
                }
                $this->access->deny('This payment provider is not connected yet. No funds were moved.');
            case 'card-apply':
                $this->access->deny('Cards are coming soon. Applications are not open yet.');
            case 'card-load':
                $this->access->deny('Cards are coming soon. Card loading is not available yet.');
            case 'task-start':
                $d = $r->validate(['task_id' => 'required|integer', 'device' => 'required|in:android,ios,desktop']);
                $result = $this->rewards->start($u, $d['task_id'], $channel, $d['device']);
                break;
            case 'task-claim':
                $this->access->check($u, 'claim', $channel);
                $d = $r->validate(['session_id' => 'required|uuid']);
                $s = DB::table('task_sessions')->where(['id' => $d['session_id'], 'user_id' => $u->id, 'channel' => $channel])->first();
                abort_unless($s, 404);
                $this->rewards->release($s->id);
                $result = ['message' => 'Verified rewards have been checked. Unverified tasks remain pending.'];
                break;
            case 'referral':
                $d = $r->validate(['wallet_id' => 'required|digits_between:6,12']);
                $this->rewards->attribute($u, $d['wallet_id']);
                break;
            case 'redeem':
                $this->access->check($u, 'redeem', $channel);
                $d = $r->validate(['amount' => 'required|string', 'key' => 'required|uuid']);
                $amount = $this->ledger->amount($d['amount'], 'RC');
                $usd = bcmul($amount, $this->settings->policy()['rc_usd_rate'], 2);
                if (bccomp($usd, '0.01', 2) < 0) {
                    $this->access->deny('Redemption is below the minimum.');
                }
                $result = (array) $this->ledger->post('redeem:'.$u->id.':'.$d['key'], 'redemption', $u->id, 'RC', $amount, [
                    $this->ledger->account('user:'.$u->id, 'RC', $u->id) => bcsub('0', $amount, 8), $this->ledger->account('company:redeemed', 'RC') => $amount,
                    $this->ledger->account('company:redemption', 'USD') => bcsub('0', $usd, 8), $this->ledger->account('user:'.$u->id, 'USD', $u->id) => $usd,
                ], ['usd' => $usd]);
                break;
            case 'support':
                $this->access->check($u, 'support', $channel);
                $d = $r->validate(['body' => 'required|string|max:2000']);
                if (! preg_match('/^[\\x09\\x0A\\x0D\\x20-\\x7E]+$/D', $d['body'])) {
                    $this->access->deny('Please write in English using Latin characters. Your draft has not been sent.');
                }
                DB::table('support_messages')->insert(['user_id' => $u->id, 'sender_id' => $u->id, 'body' => $d['body'], 'created_at' => now(), 'updated_at' => now()]);
                break;
            case 'kyc':
                if ($this->access->verified($u) || $u->kyc_status === 'pending') {
                    $this->access->deny('Your identity verification is already approved or under review.');
                }
                $d = $r->validate(['document' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120', 'legal_name' => 'required|string|max:100']);
                $path = $r->file('document')->store('kyc', 'local');
                $this->wallet->record($u, 'kyc', ['legal_name' => $d['legal_name'], 'private_path' => $path], 'pending');
                $u->kyc_status = 'pending';
                $u->save();
                break;
            case 'request':case 'link':case 'invoice':case 'subscription':
                $this->access->check($u, $action === 'request' ? 'receive' : 'business', $channel);
                $d = $r->validate(['title' => 'required|string|max:120', 'asset' => 'required|string', 'amount' => 'required|string', 'wallet_id' => 'nullable|digits_between:6,12']);
                $amount = $this->ledger->amount($d['amount'], $d['asset']);
                if ($d['asset'] === 'RC') {
                    $this->access->deny('Use a wallet currency for business payments.');
                }
                $result = ['id' => $this->wallet->record($u, $action, $d, $action === 'subscription' ? 'draft' : 'open', $d['asset'], $amount)];
                break;
            case 'dispute':
                $d = $r->validate(['operation_id' => 'required|uuid', 'reason' => 'required|string|max:1000']);
                $ids = DB::table('accounts')->where('user_id', $u->id)->pluck('id');
                abort_unless(DB::table('entries')->whereIn('account_id', $ids)->where('operation_id', $d['operation_id'])->exists(), 404);
                $result = ['id' => $this->wallet->record($u, 'dispute', $d)];
                break;
            case 'preferences':
                $u->push_consent = $r->boolean('push_consent');
                $u->save();
                break;
            case 'notify':
                $this->access->check($u, 'earn', $channel);
                $eligible = User::where('referrer_id', $u->id)->where('push_consent', true)->where(fn ($q) => $q->whereNull('last_reminded_at')->orWhere('last_reminded_at', '<', now()->subDays(7)))->count();
                $this->access->deny($eligible ? 'Push delivery is not connected. No notifications were sent.' : 'No eligible opted-in referrals are available to notify.');
            default: abort(404);
        }
        if ($r->expectsJson()) {
            return response()->json(['ok' => true, 'result' => $result]);
        }
        if ($action === 'swap-quote') {
            return back()->with('quote', $result);
        }
        if ($action === 'task-start') {
            return back()->with('pairing', $result);
        }

        return back()->with('success', $result['message'] ?? 'Saved successfully.');
    }
}
