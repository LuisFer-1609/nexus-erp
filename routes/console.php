<?php

use App\Services\Crm\Meta\ImportMetaLeads;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('meta:sync-leads', function (ImportMetaLeads $importer) {
    $result = $importer->all();
    $this->info("Leads nuevos: {$result['imported']}; páginas con error: {$result['failed_pages']}.");

    return $result['failed_pages'] > 0 ? 1 : 0;
})->purpose('Importar formularios de clientes potenciales de páginas Meta vinculadas');

Schedule::command('meta:sync-leads')->everyFifteenMinutes()->withoutOverlapping();
// Nexus IA: mantiene los datos del asistente al día. Es incremental (solo recarga
// lo que cambió) y descubre solo los cambios de estructura de la base de datos.
// Requiere el planificador: `php artisan schedule:work` (o el cron de Laravel).
Schedule::command('nexus:ai-sync --scheduled')
    ->cron('*/'.max(1, (int) config('nexus_ai.sync.schedule_minutes')).' * * * *')
    ->withoutOverlapping(60)
    ->runInBackground();
