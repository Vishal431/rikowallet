<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CronController extends Controller
{
    public function run(Request $r)
    {
        $secret = config('riko.cron_secret');
        abort_unless(is_string($secret) && strlen($secret) >= 32, 503, 'Protected cron is not configured.');
        $time = $r->header('X-Riko-Timestamp', '');
        $nonce = $r->header('X-Riko-Nonce', '');
        abort_unless(ctype_digit($time) && abs(time() - (int) $time) < 120 && preg_match('/^[a-zA-Z0-9_-]{20,80}$/D', $nonce), 401);
        $expected = hash_hmac('sha256', $time."\n".$nonce."\n".$r->getContent(), $secret);
        abort_unless(hash_equals($expected, $r->header('X-Riko-Signature', '')), 401);
        abort_unless(Cache::add('cron-nonce:'.$nonce, true, 300), 409, 'Cron request already received.');
        $d = $r->validate(['job' => 'required|in:stats,rewards,contest']);
        $lock = Cache::lock('cron-run:'.$d['job'], 300);
        abort_unless($lock->get(), 409, 'Job already running.');
        try {
            $code = Artisan::call('riko:'.$d['job']);
            DB::table('audit_logs')->insert(['action' => 'cron:'.$d['job'], 'target' => $code === 0 ? 'success' : 'failed', 'created_at' => now()]);

            return ['ok' => $code === 0];
        } finally {
            $lock->release();
        }
    }
}
