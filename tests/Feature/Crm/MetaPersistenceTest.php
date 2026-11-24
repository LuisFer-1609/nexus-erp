<?php

use App\Models\Nexus\MetaConnection;
use App\Models\User;
use App\Services\Crm\Meta\ImportMetaLeads;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

function metaCompanyUser(): array
{
    $companyId = DB::table('companies')->insertGetId(['name' => 'Acme']);
    $roleId = DB::table('roles')->insertGetId(['name' => 'Administrador', 'created_at' => now()]);
    $user = User::factory()->create(['company_id' => $companyId, 'role_id' => $roleId]);
    $connection = MetaConnection::query()->create([
        'company_id' => $companyId,
        'auth_user_id' => $user->id,
        'business_id' => '555',
        'access_token' => 'test-token',
        'token_fingerprint' => hash('sha256', 'test-token'),
    ]);

    return [$companyId, $user, $connection->id];
}

test('saving visible campaigns inserts once and only updates changed metrics', function () {
    [$companyId, $user, $connectionId] = metaCompanyUser();
    DB::table('meta_ad_accounts')->insert([
        'company_id' => $companyId,
        'meta_connection_id' => $connectionId,
        'external_id' => '123',
        'name' => 'Cuenta',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $spend = '10.00';
    Http::fake(function ($request) use (&$spend) {
        if (str_contains($request->url(), '/555/owned_ad_accounts')) {
            return Http::response(['data' => [[
                'id' => 'act_123', 'account_id' => '123', 'name' => 'Cuenta',
                'account_status' => 1, 'currency' => 'MXN', 'timezone_name' => 'America/Mexico_City',
            ]]]);
        }
        if (str_contains($request->url(), '/555/client_ad_accounts')) {
            return Http::response(['data' => []]);
        }
        if (str_contains($request->url(), '/act_123/campaigns')) {
            return Http::response(['data' => [
                ['id' => '456', 'account_id' => '123', 'name' => 'Campaña A', 'status' => 'ACTIVE'],
                ['id' => '789', 'account_id' => '123', 'name' => 'Campaña B', 'status' => 'PAUSED'],
            ]]);
        }

        return Http::response(['data' => [['campaign_id' => '456', 'spend' => $spend]]]);
    });

    $payload = [
        'account_id' => '123', 'since' => '2026-11-01', 'until' => '2026-11-20',
        'campaign_ids' => ['456', '789'],
    ];

    $this->actingAs($user)->post('/crm/meta/campaigns/save', $payload)->assertSessionHasNoErrors();
    expect(DB::table('meta_saved_campaigns')->count())->toBe(2);
    $firstUpdatedAt = DB::table('meta_saved_campaigns')->where('meta_campaign_id', '456')->value('updated_at');

    $this->actingAs($user)->post('/crm/meta/campaigns/save', $payload)->assertSessionHasNoErrors();
    expect(DB::table('meta_saved_campaigns')->count())->toBe(2)
        ->and(DB::table('meta_saved_campaigns')->where('meta_campaign_id', '456')->value('updated_at'))->toBe($firstUpdatedAt);

    $spend = '20.00';
    $this->actingAs($user)->post('/crm/meta/campaigns/save', $payload)->assertSessionHasNoErrors();
    $metrics = json_decode(DB::table('meta_saved_campaigns')->where('meta_campaign_id', '456')->value('metrics'), true);
    expect($metrics['spend'])->toBe('20.00');
});

test('lead import is idempotent and preserves CRM edits', function () {
    [$companyId, , $connectionId] = metaCompanyUser();
    DB::table('meta_pages')->insert([
        'company_id' => $companyId, 'meta_connection_id' => $connectionId, 'external_id' => '555', 'name' => 'Página',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    Http::fake([
        '*/555/leadgen_forms*' => Http::response(['data' => [['id' => '777', 'name' => 'Formulario']]]),
        '*/777/leads*' => Http::response(['data' => [[
            'id' => '999', 'form_id' => '777', 'campaign_id' => '456',
            'field_data' => [
                ['name' => 'full_name', 'values' => ['Ana Pérez']],
                ['name' => 'email', 'values' => ['ana@example.test']],
            ],
        ]]]),
    ]);

    $importer = app(ImportMetaLeads::class);
    expect($importer->company($companyId)['imported'])->toBe(1);
    DB::table('leads')->where('meta_lead_id', '999')->update(['priority' => 'alta']);
    expect($importer->company($companyId)['imported'])->toBe(0)
        ->and(DB::table('leads')->where('meta_lead_id', '999')->value('priority'))->toBe('alta')
        ->and(DB::table('leads')->where('meta_lead_id', '999')->value('company_id'))->toBe($companyId);
});

test('a user from another company cannot save campaigns from a linked account', function () {
    [$companyId, , $connectionId] = metaCompanyUser();
    DB::table('meta_ad_accounts')->insert([
        'company_id' => $companyId, 'meta_connection_id' => $connectionId, 'external_id' => '123', 'name' => 'Cuenta',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $otherCompanyId = DB::table('companies')->insertGetId(['name' => 'Otra empresa']);
    $roleId = DB::table('roles')->where('name', 'Administrador')->value('id');
    $otherUser = User::factory()->create(['company_id' => $otherCompanyId, 'role_id' => $roleId]);

    $this->actingAs($otherUser)->post('/crm/meta/campaigns/save', [
        'account_id' => '123', 'since' => '2026-11-01', 'until' => '2026-11-20',
        'campaign_ids' => ['456'],
    ])->assertForbidden();

    expect(DB::table('meta_saved_campaigns')->count())->toBe(0);
});

test('campaigns remain visible if only the metrics request fails', function () {
    [$companyId, $user, $connectionId] = metaCompanyUser();
    DB::table('meta_ad_accounts')->insert([
        'company_id' => $companyId, 'meta_connection_id' => $connectionId,
        'external_id' => '123', 'name' => 'Cuenta', 'created_at' => now(), 'updated_at' => now(),
    ]);
    Http::fake([
        '*/act_123/campaigns*' => Http::response(['data' => [['id' => '456', 'name' => 'Campaña A', 'status' => 'ACTIVE']]]),
        '*/act_123/insights*' => Http::response(['error' => ['message' => 'Sin métricas']], 500),
    ]);

    $this->actingAs($user)->getJson('/crm/meta/campaigns?account_id=123&since=2026-11-01&until=2026-11-23')
        ->assertOk()->assertJsonPath('data.0.name', 'Campaña A')
        ->assertJsonPath('metrics_error', true);
});
