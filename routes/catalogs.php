<?php

declare(strict_types=1);

use App\Http\Controllers\Catalogs\Cie10Controller;
use App\Http\Controllers\Catalogs\MedicationController;
use App\Http\Controllers\Catalogs\NoteTemplateController;
use App\Http\Controllers\Catalogs\ServiceController;
use App\Http\Controllers\Settings\ScheduleSettingsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::middleware('permission:catalogs.manage')->prefix('catalogs')->name('catalogs.')->group(function (): void {
        Route::redirect('/', '/catalogs/vademecum')->name('index');

        Route::get('vademecum', [MedicationController::class, 'index'])->name('vademecum.index');
        Route::post('vademecum', [MedicationController::class, 'store'])->name('vademecum.store');
        Route::put('vademecum/{medication}', [MedicationController::class, 'update'])->name('vademecum.update');
        Route::patch('vademecum/{medication}/favorite', [MedicationController::class, 'favorite'])->name('vademecum.favorite');

        Route::get('services', [ServiceController::class, 'index'])->name('services.index');
        Route::post('services', [ServiceController::class, 'store'])->name('services.store');
        Route::put('services/{service}', [ServiceController::class, 'update'])->name('services.update');

        Route::get('templates', [NoteTemplateController::class, 'index'])->name('templates.index');
        Route::post('templates', [NoteTemplateController::class, 'store'])->name('templates.store');
        Route::put('templates/{template}', [NoteTemplateController::class, 'update'])->name('templates.update');
        Route::delete('templates/{template}', [NoteTemplateController::class, 'destroy'])->name('templates.destroy');

        Route::get('diagnoses', [Cie10Controller::class, 'index'])->name('diagnoses.index');
        Route::post('diagnoses/import', [Cie10Controller::class, 'import'])->name('diagnoses.import');
        Route::patch('diagnoses/{code}/toggle', [Cie10Controller::class, 'toggle'])->name('diagnoses.toggle');
    });

    Route::middleware('permission:settings.manage')->group(function (): void {
        Route::get('settings/schedule', [ScheduleSettingsController::class, 'edit'])->name('settings.schedule.edit');
        Route::put('settings/schedule', [ScheduleSettingsController::class, 'update'])->name('settings.schedule.update');
    });
});
