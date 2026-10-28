<?php

declare(strict_types=1);

use App\Http\Controllers\Clinical\Cie10SearchController;
use App\Http\Controllers\Clinical\ConsultationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::middleware('permission:consultations.manage')->group(function (): void {
        Route::post('appointments/{appointment}/consultation', [ConsultationController::class, 'startFromAppointment'])
            ->name('consultations.start-from-appointment');
        Route::post('patients/{patient}/consultations', [ConsultationController::class, 'start'])
            ->name('consultations.start');

        Route::get('consultations/{consultation}/edit', [ConsultationController::class, 'edit'])->name('consultations.edit');
        Route::put('consultations/{consultation}', [ConsultationController::class, 'update'])->name('consultations.update');
        Route::post('consultations/{consultation}/sign', [ConsultationController::class, 'sign'])->name('consultations.sign');
        Route::delete('consultations/{consultation}', [ConsultationController::class, 'destroy'])->name('consultations.destroy');

        Route::get('catalogs/cie10', Cie10SearchController::class)->name('catalogs.cie10');
    });

    Route::get('consultations/{consultation}', [ConsultationController::class, 'show'])
        ->middleware('permission:consultations.view')
        ->name('consultations.show');
});
