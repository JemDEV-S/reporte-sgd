<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Mantiene fresca la publicación vigente aplicando los cambios del SGD desde la
// última marca de agua. Coincide con el solapamiento de 15 min de la ingesta.
// Requiere que el entorno ejecute `php artisan schedule:run` cada minuto
// (Programador de tareas en Windows o cron en Linux).
Schedule::command('sgd:ingestar --incremental')
    ->everyFifteenMinutes()
    ->withoutOverlapping(20)
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/ingesta-sgd.log'))
    ->onFailure(function () {
        logger()->error('La ingesta incremental programada del SGD falló; la última publicación vigente permanece.');
    });
