<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Verificación y recordatorios de pasos vencidos cada hora
Schedule::command('sop:check-overdue-steps')->hourly();

// Procesamiento de colas en entorno sin demonios (Hostinger compartido)
Schedule::command('queue:work --stop-when-empty --max-time=50')
    ->everyMinute()
    ->withoutOverlapping();

