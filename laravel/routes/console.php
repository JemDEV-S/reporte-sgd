<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// La frecuencia y la pausa se resuelven desde PostgreSQL en cada comprobación.
Schedule::command('sgd:planificar')
    ->everyMinute()
    ->withoutOverlapping(2)
    ->appendOutputTo(storage_path('logs/ingesta-sgd.log'));
