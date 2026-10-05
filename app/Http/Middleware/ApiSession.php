<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ApiSession
{
    public function handle(Request $r, Closure $next)
    {
        $token = $r->bearerToken();
        abort_unless($token, 401);
        $row = DB::table('api_tokens')->where('hash', hash('sha256', $token))->where('expires_at', '>', now())->first();
        abort_unless($row, 401);
        $u = User::findOrFail($row->user_id);
        $r->setUserResolver(fn () => $u);
        $r->attributes->set('channel', $row->channel);

        return $next($r);
    }
}
