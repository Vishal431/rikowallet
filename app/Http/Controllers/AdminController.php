<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Ledger;
use App\Services\ManualWithdrawals;
use App\Services\Settings;
use App\Services\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AdminController extends Controller
{
    public function __construct(private Settings $settings, private Ledger $ledger, private Wallet $wallet) {}

    private function guard(Request $r): void
    {
        abort_unless($r->user()?->role === 'admin', 403);
    }

    public function index(Request $r, string $page = 'overview')
    {
        $this->guard($r);
        if ($page === 'site-guide') {
            return view('admin.site-guide', ['page' => $page, 'health' => app(\App\Services\SiteHealth::class)->report()]);
        }
        if ($page === 'withdrawals') {
            return view('admin.withdrawals', ['page' => $page, 'currencies' => DB::table('payout_currencies')->get(), 'methods' => DB::table('payment_methods')->where('manual', true)->get(), 'requests' => DB::table('records')->where('kind', 'manual_withdrawal')->latest()->paginate(25)]);
        }
        abort_unless(in_array($page, ['overview', 'users', 'kyc', 'earning', 'methods', 'referrals', 'contest', 'liquidity', 'support', 'operations', 'cron', 'audit', 'providers']), 404);
        $lastDay = now()->timezone('Asia/Kolkata')->subDay()->startOfDay();
        $firstDay = $lastDay->copy()->subDays(29);
        $savedStats = DB::table('daily_stats')->whereBetween('day', [$firstDay->toDateString(), $lastDay->toDateString()])->get()->keyBy('day');
        $stats = collect(range(0, 29))->map(function ($offset) use ($firstDay, $savedStats) {
            $day = $firstDay->copy()->addDays($offset)->toDateString();

            return $savedStats->get($day) ?? (object) ['day' => $day, 'data' => '{}', 'updated_at' => null];
        });
        $data = ['page' => $page, 'policy' => $this->settings->policy(),
            'users' => User::latest()->paginate(25),
            'accounts' => DB::table('accounts')->whereNotNull('user_id')->select('asset', DB::raw('SUM(balance) as balance'))->groupBy('asset')->get(),
            'company' => DB::table('accounts')->whereNull('user_id')->where('owner', '!=', 'sandbox:source')->get(),
            'operations' => DB::table('operations')->latest()->limit(30)->get(),
            'tasks' => DB::table('tasks')->get(), 'methods' => DB::table('payment_methods')->get(),
            'records' => DB::table('records')->latest()->limit(50)->get(),
            'messages' => DB::table('support_messages')->latest()->limit(50)->get(),
            'logs' => DB::table('audit_logs')->latest('created_at')->limit(40)->get(),
            'stats' => $stats,
            'seasons' => DB::table('contest_seasons')->latest()->get(),
        ];

        return view('admin.index', $data);
    }

    public function save(Request $r, string $action)
    {
        $this->guard($r);
        $result = [];
        $target = null;
        DB::transaction(function () use ($r, $action, &$result, &$target) {
            switch ($action) {
                case 'payout-rate':
                    $d = $r->validate(['code' => 'required|regex:/^[A-Z][A-Z0-9]{1,11}$/', 'precision' => 'required|integer|min:0|max:8', 'per_rc' => ['required', 'regex:/^(0|[1-9][0-9]{0,8})(\.[0-9]{1,12})?$/']]);
                    abort_unless(bccomp($d['per_rc'], '0', 12) > 0, 422, 'Rate must be positive.');
                    DB::table('payout_currencies')->updateOrInsert(['code' => $d['code']], $d + ['updated_at' => now(), 'created_at' => now()]);
                    $target = $d['code'];
                    break;
                case 'manual-method':
                    $d = $r->validate(['method_id' => 'nullable|integer|exists:payment_methods,id', 'name' => 'required|string|max:80', 'category' => 'required|in:Cards,Crypto,Wallet', 'payout_currency' => 'required|exists:payout_currencies,code', 'countries' => 'required|array|min:1', 'countries.*' => 'in:'.implode(',', array_keys(config('riko.countries'))), 'minimum' => 'required|decimal:0,2|min:0.01|max:100000000', 'maximum' => 'required|decimal:0,2|gte:minimum|max:100000000', 'fee_mode' => 'required|in:flat,percent,capped', 'fee_value' => 'required|decimal:0,8|min:0|max:1000000', 'fee_cap' => 'nullable|required_if:fee_mode,capped|decimal:0,8|min:0|max:1000000', 'fee_tax_percent' => 'required|decimal:0,4|min:0|max:100', 'instructions' => 'required|string|max:1000', 'image' => 'nullable|image|mimes:png,jpg,jpeg,webp|max:1024|dimensions:max_width=1024,max_height=1024']);
                    if ($d['fee_mode'] !== 'flat') {
                        abort_if(bccomp($d['fee_value'], '100', 8) > 0, 422, 'Percentage cannot exceed 100.');
                    }
                    $target = $d['method_id'] ?? null;
                    if ($target) {
                        abort_unless(DB::table('payment_methods')->where(['id' => $target, 'manual' => true])->exists(), 422);
                    }
                    unset($d['method_id'], $d['image']);
                    $d['fee_cap'] = $d['fee_mode'] === 'capped' ? $d['fee_cap'] : null;
                    if ($r->hasFile('image')) {
                        $file = $r->file('image');
                        $d['image_file'] = (string) Str::uuid().'.'.$file->extension();
                        $file->storeAs('method-images', $d['image_file'], 'local');
                    }
                    $d += ['manual' => true, 'direction' => 'withdraw', 'assets' => '["RC"]', 'updated_at' => now()];
                    $d['countries'] = json_encode($d['countries']);
                    $d['enabled'] = $r->boolean('enabled');
                    if ($target) {
                        DB::table('payment_methods')->where('id', $target)->update($d);
                    } else {
                        $target = DB::table('payment_methods')->insertGetId($d + ['created_at' => now()]);
                    }
                    break;
                case 'manual-review':
                    $d = $r->validate(['record_id' => 'required|uuid', 'decision' => 'required|in:processing,paid,rejected,failed', 'reference' => 'required|string|min:3|max:300']);
                    app(ManualWithdrawals::class)->review($r->user(), $d['record_id'], $d['decision'], $d['reference']);
                    $target = $d['record_id'];
                    break;
                case 'method-fees':
                    $d = $r->validate(['method_id' => 'required|integer|exists:payment_methods,id', 'processing_percent' => 'required|numeric|min:0|max:100', 'platform_percent' => 'required|numeric|min:0|max:100', 'fee_tax_percent' => 'required|numeric|min:0|max:100', 'fee' => 'required|numeric|min:0|max:1000000']);
                    $target = $d['method_id'];
                    unset($d['method_id']);
                    DB::table('payment_methods')->where('id', $target)->update($d + ['updated_at' => now()]);
                    break;
                case 'policy':
                    $d = $r->validate(['kyc_scope' => 'sometimes|in:all,new', 'referral_mode' => 'required|in:OFF,A,B,C', 'referral_percent' => 'required|numeric|min:0|max:100', 'newcomer_bonus' => 'required|numeric|min:0|max:1000000', 'referrer_bonus' => 'required|numeric|min:0|max:1000000', 'rc_usd_rate' => 'required|numeric|min:0.000001|max:100', 'swap_fee_percent' => 'required|numeric|min:0|max:10', 'swap_max_usd' => 'required|numeric|min:1|max:1000000', 'kyc_actions' => 'array', 'kyc_actions.*' => 'in:'.implode(',', config('riko.actions'))]);
                    $p = $this->settings->policy();
                    foreach (['earn_global', 'earn_web', 'earn_app', 'contest', 'referral_kyc', 'newcomer_kyc', 'referrer_kyc'] as $k) {
                        $p[$k] = $r->boolean($k);
                    }
                    if (($d['kyc_scope'] ?? 'all') !== ($p['kyc_scope'] ?? 'all') || ($d['kyc_actions'] ?? []) !== $p['kyc_actions']) {
                        $p['kyc_effective_at'] = now()->toIso8601String();
                    }
                    $p = array_merge($p, $d);
                    $p['kyc_actions'] = $d['kyc_actions'] ?? [];
                    $this->settings->put('policy', $p);
                    $target = 'policy';
                    break;
                case 'method':
                    $d = $r->validate(['name' => 'required|string|max:80', 'direction' => 'required|in:deposit,withdraw', 'countries' => 'required|array|min:1', 'countries.*' => 'in:'.implode(',', array_keys(config('riko.countries'))), 'assets' => 'required|array|min:1', 'assets.*' => 'in:'.implode(',', config('riko.payment_assets')), 'minimum' => 'required|numeric|min:0.01', 'maximum' => 'required|numeric|gte:minimum', 'fee' => 'required|numeric|min:0', 'instructions' => 'nullable|string|max:1000']);
                    foreach (['countries', 'assets'] as $k) {
                        $d[$k] = json_encode($d[$k]);
                    }$d['enabled'] = $r->boolean('enabled');
                    $d['provider_ready'] = false;
                    $d['created_at'] = now();
                    $d['updated_at'] = now();
                    $target = DB::table('payment_methods')->insertGetId($d);
                    break;
                case 'task':
                    $rateInput = $r->validate(['countries' => 'required|array|min:1', 'rates' => 'sometimes|array:'.implode(',', array_keys(config('riko.countries'))), 'rates.*' => 'nullable|string|max:20']);
                    $rates = [];
                    foreach ($rateInput['rates'] ?? [] as $country => $rate) {
                        if ($rate === null || $rate === '') {
                            continue;
                        }
                        abort_unless(in_array($country, $r->input('countries', [])), 422, 'A country rate requires that country to be enabled.');
                        $rates[$country] = $this->ledger->amount($rate, 'RC');
                        abort_if(bccomp($rates[$country], '100000', 8) > 0, 422, 'Country rate exceeds the task reward limit.');
                    }
                    $d = $r->validate(['title' => 'required|string|max:100', 'category' => 'required|in:app_surf,web_surf,app_install,survey', 'instructions' => 'required|string|max:2000', 'package_name' => 'nullable|regex:/^[a-zA-Z][a-zA-Z0-9_]*(\\.[a-zA-Z0-9_]+)+$/', 'url' => 'nullable|url:https|max:500', 'reward' => 'required|numeric|min:0|max:100000', 'budget' => 'required|numeric|min:0|max:10000000', 'countries' => 'required|array|min:1', 'countries.*' => 'in:'.implode(',', array_keys(config('riko.countries'))), 'devices' => 'required|array|min:1', 'devices.*' => 'in:android,ios,desktop', 'channels' => 'required|array|min:1', 'channels.*' => 'in:web,app']);
                    foreach (['countries', 'devices', 'channels'] as $k) {
                        $d[$k] = json_encode($d[$k]);
                    }$d['enabled'] = $r->boolean('enabled');
                    $d['provider_ready'] = false;
                    $d['created_at'] = now();
                    $d['updated_at'] = now();
                    $d['reward'] = $this->ledger->amount((string) $d['reward'], 'RC');
                    $d['rates'] = json_encode($rates);
                    $target = DB::table('tasks')->insertGetId($d);
                    break;
                case 'kyc':
                    $d = $r->validate(['user_id' => 'required|integer', 'status' => 'required|in:approved,rejected', 'reason' => 'required|string|max:500']);
                    $u = User::whereKey($d['user_id'])->lockForUpdate()->firstOrFail();
                    abort_if($u->id === $r->user()->id, 403, 'You cannot approve your own identity.');
                    $u->kyc_status = $d['status'];
                    $u->kyc_expires_at = $d['status'] === 'approved' ? now()->addYear() : null;
                    $u->save();
                    DB::table('records')->where(['user_id' => $u->id, 'kind' => 'kyc', 'status' => 'pending'])->update(['status' => $d['status'], 'updated_at' => now()]);
                    $target = $u->wallet_id;
                    break;
                case 'restriction':
                    $d = $r->validate(['user_id' => 'required|integer', 'scope' => 'required|in:account,login,earning,support,financial,app_surf,web_surf,app_install,survey', 'duration' => 'required|in:clear,day,week,permanent', 'reason' => 'required|string|max:500']);
                    $u = User::whereKey($d['user_id'])->lockForUpdate()->firstOrFail();
                    abort_if($u->id === $r->user()->id, 403);
                    $v = $u->restrictions ?? [];
                    if ($d['duration'] === 'clear') {
                        unset($v[$d['scope']]);
                    } else {
                        $v[$d['scope']] = match ($d['duration']) {
                            'permanent' => 'permanent','day' => now()->addDay()->toIso8601String(),default => now()->addWeek()->toIso8601String()
                        };
                    }
                    $u->restrictions = $v;
                    $u->save();
                    $target = $u->wallet_id;
                    break;
                case 'reply':
                    $d = $r->validate(['user_id' => 'required|exists:users,id', 'body' => 'required|string|max:2000']);
                    DB::table('support_messages')->insert(['user_id' => $d['user_id'], 'sender_id' => $r->user()->id, 'body' => $d['body'], 'created_at' => now(), 'updated_at' => now()]);
                    $target = $d['user_id'];
                    break;
                case 'adjustment':
                    $d = $r->validate(['user_id' => 'required|exists:users,id', 'asset' => 'required|in:'.implode(',', array_keys(config('riko.assets'))), 'amount' => 'required|string', 'reason' => 'required|string|max:500']);
                    $d['amount'] = $this->ledger->amount($d['amount'], $d['asset']);
                    $d['requested_by'] = $r->user()->id;
                    $target = $this->wallet->record($r->user(), 'adjustment', $d, 'pending', $d['asset'], $d['amount']);
                    break;
                case 'approve-adjustment':
                    $d = $r->validate(['record_id' => 'required|uuid']);
                    $rec = DB::table('records')->where(['id' => $d['record_id'], 'kind' => 'adjustment'])->lockForUpdate()->first();
                    abort_unless($rec, 404);
                    abort_unless($rec->status === 'pending', 409);
                    $v = json_decode($rec->data, true);
                    abort_if($v['requested_by'] === $r->user()->id, 403, 'A different administrator must approve this adjustment.');
                    $this->ledger->post('adjust:'.$rec->id, 'admin_credit', $v['user_id'], $v['asset'], $v['amount'], [
                        $this->ledger->account('company:adjustments', $v['asset']) => bcsub('0', $v['amount'], 8), $this->ledger->account('user:'.$v['user_id'], $v['asset'], $v['user_id']) => $v['amount'],
                    ], ['reason' => $v['reason'], 'requested_by' => $v['requested_by'], 'approved_by' => $r->user()->id]);
                    DB::table('records')->where('id', $rec->id)->update(['status' => 'approved', 'updated_at' => now()]);
                    $target = $rec->id;
                    break;
                case 'season':
                    $d = $r->validate(['month' => 'required|date_format:Y-m|unique:contest_seasons,month', 'places' => 'required|in:10,15,20', 'prize' => 'required|string']);
                    abort_if($d['month'] < now()->format('Y-m'), 422, 'Cannot create a past contest.');
                    $prize = $this->ledger->amount($d['prize'], 'RC');
                    $pool = bcmul($prize, $d['places'], 8);
                    $target = DB::table('contest_seasons')->insertGetId(['month' => $d['month'], 'status' => 'active', 'pool' => $pool, 'prizes' => json_encode(array_fill(0, (int) $d['places'], $prize)), 'created_at' => now(), 'updated_at' => now()]);
                    $this->ledger->post('contest:reserve:'.$target, 'contest_reserve', null, 'RC', $pool, [$this->ledger->account('company:rewards', 'RC') => bcsub('0', $pool, 8), $this->ledger->account('contest:'.$target, 'RC') => $pool]);
                    break;
                default:abort(404);
            }
            DB::table('audit_logs')->insert(['actor_id' => $r->user()->id, 'action' => $action, 'target' => (string) $target, 'data' => json_encode(['reason' => $r->input('reason'), 'changed_fields' => array_keys($r->except(['_token', 'password']))]), 'created_at' => now()]);
        }, 5);

        return back()->with('success', 'Saved. The change has been recorded in the audit log.');
    }

    public function document(Request $r, string $id)
    {
        $this->guard($r);
        $v = DB::table('records')->where(['id' => $id, 'kind' => 'kyc'])->first();
        abort_unless($v, 404);
        $d = json_decode($v->data, true);
        DB::table('audit_logs')->insert(['actor_id' => $r->user()->id, 'action' => 'kyc_document_view', 'target' => $id, 'created_at' => now()]);

        return Storage::disk('local')->download($d['private_path'], 'identity-document');
    }
}
