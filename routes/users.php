<?php

declare(strict_types=1);

use App\Http\Controllers\Users\UserController;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Route;

// Cuentas del personal: solo el médico, y confirmando su contraseña.
Route::middleware(['auth', 'verified', 'permission:users.manage', RequirePassword::class])
    ->prefix('settings/users')
    ->name('settings.users.')
    ->group(function (): void {
        Route::get('/', [UserController::class, 'index'])->name('index');
        Route::post('/', [UserController::class, 'store'])->name('store');
        Route::put('{user}', [UserController::class, 'update'])->name('update');
        Route::patch('{user}/status', [UserController::class, 'status'])->name('status');
        Route::put('{user}/password', [UserController::class, 'password'])
            ->middleware('throttle:10,1')
            ->name('password');
    });
