<?php

declare(strict_types=1);

use App\Http\Controllers\Settings\BackupController;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Route;

// Respaldos: solo el médico, confirmando su contraseña (contienen todo el expediente).
Route::middleware(['auth', 'verified', 'permission:settings.manage', RequirePassword::class])
    ->prefix('settings/backups')
    ->name('settings.backups.')
    ->group(function (): void {
        Route::get('/', [BackupController::class, 'index'])->name('index');
        Route::post('/', [BackupController::class, 'store'])->middleware('throttle:3,10')->name('store');
        Route::get('{name}', [BackupController::class, 'download'])
            ->where('name', 'respaldo-control-medico-[0-9-]+\.zip')
            ->name('download');
    });
