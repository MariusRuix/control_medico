<?php

declare(strict_types=1);

use App\Http\Controllers\Patients\PatientController;
use App\Http\Controllers\Patients\PatientHistoryController;
use App\Http\Controllers\Patients\PatientSearchController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('patients')->name('patients.')->group(function (): void {
    Route::get('/', [PatientController::class, 'index'])->middleware('permission:patients.view')->name('index');
    Route::get('search', PatientSearchController::class)->middleware('permission:patients.view')->name('search');
    Route::get('create', [PatientController::class, 'create'])->middleware('permission:patients.manage')->name('create');
    Route::post('/', [PatientController::class, 'store'])->middleware('permission:patients.manage')->name('store');
    Route::get('{patient}', [PatientController::class, 'show'])->middleware('permission:patients.view')->name('show');
    Route::get('{patient}/edit', [PatientController::class, 'edit'])->middleware('permission:patients.manage')->name('edit');
    Route::put('{patient}', [PatientController::class, 'update'])->middleware('permission:patients.manage')->name('update');
    Route::delete('{patient}', [PatientController::class, 'destroy'])->middleware('permission:patients.delete')->name('destroy');

    Route::middleware('permission:patient-histories.manage')->scopeBindings()->group(function (): void {
        Route::post('{patient}/histories', [PatientHistoryController::class, 'store'])->name('histories.store');
        Route::put('{patient}/histories/{history}', [PatientHistoryController::class, 'update'])->name('histories.update');
        Route::delete('{patient}/histories/{history}', [PatientHistoryController::class, 'destroy'])->name('histories.destroy');
    });
});
