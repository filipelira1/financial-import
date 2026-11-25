<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\ImportController;
use App\Http\Controllers\Api\V1\TransactionController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::post('auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:login')
        ->name('auth.login');

    Route::middleware(['auth:sanctum', 'throttle:api'])->group(function (): void {
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::get('auth/me', [AuthController::class, 'me'])->name('auth.me');

        Route::get('dashboard/summary', [DashboardController::class, 'summary'])->name('dashboard.summary');
        Route::get('dashboard/monthly', [DashboardController::class, 'monthly'])->name('dashboard.monthly');
        Route::get('transactions', [TransactionController::class, 'index'])->name('transactions.index');
        Route::get('transactions/export', [TransactionController::class, 'export'])->name('transactions.export');

        Route::controller(ImportController::class)->prefix('imports')->name('imports.')->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->middleware('throttle:uploads')->name('store');
            Route::get('{import}', 'show')->whereNumber('import')->name('show');
            Route::get('{import}/failures', 'failures')->whereNumber('import')->name('failures');
            Route::get('{import}/failures/export', 'exportFailures')->whereNumber('import')->name('failures.export');
            Route::post('{import}/retry', 'retry')->whereNumber('import')->name('retry');
        });
    });
});
