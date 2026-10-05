<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CallbackController;
use App\Http\Controllers\WalletController;
use Illuminate\Support\Facades\Route;
Route::post('/cron/run', [\App\Http\Controllers\CronController::class, 'run'])->middleware('throttle:10,1');

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1');
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:3,1');
Route::middleware(['api.session', 'throttle:60,1'])->group(function () {
    Route::get('/wallet', [WalletController::class, 'snapshot']);
    Route::post('/actions/{action}', [WalletController::class, 'action']);
    Route::post('/logout', [AuthController::class, 'logout']);
});
Route::post('/earn/verify', [CallbackController::class, 'handle'])->middleware('throttle:120,1');
Route::post('/earn/verify-code', [CallbackController::class, 'handle'])->middleware('throttle:120,1');
