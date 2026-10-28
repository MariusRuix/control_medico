<?php

declare(strict_types=1);

use App\Http\Controllers\Agenda\AppointmentController;
use App\Http\Controllers\Agenda\ScheduleBlockController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::prefix('appointments')->name('appointments.')->group(function (): void {
        Route::get('/', [AppointmentController::class, 'index'])->middleware('permission:appointments.view')->name('index');
        Route::get('availability', [AppointmentController::class, 'availability'])->middleware('permission:appointments.view')->name('availability');
        Route::post('/', [AppointmentController::class, 'store'])->middleware('permission:appointments.manage')->name('store');
        Route::put('{appointment}', [AppointmentController::class, 'update'])->middleware('permission:appointments.manage')->name('update');
        Route::patch('{appointment}/status', [AppointmentController::class, 'status'])->middleware('permission:appointments.manage')->name('status');
    });

    Route::middleware('permission:settings.manage')->prefix('schedule-blocks')->name('schedule-blocks.')->group(function (): void {
        Route::post('/', [ScheduleBlockController::class, 'store'])->name('store');
        Route::delete('{block}', [ScheduleBlockController::class, 'destroy'])->name('destroy');
    });
});
