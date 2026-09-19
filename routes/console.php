<?php

use App\Services\BackupService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

$backup = app(BackupService::class);
$cron = $backup->getScheduleCron();
Schedule::command('backup:generate --all')
    ->cron($cron)
    ->description('Generar backup semanal de todas las empresas');
