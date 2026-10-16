<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CallbackController;
use App\Http\Controllers\WalletController;
use App\Models\User;
use App\Services\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::post('/earn/verify', [CallbackController::class, 'handle'])->middleware('throttle:120,1');
Route::post('/earn/verify.php', [CallbackController::class, 'handle'])->middleware('throttle:120,1');
Route::post('/earn/verify-code', [CallbackController::class, 'handle'])->middleware('throttle:120,1');
Route::post('/earn/verify-code.php', [CallbackController::class, 'handle'])->middleware('throttle:120,1');

Route::get('/', fn () => view('landing'));
Route::get('/method-image/{file}', function (string $file) {
    abort_unless(preg_match('/^[a-f0-9-]{36}\.(png|jpg|jpeg|webp)$/D', $file), 404);
    $path = \Illuminate\Support\Facades\Storage::disk('local')->path('method-images/'.$file);
    abort_unless(is_file($path), 404);

    return response()->file($path, ['Cache-Control' => 'public, max-age=31536000, immutable']);
});
Route::middleware('guest')->group(function () {
    Route::get('/login', fn () => view('auth', ['mode' => 'login']))->name('login');
    Route::get('/register', fn () => view('auth', ['mode' => 'register']));
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:3,1');
});
Route::middleware('auth')->group(function () {
    Route::get('/pay/{id}', function (Request $r, string $id) {
        $record = DB::table('records')->where('id', $id)->whereIn('kind', ['link', 'invoice', 'request'])->first();
        abort_unless($record, 404);
        $details = json_decode($record->data, true);
        if ($record->kind === 'request' && $details['wallet_id'] !== $r->user()->wallet_id && $record->user_id !== $r->user()->id) {
            abort(403);
        }

        return view('checkout', ['record' => $record, 'details' => $details, 'merchant' => User::findOrFail($record->user_id)]);
    });
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/app/{page?}', function (Request $r, Wallet $wallet, string $page = 'home') {
        abort_unless(in_array($page, ['home', 'pay', 'receive', 'requests', 'deposit', 'withdraw', 'swap', 'cards', 'earn', 'referrals', 'contest', 'profile', 'support', 'activity', 'business', 'invoices', 'links', 'subscriptions', 'kyc']), 404);
        $data = $wallet->snapshot($r->user(), 'web');
        if (in_array($page, ['earn', 'referrals', 'contest']) && ! $data['earning_enabled']) {
            abort(404);
        }
        if ($page === 'contest' && ! $data['policy']['contest']) {
            abort(404);
        }

        return view('app', ['page' => $page, 'data' => $data]);
    });
    Route::post('/actions/{action}', [WalletController::class, 'action'])->middleware('throttle:30,1');
    Route::get('/admin/{page?}', [AdminController::class, 'index']);
    Route::post('/admin/actions/{action}', [AdminController::class, 'save'])->middleware('throttle:20,1');
    Route::get('/admin-document/{id}', [AdminController::class, 'document']);
});
