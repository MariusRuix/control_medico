<?php

declare(strict_types=1);

use App\Http\Controllers\Billing\CashController;
use App\Http\Controllers\Billing\ChargeController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::prefix('cash')->name('cash.')->group(function (): void {
        Route::get('/', [CashController::class, 'index'])->middleware('permission:charges.view')->name('index');

        Route::middleware('permission:cash-register.manage')->group(function (): void {
            Route::post('open', [CashController::class, 'open'])->name('open');
            Route::get('{register}', [CashController::class, 'show'])->name('show');
            Route::post('{register}/close', [CashController::class, 'close'])->name('close');
        });
    });

    Route::middleware('permission:charges.manage')->group(function (): void {
        Route::post('charges', [ChargeController::class, 'store'])->name('charges.store');
        Route::post('charges/{charge}/payments', [ChargeController::class, 'pay'])->name('charges.pay');
        Route::post('charges/{charge}/cancel', [ChargeController::class, 'cancel'])->name('charges.cancel');
    });

    Route::delete('payments/{payment}', [ChargeController::class, 'voidPayment'])
        ->middleware('permission:cash-register.manage')
        ->name('payments.destroy');
});
