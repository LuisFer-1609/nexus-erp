<?php

use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

test('authenticated users can list Meta ad accounts', function () {
    config()->set('services.meta.url', 'https://graph.facebook.com');
    config()->set('services.meta.version', 'v27.0');
    config()->set('services.meta.access_token', 'test-token');

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
        ->actingAs(User::factory()->create())
        ->getJson(route('crm.meta.ad-accounts.index'));

    $response->assertOk()->assertExactJson([
        'data' => [[
            'id' => '123456789',
            'graph_id' => 'act_123456789',
            'name' => 'Cuenta de prueba',
            'status' => 1,
            'currency' => 'MXN',
            'timezone' => 'America/Mexico_City',
        ]],
    ]);

    Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://graph.facebook.com/v27.0/me/adaccounts?')
        && $request->hasHeader('Authorization', 'Bearer test-token')
    );
});
