<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Nexus IA: mantiene los datos del asistente al día. Es incremental (solo recarga
// lo que cambió) y descubre solo los cambios de estructura de la base de datos.
// Requiere el planificador: `php artisan schedule:work` (o el cron de Laravel).
Schedule::command('nexus:ai-sync --scheduled')
    ->cron('*/'.max(1, (int) config('nexus_ai.sync.schedule_minutes')).' * * * *')
    ->withoutOverlapping(60)
    ->runInBackground();
