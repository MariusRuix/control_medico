<?php

declare(strict_types=1);

use App\Http\Controllers\Reports\FinancialReportController;
use Illuminate\Support\Facades\Route;

// Reportes financieros: solo el médico.
Route::middleware(['auth', 'verified', 'permission:financial-reports.view'])
    ->prefix('reports')
    ->name('reports.')
    ->group(function (): void {
        Route::get('finance', [FinancialReportController::class, 'index'])->name('finance');
        Route::get('finance/export', [FinancialReportController::class, 'export'])
            ->middleware('throttle:20,1')
            ->name('finance.export');
    });
