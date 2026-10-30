<?php

use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
require __DIR__.'/patients.php';
require __DIR__.'/agenda.php';
require __DIR__.'/clinical.php';
require __DIR__.'/medical-files.php';
require __DIR__.'/billing.php';
