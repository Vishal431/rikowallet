<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Access;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $r)
    {
        $d = $r->validate(['email' => 'required|email', 'password' => 'required|string']);
        $u = User::where('email', strtolower($d['email']))->first();
        if (! $u || ! Hash::check($d['password'], $u->password)) {
            throw ValidationException::withMessages(['email' => 'Email or password is incorrect.']);
        }
        if (app(Access::class)->blocked($u, 'login')) {
            throw ValidationException::withMessages(['email' => 'Account access is restricted. Contact support.']);
        }

        return $this->signedIn($r, $u);
    }

    public function register(Request $r)
    {
        $d = $r->validate(['name' => 'required|string|max:60', 'email' => 'required|email|max:190|unique:users', 'password' => ['required', 'confirmed', Password::min(12)->letters()->numbers()], 'country' => 'required|in:'.implode(',', array_keys(config('riko.countries')))]);
        $u = DB::transaction(function () use ($d) {
            $u = new User;
            $u->name = $d['name'];
            $u->email = strtolower($d['email']);
            $u->password = $d['password'];
            $u->country = $d['country'];
            $u->wallet_id = (string) random_int(1000000000, 9999999999);
            $u->save();

            return $u;
        });

        return $this->signedIn($r, $u, true);
    }

    private function signedIn(Request $r, User $u, bool $new = false)
    {
        if ($r->is('api/*')) {
            $token = Str::random(64);
            DB::table('api_tokens')->insert(['user_id' => $u->id, 'hash' => hash('sha256', $token), 'channel' => 'app', 'expires_at' => now()->addHours(12), 'created_at' => now(), 'updated_at' => now()]);

            return response()->json(['token' => $token, 'new_user' => $new]);
        }
        Auth::login($u);
        $r->session()->regenerate();

        return redirect($new ? '/app/referrals' : '/app');
    }

    public function logout(Request $r)
    {
        if ($r->is('api/*')) {
            DB::table('api_tokens')->where('hash', hash('sha256', $r->bearerToken() ?? ''))->delete();

            return response()->json(['ok' => true]);
        }
        Auth::logout();
        $r->session()->invalidate();
        $r->session()->regenerateToken();

        return redirect('/login');
    }
}
