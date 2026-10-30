<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Recordatorios de citas. Requiere el programador de tareas de Laravel:
// en Windows/Laragon, `php artisan schedule:work` o una tarea programada
// que ejecute `php artisan schedule:run` cada minuto.
Schedule::command('appointments:send-reminders')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->onOneServer();
