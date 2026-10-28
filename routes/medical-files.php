<?php

declare(strict_types=1);

use App\Http\Controllers\MedicalFiles\MedicalFileController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::post('patients/{patient}/files', [MedicalFileController::class, 'store'])
        ->middleware(['permission:medical-files.upload', 'throttle:30,1'])
        ->name('medical-files.store');

    Route::get('files/{file}', [MedicalFileController::class, 'show'])
        ->middleware('permission:medical-files.view')
        ->name('medical-files.show');

    Route::delete('files/{file}', [MedicalFileController::class, 'destroy'])
        ->middleware('permission:medical-files.delete')
        ->name('medical-files.destroy');
});
