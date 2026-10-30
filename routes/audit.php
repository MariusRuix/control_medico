<?php

declare(strict_types=1);

use App\Http\Controllers\Audit\AuditLogController;
use Illuminate\Support\Facades\Route;

// Bitácora de accesos y cambios (solo lectura): NOM-004 / NOM-024.
Route::middleware(['auth', 'verified', 'permission:audit-logs.view'])
    ->prefix('audit-logs')
    ->name('audit-logs.')
    ->group(function (): void {
        Route::get('/', [AuditLogController::class, 'index'])->name('index');
        Route::get('export', [AuditLogController::class, 'export'])
            ->middleware('throttle:10,1')
            ->name('export');
    });
