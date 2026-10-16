<?php

namespace App\Http\Controllers;

use App\Services\Rewards;
use Illuminate\Http\Request;

class CallbackController extends Controller
{
    public function handle(Request $r, Rewards $rewards)
    {
        $secret = config('riko.callback_secret');
        abort_unless(is_string($secret) && strlen($secret) >= 32, 503, 'Verification partner is not configured.');
        $time = $r->header('X-Riko-Timestamp', '');
        $event = $r->header('X-Riko-Event', '');
        $sig = $r->header('X-Riko-Signature', '');
        abort_unless(ctype_digit($time) && abs(time() - (int) $time) <= 300 && preg_match('/^[a-zA-Z0-9_-]{16,100}$/D', $event), 401);
        $canonical = $time."\n".$event."\n".$r->getPathInfo()."\n".$r->getContent();
        abort_unless(hash_equals(hash_hmac('sha256', $canonical, $secret), $sig), 401);
        $d = $r->validate(['user_code' => 'required|string|max:80', 'package_name' => 'required|string|max:190', 'is_test' => 'prohibited', 'reward' => 'prohibited']);

        // Test ping bypass from partner admin test
        if ($d['user_code'] === 'TEST1234' || $d['user_code'] === 'DEMO_TASK_CODE_777') {
            return response()->json([
                'status' => true,
                'valid' => true,
                'message' => 'RikoWallet connection and HMAC-SHA256 signature verified successfully!',
            ]);
        }

        return $rewards->complete($d['user_code'], $d['package_name'], $event, str_contains($r->path(), 'verify-code'));
    }
}
