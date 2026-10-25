<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\AuthenticatorController;
use App\Http\Controllers\ManagementController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
Route::post('/auth/verify', [AuthController::class, 'verify'])->middleware('throttle:10,1');
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', fn (Request $r) => $r->user());
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/auth/authenticator', [AuthenticatorController::class, 'status']);
    Route::middleware('throttle:10,1')->group(function () {
        Route::post('/auth/authenticator/setup', [AuthenticatorController::class, 'setup']);
        Route::post('/auth/authenticator/confirm', [AuthenticatorController::class, 'confirm']);
        Route::delete('/auth/authenticator', [AuthenticatorController::class, 'disable']);
    });
    Route::prefix('{resource}')->where(['resource' => 'usuarios|productos|contenidos'])->group(function () {
        Route::get('/', [ManagementController::class, 'index']);
        Route::post('/', [ManagementController::class, 'store']);
        Route::get('/{id}', [ManagementController::class, 'show'])->whereNumber('id');
        Route::put('/{id}', [ManagementController::class, 'update'])->whereNumber('id');
        Route::delete('/{id}', [ManagementController::class, 'destroy'])->whereNumber('id');
    });
});
