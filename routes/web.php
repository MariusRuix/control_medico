<?php

declare(strict_types=1);

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

// Sistema interno: la raíz lleva directo a Inicio (o al login si no hay sesión).
Route::redirect('/', '/dashboard')->name('home');

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
});

require __DIR__.'/settings.php';
require __DIR__.'/patients.php';
require __DIR__.'/agenda.php';
require __DIR__.'/clinical.php';
require __DIR__.'/medical-files.php';
require __DIR__.'/billing.php';
require __DIR__.'/catalogs.php';
require __DIR__.'/users.php';
require __DIR__.'/audit.php';
