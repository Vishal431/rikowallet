<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class Rewards
{
    public function __construct(private Ledger $ledger, private Settings $settings, private Access $access) {}

    public function start(User $u, int $taskId, string $channel, string $device): array
    {
        $this->access->check($u, 'earn', $channel);

        return DB::transaction(function () use ($u, $taskId, $channel, $device) {
            $t = DB::table('tasks')->where('id', $taskId)->lockForUpdate()->first();
            abort_unless($t && $t->enabled, 404);

            // 1. User task category restrictions (e.g. banned from app_surf)
            if ($this->access->blocked($u, 'app_surf') && ($t->category === 'app_surf' || str_contains($t->title, 'Surf') || str_contains((string) $t->package_name, 'drama'))) {
                $this->access->deny('Your account is restricted from app surf tasks.');
            }
            if ($this->access->blocked($u, $t->category) || $this->access->blocked($u, 'task_' . $t->id)) {
                $this->access->deny('Your account is restricted from this task category.');
            }

            // 2. Country & Worldwide eligibility
            $allowedCountries = json_decode($t->countries ?? '[]', true) ?: [];
            $isWorldwide = in_array('*', $allowedCountries) || in_array('WW', $allowedCountries) || in_array('ALL', $allowedCountries) || empty($allowedCountries);
            $userCountry = strtoupper(trim($u->country ?? ''));

            if (! $isWorldwide && ! in_array($userCountry, $allowedCountries)) {
                $this->access->deny('This task is not available for your country.');
            }

            foreach (['channels' => $channel, 'devices' => $device] as $key => $value) {
                if (! in_array($value, json_decode($t->$key, true))) {
                    $this->access->deny('This task is not available for your device or platform.');
                }
            }
            if (! $t->provider_ready) {
                $this->access->deny('This task is waiting for its verification partner.');
            }

            // 3. 1 task completion per UTC day (Resets at 00:00 UTC)
            $todayUtc = now()->timezone('UTC')->toDateString();
            $existingSession = DB::table('task_sessions')
                ->where('user_id', $u->id)
                ->where('task_id', $t->id)
                ->where(function ($query) use ($todayUtc) {
                    $query->where('task_date_utc', $todayUtc)
                          ->orWhereNull('task_date_utc');
                })
                ->first();

            if ($existingSession) {
                $sessionDate = $existingSession->task_date_utc ?: substr($existingSession->created_at, 0, 10);
                if ($sessionDate === $todayUtc) {
                    if ($existingSession->status === 'credited') {
                        $this->access->deny('You have already completed this task today. Daily limit resets at 00:00 UTC.');
                    } else {
                        $this->access->deny('You already have an active session for this task today. See your activity for pairing code.');
                    }
                }
            }

            // 4. Country rate calculation with Worldwide fallback (0 if not eligible)
            $p = $u->referral_policy ?? $this->settings->policy();
            $rates = json_decode($t->rates ?? '{}', true) ?: [];

            if (isset($rates[$userCountry]) && $rates[$userCountry] !== '') {
                $reward = (string) $rates[$userCountry];
            } elseif (isset($rates['*']) && $rates['*'] !== '') {
                $reward = (string) $rates['*'];
            } elseif (isset($rates['WW']) && $rates['WW'] !== '') {
                $reward = (string) $rates['WW'];
            } elseif (isset($rates['ALL']) && $rates['ALL'] !== '') {
                $reward = (string) $rates['ALL'];
            } else {
                $reward = (string) ($t->reward ?? '0');
            }

            if (bccomp($reward, '0', 8) <= 0) {
                $this->access->deny('This task currently has no reward allocated for your region (Reward: 0).');
            }

            $commission = $u->referrer_id && $p['referral_mode'] === 'C' ? bcdiv(bcmul($reward, $p['referral_percent'], 8), '100', 2) : '0';
            $total = bcadd($reward, $commission, 8);
            if (bccomp($t->budget, $total, 8) < 0) {
                $this->access->deny('This task budget is exhausted.');
            }
            $id = (string) Str::uuid();
            $code = Str::random(40);
            $this->ledger->post('task:reserve:'.$id, 'reward_reserve', $u->id, 'RC', $total, [
                $this->ledger->account('company:rewards', 'RC') => bcsub('0', $total, 8),
                $this->ledger->account('task:'.$id, 'RC') => $total,
            ]);
            DB::table('tasks')->where('id', $t->id)->update(['budget' => bcsub($t->budget, $total, 8)]);
            DB::table('task_sessions')->insert([
                'id' => $id,
                'user_id' => $u->id,
                'task_id' => $t->id,
                'code_hash' => hash('sha256', $code),
                'channel' => $channel,
                'device' => $device,
                'reward' => $reward,
                'policy' => json_encode($p + ['commission' => $commission]),
                'status' => 'started',
                'task_date_utc' => $todayUtc,
                'expires_at' => now()->addDay(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return ['id' => $id, 'pairing_code' => $code, 'reward' => $reward, 'status' => 'started'];
        }, 5);
    }

    public function complete(string $code, string $package, string $event, bool $verifyOnly = false): array
    {
        return DB::transaction(function () use ($code, $package, $event, $verifyOnly) {
            $s = DB::table('task_sessions')->where('code_hash', hash('sha256', $code))->lockForUpdate()->first();
            abort_unless($s, 422, 'Invalid earning code.');
            $t = DB::table('tasks')->find($s->task_id);
            abort_unless($t && $t->package_name === $package, 422, 'Task package does not match.');

            // Verify user restriction at completion time
            $uCheck = User::find($s->user_id);
            if ($uCheck) {
                if ($this->access->blocked($uCheck, 'app_surf') && ($t->category === 'app_surf' || str_contains($t->title, 'Surf') || str_contains((string) $t->package_name, 'drama'))) {
                    abort(403, 'Account is restricted from app surf tasks.');
                }
                if ($this->access->blocked($uCheck, $t->category) || $this->access->blocked($uCheck, 'task_' . $t->id)) {
                    abort(403, 'Account is restricted from this task category.');
                }
            }
            abort_if($s->status === 'expired', 422, 'Earning session expired.');
            if ($s->status === 'credited') {
                abort_unless($verifyOnly || $s->event_id === $event, 409, 'Completion already recorded.');

                return ['valid' => true, 'status' => 'credited'];
            }
            abort_if($s->status === 'started' && now()->gt($s->expires_at), 422, 'Earning session expired.');
            if ($verifyOnly) {
                return ['valid' => true, 'status' => $s->status];
            }
            abort_if($s->event_id && $s->event_id !== $event, 409, 'Conflicting completion.');
            abort_if(DB::table('task_sessions')->where('event_id', $event)->where('id', '!=', $s->id)->exists(), 409, 'Duplicate provider event.');
            DB::table('task_sessions')->where('id', $s->id)->update(['event_id' => $event, 'status' => 'verified_pending', 'updated_at' => now()]);
            try {
                $this->release($s->id);
            } catch (ValidationException $e) {
                return ['valid' => true, 'status' => 'verified_pending', 'message' => 'Evidence saved; reward eligibility is pending.'];
            }

            return ['valid' => true, 'status' => 'credited'];
        }, 5);
    }

    public function expireReservations(): int
    {
        $count = 0;
        // Only unverified sessions expire. Verified evidence awaiting KYC keeps its funds.
        foreach (DB::table('task_sessions')->where('status', 'started')->where('expires_at', '<', now())->limit(500)->get(['id', 'task_id']) as $candidate) {
            $count += DB::transaction(function () use ($candidate) {
                $task = DB::table('tasks')->where('id', $candidate->task_id)->lockForUpdate()->first();
                $session = DB::table('task_sessions')->where('id', $candidate->id)->lockForUpdate()->first();
                if (! $task || ! $session || $session->status !== 'started' || now()->lte($session->expires_at)) {
                    return 0;
                }
                $policy = json_decode($session->policy, true);
                $total = bcadd($session->reward, $policy['commission'] ?? '0', 8);
                $this->ledger->post('task:expire:'.$session->id, 'reward_reservation_return', $session->user_id, 'RC', $total, [
                    $this->ledger->account('task:'.$session->id, 'RC') => bcsub('0', $total, 8),
                    $this->ledger->account('company:rewards', 'RC') => $total,
                ]);
                DB::table('tasks')->where('id', $task->id)->update(['budget' => bcadd($task->budget, $total, 8), 'updated_at' => now()]);
                DB::table('task_sessions')->where('id', $session->id)->update(['status' => 'expired', 'updated_at' => now()]);

                return 1;
            }, 5);
        }

        return $count;
    }

    public function release(string $id): void
    {
        DB::transaction(function () use ($id) {
            $s = DB::table('task_sessions')->where('id', $id)->lockForUpdate()->first();
            if (! $s || $s->status !== 'verified_pending') {
                return;
            }
            $u = User::findOrFail($s->user_id);
            $this->access->check($u, 'claim', $s->channel);
            $this->ledger->post('task:credit:'.$id, 'task_reward', $u->id, 'RC', $s->reward, [
                $this->ledger->account('task:'.$id, 'RC') => bcsub('0', $s->reward, 8),
                $this->ledger->account('user:'.$u->id, 'RC', $u->id) => $s->reward,
            ]);
            DB::table('task_sessions')->where('id', $id)->update(['status' => 'credited', 'updated_at' => now()]);
            $p = json_decode($s->policy, true);
            if ($u->referrer_id && bccomp($p['commission'] ?? '0', '0', 8) > 0) {
                DB::table('referral_rewards')->insertOrIgnore(['source' => 'task:'.$id, 'user_id' => $u->referrer_id, 'referred_id' => $u->id, 'amount' => $p['commission'], 'kind' => 'commission', 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
            }
            $this->releaseReferrals();
        }, 5);
    }

    public function attribute(User $u, string $code): void
    {
        DB::transaction(function () use ($u, $code) {
            $u = User::whereKey($u->id)->lockForUpdate()->firstOrFail();
            if ($u->referrer_id || $u->created_at->lt(now()->subDays(7)) || DB::table('task_sessions')->where('user_id', $u->id)->exists()) {
                $this->access->deny('Referral attribution is no longer available.');
            }
            $parent = User::where('wallet_id', $code)->first();
            if (! $parent || $parent->id === $u->id) {
                $this->access->deny('Enter a valid referral wallet ID.');
            }
            // Only earlier accounts may refer a newcomer: prevents cycles under concurrent attribution.
            if ($parent->id >= $u->id) {
                $this->access->deny('This account cannot refer you.');
            }
            $p = $this->settings->policy();
            $u->referrer_id = $parent->id;
            $u->referral_policy = $p;
            $u->save();
            $items = [];
            if (in_array($p['referral_mode'], ['A', 'B'])) {
                $items[] = ['newcomer', $u->id, $p['newcomer_bonus']];
            }
            if ($p['referral_mode'] === 'A') {
                $items[] = ['signup', $parent->id, $p['referrer_bonus']];
            }
            foreach ($items as [$kind,$recipient,$amount]) {
                if (bccomp($amount, '0', 8) > 0) {
                    DB::table('referral_rewards')->insertOrIgnore(['source' => $kind.':'.$u->id, 'user_id' => $recipient, 'referred_id' => $u->id, 'amount' => $amount, 'kind' => $kind, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
                }
            }
        }, 5);
        $this->releaseReferrals();
    }

    public function releaseReferrals(): void
    {
        foreach (DB::table('referral_rewards')->where('status', 'pending')->limit(100)->pluck('id') as $id) {
            DB::transaction(function () use ($id) {
                $r = DB::table('referral_rewards')->where('id', $id)->lockForUpdate()->first();
                if ($r->status !== 'pending') {
                    return;
                }
                $p = $this->settings->policy();
                if (! $p['earn_global']) {
                    return;
                }
                $new = User::find($r->referred_id);
                $owner = User::find($r->user_id);
                if (! $new || ! $owner || $this->access->blocked($owner, 'earning') || $this->access->blocked($new, 'earning')) {
                    return;
                }
                if (($p['referral_kyc'] && ! $this->access->verified($new)) || ($r->kind === 'newcomer' && $p['newcomer_kyc'] && ! $this->access->verified($new)) || ($p['referrer_kyc'] && ! $this->access->verified($owner))) {
                    return;
                }
                $source = $r->kind === 'commission' ? $r->source : 'company:rewards';
                try {
                    $this->ledger->post('referral:'.$r->id, 'referral_'.$r->kind, $owner->id, 'RC', $r->amount, [
                        $this->ledger->account($source, 'RC') => bcsub('0', $r->amount, 8),
                        $this->ledger->account('user:'.$owner->id, 'RC', $owner->id) => $r->amount,
                    ], ['referred_id' => $new->id]);
                    DB::table('referral_rewards')->where('id', $id)->update(['status' => 'credited', 'updated_at' => now()]);
                } catch (ValidationException $e) { /* Insufficient funded budget stays pending. */
                }
            }, 5);
        }
    }
}
