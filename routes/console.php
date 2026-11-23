<?php

use App\Models\Nexus\MetaConnection;
use App\Services\Crm\Meta\ImportMetaLeads;
use App\Services\Crm\Meta\MetaReadService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
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

Artisan::command('meta:connect-company {companyId}', function (MetaReadService $meta) {
    $companyId = (int) $this->argument('companyId');
    $token = (string) config('services.meta.access_token');

    if (! DB::table('companies')->where('id', $companyId)->where('is_active', true)->exists() || $token === '') {
        throw new RuntimeException('La empresa activa o META_ACCESS_TOKEN no están configurados.');
    }

    $fingerprint = hash('sha256', $token);
    if (MetaConnection::query()->where('token_fingerprint', $fingerprint)->where('company_id', '!=', $companyId)->exists()) {
        throw new RuntimeException('Este token ya está asignado a otra empresa.');
    }

    $meta->adAccounts($token);
    MetaConnection::query()->updateOrCreate(
        ['company_id' => $companyId],
        ['access_token' => $token, 'token_fingerprint' => $fingerprint],
    );
    $this->info('Token de Meta asignado a la empresa.');
})->purpose('Asignar el token META_ACCESS_TOKEN a una empresa concreta');
