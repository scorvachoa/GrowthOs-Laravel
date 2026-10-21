<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Tareas programadas: requieren el scheduler en el servidor
 * (`php artisan schedule:run` cada minuto).
 *
 * Cron (Linux):
 *   * * * * * cd /ruta/al/growthos && php artisan schedule:run >> /dev/null 2>&1
 *
 * Windows (Programador de tareas, cada 1 minuto):
 *   C:\ruta\php\php.exe C:\ruta\al\growthos\artisan schedule:run
 */
Schedule::command('ideas:generate')
    ->weeklyOn(1, '06:00')
    ->description('Generar ideas semanales con IA para todos los canales');

Schedule::command('tasks:publish-scheduled')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->description('Auto-publicar tareas Programadas cuando su video ya esté en YouTube');
