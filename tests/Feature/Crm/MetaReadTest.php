<?php

use App\Models\Nexus\MetaConnection;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

test('authenticated users can list Meta ad accounts', function () {
    config()->set('services.meta.url', 'https://graph.facebook.com');
    config()->set('services.meta.version', 'v27.0');
    $companyId = DB::table('companies')->insertGetId(['name' => 'Empresa de prueba']);
    $roleId = DB::table('roles')->insertGetId(['name' => 'Administrador', 'created_at' => now()]);
    $user = User::factory()->create(['company_id' => $companyId, 'role_id' => $roleId]);
    MetaConnection::query()->create([
        'company_id' => $companyId,
        'auth_user_id' => $user->id,
        'business_id' => null,
        'access_token' => 'test-token',
        'token_fingerprint' => hash('sha256', 'test-token'),
    ]);

    Http::fake([
        'https://graph.facebook.com/v27.0/me/adaccounts*' => Http::response([
            'data' => [[
                'id' => 'act_123456789',
                'account_id' => '123456789',
                'name' => 'Cuenta de prueba',
                'account_status' => 1,
                'currency' => 'MXN',
                'timezone_name' => 'America/Mexico_City',
            ]],
        ]),
    ]);

    $response = $this
        ->actingAs($user)
        ->getJson(route('crm.meta.ad-accounts.index'));

    $response->assertOk()->assertExactJson([
        'data' => [[
            'id' => '123456789',
            'graph_id' => 'act_123456789',
            'name' => 'Cuenta de prueba',
            'status' => 1,
            'currency' => 'MXN',
            'timezone' => 'America/Mexico_City',
            'linked' => false,
        ]],
    ]);

    Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://graph.facebook.com/v27.0/me/adaccounts?')
        && $request->hasHeader('Authorization', 'Bearer test-token')
    );
});
