<?php

use App\Models\User;
use App\Services\Access;
use App\Services\Ledger;
use App\Services\Rewards;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Validation\ValidationException;

Artisan::command('riko:stats {--day=}', function () {
    $day = $this->option('day') ?: now()->timezone('Asia/Kolkata')->subDay()->toDateString();
    if (! preg_match('/^\d{4}-\d{2}-\d{2}$/D', $day)) {
        $this->error('Use a YYYY-MM-DD date.');

        return 1;
    }
    $start = Carbon::parse($day, 'Asia/Kolkata')->startOfDay()->timezone(config('app.timezone'));
    $end = $start->copy()->addDay();
    $ops = DB::table('operations')->where('created_at', '>=', $start)->where('created_at', '<', $end);
    $fees = DB::table('entries')->join('accounts', 'accounts.id', '=', 'entries.account_id')
        ->join('operations', 'operations.id', '=', 'entries.operation_id')
        ->where('accounts.owner', 'company:fees')->where('operations.created_at', '>=', $start)->where('operations.created_at', '<', $end)
        ->select('accounts.asset', DB::raw('SUM(entries.amount) as amount'))->groupBy('accounts.asset')->pluck('amount', 'asset')->all();
    $data = [
        'signups' => DB::table('users')->where('created_at', '>=', $start)->where('created_at', '<', $end)->count(),
        'active_users' => DB::table('user_activity_days')->where('day', $day)->count(),
        'transactions' => (clone $ops)->whereNotIn('type', ['sandbox_funding', 'reward_reserve', 'reward_reservation_return'])->count(),
        'task_rc' => (string) (clone $ops)->where('type', 'task_reward')->sum('amount'),
        'referral_rc' => (string) (clone $ops)->where('type', 'referral_commission')->sum('amount'),
        'platform_fees' => $fees,
        'fees_usd' => (string) ($fees['USD'] ?? '0'),
    ];
    DB::table('daily_stats')->updateOrInsert(['day' => $day], ['data' => json_encode($data), 'updated_at' => now()]);
    $this->info('Daily statistics refreshed.');
});
Artisan::command('riko:rewards', function (Rewards $rewards) {
    $expired = $rewards->expireReservations();
    foreach (DB::table('task_sessions')->where('status', 'verified_pending')->limit(100)->pluck('id') as $id) {
        try {
            $rewards->release($id);
        } catch (ValidationException $e) {
        }
    }
    $rewards->releaseReferrals();
    $this->info('Eligible rewards processed; '.$expired.' expired reservations returned.');
});
Artisan::command('riko:contest', function (Ledger $ledger) {
    foreach (DB::table('contest_seasons')->where('status', 'active')->where('month', '<', now()->format('Y-m'))->get() as $season) {
        DB::transaction(function () use ($season) {
            $season = DB::table('contest_seasons')->where('id', $season->id)->lockForUpdate()->first();
            if ($season->status !== 'active') {
                return;
            }
            $prizes = json_decode($season->prizes, true);
            $start = Carbon::parse($season->month.'-01')->startOfMonth();
            $end = $start->copy()->addMonth();
            $winners = DB::table('referral_rewards')->where('kind', 'commission')->where('status', 'credited')->where('updated_at', '>=', $start)->where('updated_at', '<', $end)->select('user_id', DB::raw('SUM(amount) as score'), DB::raw('MIN(updated_at) as first_at'))->groupBy('user_id')->orderByDesc('score')->orderBy('first_at')->orderBy('user_id')->limit(count($prizes))->get();
            foreach ($winners as $index => $winner) {
                DB::table('contest_results')->insertOrIgnore(['season_id' => $season->id, 'user_id' => $winner->user_id, 'rank' => $index + 1, 'score' => $winner->score, 'prize' => $prizes[$index], 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
            }
            DB::table('contest_seasons')->where('id', $season->id)->update(['status' => 'frozen', 'updated_at' => now()]);
        }, 5);
    }
    foreach (DB::table('contest_results')->where('status', 'pending')->get() as $row) {
        DB::transaction(function () use ($row, $ledger) {
            $row = DB::table('contest_results')->where('id', $row->id)->lockForUpdate()->first();
            if ($row->status !== 'pending') {
                return;
            }
            $u = User::find($row->user_id);
            if (! $u || app(Access::class)->blocked($u, 'earning')) {
                return;
            }
            $ledger->post('contest:prize:'.$row->id, 'contest_prize', $row->user_id, 'RC', $row->prize, [
                $ledger->account('contest:'.$row->season_id, 'RC') => bcsub('0', $row->prize, 8), $ledger->account('user:'.$row->user_id, 'RC', $row->user_id) => $row->prize,
            ]);
            DB::table('contest_results')->where('id', $row->id)->update(['status' => 'paid', 'updated_at' => now()]);
        }, 5);
    }
    $this->info('Contest entitlements settled safely.');
});
Schedule::command('riko:stats')->dailyAt('00:15')->timezone('Asia/Kolkata')->withoutOverlapping();
Schedule::command('riko:rewards')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('riko:contest')->monthlyOn(1, '00:30')->timezone('Asia/Kolkata')->withoutOverlapping();
