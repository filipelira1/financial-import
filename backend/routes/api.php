<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ImportController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::post('auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:login')
        ->name('auth.login');

    Route::middleware(['auth:sanctum', 'throttle:api'])->group(function (): void {
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::get('auth/me', [AuthController::class, 'me'])->name('auth.me');

        Route::controller(ImportController::class)->prefix('imports')->name('imports.')->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->middleware('throttle:uploads')->name('store');
            Route::get('{import}', 'show')->whereNumber('import')->name('show');
            Route::get('{import}/failures', 'failures')->whereNumber('import')->name('failures');
            Route::post('{import}/retry', 'retry')->whereNumber('import')->name('retry');
        });
    });
});
