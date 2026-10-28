<?php

declare(strict_types=1);

use App\Http\Controllers\Clinical\Cie10SearchController;
use App\Http\Controllers\Clinical\ConsultationController;
use App\Http\Controllers\Clinical\MedicationSearchController;
use App\Http\Controllers\Clinical\PrescriptionController;
use App\Http\Controllers\Settings\ClinicSettingsController;
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

    // Recetas
    Route::middleware('permission:prescriptions.manage')->group(function (): void {
        Route::post('consultations/{consultation}/prescriptions', [PrescriptionController::class, 'store'])->name('prescriptions.store');
        Route::post('prescriptions/{prescription}/email', [PrescriptionController::class, 'email'])->name('prescriptions.email');
        Route::delete('prescriptions/{prescription}', [PrescriptionController::class, 'destroy'])->name('prescriptions.destroy');
        Route::get('catalogs/medications', MedicationSearchController::class)->name('catalogs.medications');
    });

    Route::get('prescriptions/{prescription}/pdf', [PrescriptionController::class, 'pdf'])
        ->middleware('permission:consultations.view')
        ->name('prescriptions.pdf');

    // Datos del consultorio y del médico
    Route::middleware('permission:settings.manage')->prefix('settings/clinic')->name('settings.clinic.')->group(function (): void {
        Route::get('/', [ClinicSettingsController::class, 'edit'])->name('edit');
        Route::post('/', [ClinicSettingsController::class, 'update'])->name('update');
        Route::get('image/{image}', [ClinicSettingsController::class, 'image'])->whereIn('image', ['logo', 'signature', 'seal'])->name('image');
    });
});
