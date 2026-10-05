<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Wallet
{
    public function __construct(private Ledger $ledger, private Access $access, private Settings $settings) {}

    public function record(User $u, string $kind, array $data, string $status = 'pending', ?string $asset = null, ?string $amount = null): string
    {
        $id = (string) Str::uuid();
        DB::table('records')->insert(['id' => $id, 'user_id' => $u->id, 'kind' => $kind, 'status' => $status, 'asset' => $asset, 'amount' => $amount, 'data' => json_encode($data), 'created_at' => now(), 'updated_at' => now()]);

        return $id;
    }

    public function methods(User $u, string $direction)
    {
        if (config('riko.rewards_only')) {
            if ($direction !== 'withdraw') {
                return collect();
            }

            return DB::table('payment_methods')->where(['enabled' => true, 'direction' => 'withdraw', 'manual' => true])->get()->filter(fn ($m) => in_array($u->country, json_decode($m->countries, true)))->map(function ($m) {
                $m->image_url = $m->image_file ? url('/method-image/'.$m->image_file) : null;

                return $m;
            })->values();
        }

        return DB::table('payment_methods')->where('enabled', true)->where('direction', $direction)->get()->filter(fn ($m) => in_array($u->country, json_decode($m->countries, true)) && count(array_intersect(json_decode($m->assets, true), config('riko.payment_assets'))) > 0)->map(function ($m) {
            $m->assets = json_encode(array_values(array_intersect(json_decode($m->assets, true), config('riko.payment_assets'))));

            return $m;
        })->values();
    }

    public function snapshot(User $u, string $channel): array
    {
        $policy = $this->settings->policy();
        $u->last_active_at = now();
        $u->save();
        DB::table('user_activity_days')->insertOrIgnore([
            'user_id' => $u->id, 'day' => now()->timezone('Asia/Kolkata')->toDateString(), 'first_seen_at' => now(),
        ]);
        $accounts = DB::table('accounts')->where('owner', 'user:'.$u->id)->get();
        $balances = collect(config('riko.assets'))->only(config('riko.rewards_only') ? ['RC'] : config('riko.enabled_assets'))->map(fn ($precision, $asset) => ['asset' => $asset, 'precision' => $precision, 'balance' => (string) ($accounts->firstWhere('asset', $asset)->balance ?? '0')])->values();
        $accountIds = DB::table('accounts')->where('user_id', $u->id)->pluck('id');
        $opIds = DB::table('entries')->whereIn('account_id', $accountIds)->pluck('operation_id');
        $activity = DB::table('operations')->whereIn('id', $opIds)->latest()->limit(40)->get();
        $records = DB::table('records')->where('user_id', $u->id)->latest()->limit(100)->get()->map(function ($r) {
            $r->data = json_decode($r->data, true);
            unset($r->data['private_path']);
            unset($r->data['destination_encrypted'], $r->data['fingerprint']);

            return $r;
        });
        $enabled = $policy['earn_global'] && $policy['earn_'.$channel];
        $locked = $this->access->requiresKyc($u, 'earn') && ! $this->access->verified($u);
        $tasks = $enabled && ! $locked ? DB::table('tasks')->where('enabled', true)->get()->filter(fn ($t) => in_array($u->country, json_decode($t->countries, true)) && in_array($channel, json_decode($t->channels, true)))->values() : collect();
        $referrals = User::where('referrer_id', $u->id)->get(['id', 'wallet_id', 'name', 'created_at', 'last_active_at'])->map(function ($r) {
            $r->active = DB::table('operations')->where('user_id', $r->id)->where('type', 'task_reward')->where('created_at', '>=', now()->subDays(30))->exists();
            $r->commission = (string) DB::table('referral_rewards')->where('referred_id', $r->id)->where('status', 'credited')->where('kind', 'commission')->sum('amount');

            return $r;
        });
        $leaders = $policy['contest'] ? DB::table('referral_rewards')->join('users', 'users.id', '=', 'referral_rewards.user_id')->where('referral_rewards.kind', 'commission')->where('referral_rewards.status', 'credited')->where('referral_rewards.updated_at', '>=', now()->startOfMonth())->select('users.wallet_id', DB::raw('SUM(amount) as score'))->groupBy('users.wallet_id')->orderByDesc('score')->orderBy('users.wallet_id')->limit(20)->get() : collect();

        return [
            'rewards_only' => (bool) config('riko.rewards_only'),
            'user' => ['name' => $u->name, 'email' => $u->email, 'wallet_id' => $u->wallet_id, 'country' => $u->country, 'kyc_status' => $u->kyc_status, 'role' => $u->role, 'referrer_id' => $u->referrer_id, 'push_consent' => $u->push_consent],
            'sandbox' => (bool) config('riko.sandbox'), 'policy' => $policy, 'earning_enabled' => $enabled, 'earning_locked' => $locked,
            'balances' => $balances, 'activity' => $activity, 'records' => $records, 'tasks' => $tasks,
            'sessions' => DB::table('task_sessions')->where('user_id', $u->id)->get(['id', 'task_id', 'reward', 'status', 'expires_at', 'created_at']),
            'referrals' => $referrals, 'referral_rewards' => DB::table('referral_rewards')->where('user_id', $u->id)->get(),
            'leaderboard' => $leaders, 'messages' => DB::table('support_messages')->where('user_id', $u->id)->oldest()->limit(100)->get(),
            'deposit_methods' => $this->methods($u, 'deposit'), 'withdraw_methods' => $this->methods($u, 'withdraw'),
        ];
    }

    public function swapQuote(User $u, string $from, string $to, string $amount, string $channel): array
    {
        $this->access->check($u, 'swap', $channel);
        $amount = $this->ledger->amount($amount, $from);
        if ($from === $to || ! in_array($from, config('riko.payment_assets')) || ! in_array($to, config('riko.payment_assets'))) {
            $this->access->deny('Select two different wallet currencies. RikoCoin uses redemption.');
        }
        $prices = $this->settings->get('prices', []);
        if (! config('riko.sandbox') || ! isset($prices[$from],$prices[$to])) {
            $this->access->deny('A live pricing provider must be connected before swaps are available.');
        }
        $p = $this->settings->policy();
        $usd = bcmul($amount, $prices[$from], 8);
        if (bccomp($usd, $p['swap_max_usd'], 8) > 0) {
            $this->access->deny('Amount exceeds the administrator swap limit.');
        }
        $fee = bcdiv(bcmul($amount, $p['swap_fee_percent'], 8), '100', config('riko.assets')[$from]);
        $output = bcdiv(bcmul(bcsub($amount, $fee, 8), $prices[$from], 8), $prices[$to], config('riko.assets')[$to]);
        if (bccomp($output, '0', 8) <= 0) {
            $this->access->deny('Amount is below the minimum output.');
        }
        $data = ['from' => $from, 'to' => $to, 'input' => $amount, 'fee' => $fee, 'output' => $output, 'expires_at' => now()->addSeconds(60)->toIso8601String()];
        $id = $this->record($u, 'swap_quote', $data, 'quoted', $from, $amount);

        return ['id' => $id] + $data;
    }

    public function acceptSwap(User $u, string $id, string $channel): object
    {
        $this->access->check($u, 'swap', $channel);

        return DB::transaction(function () use ($u, $id) {
            $q = DB::table('records')->where(['id' => $id, 'user_id' => $u->id, 'kind' => 'swap_quote'])->lockForUpdate()->first();
            abort_unless($q, 404);
            if ($q->status === 'completed') {
                return DB::table('operations')->where('key', 'swap:'.$id)->first();
            }
            $d = json_decode($q->data, true);
            if (! in_array($d['from'], config('riko.payment_assets')) || ! in_array($d['to'], config('riko.payment_assets'))) {
                $this->access->deny('This currency is not available yet. Request a new quote.');
            }
            if (now()->gt($d['expires_at'])) {
                $this->access->deny('Quote expired. Request a new quote.');
            }
            $op = $this->ledger->post('swap:'.$id, 'swap', $u->id, $d['from'], $d['input'], [
                $this->ledger->account('user:'.$u->id, $d['from'], $u->id) => bcsub('0', $d['input'], 8),
                $this->ledger->account('company:inventory', $d['from']) => bcsub($d['input'], $d['fee'], 8),
                $this->ledger->account('company:fees', $d['from']) => $d['fee'],
                $this->ledger->account('company:inventory', $d['to']) => bcsub('0', $d['output'], 8),
                $this->ledger->account('user:'.$u->id, $d['to'], $u->id) => $d['output'],
            ], $d);
            DB::table('records')->where('id', $id)->update(['status' => 'completed', 'updated_at' => now()]);

            return $op;
        }, 5);
    }
}
