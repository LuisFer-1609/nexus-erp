<?php

use App\Models\Nexus\MetaConnection;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

function metaAdministrator(string $companyName): User
{
    $companyId = DB::table('companies')->insertGetId(['name' => $companyName]);
    $roleId = DB::table('roles')->where('name', 'Administrador')->value('id')
        ?? DB::table('roles')->insertGetId(['name' => 'Administrador', 'created_at' => now()]);

    return User::factory()->create(['company_id' => $companyId, 'role_id' => $roleId]);
}

test('a company administrator connects through OAuth and reads campaigns from the selected business', function () {
    config()->set('services.meta.app_id', 'app-123');
    config()->set('services.meta.app_secret', 'test-secret');
    config()->set('services.meta.redirect_uri', 'http://localhost:8000/crm/meta/oauth/callback');
    $admin = metaAdministrator('Invérea');

    Http::fake(function (Request $request) {
        $url = $request->url();
        if (str_contains($url, '/oauth/access_token')) {
            return Http::response(str_contains($url, 'fb_exchange_token=')
                ? ['access_token' => 'long-token', 'expires_in' => 5184000]
                : ['access_token' => 'short-token']);
        }
        if (str_contains($url, '/me/businesses')) {
            return Http::response(['data' => [['id' => '555', 'name' => 'Negocio Invérea']]]);
        }
        if (str_contains($url, '/me?')) {
            return Http::response(['id' => '999']);
        }
        if (str_contains($url, '/555/owned_ad_accounts')) {
            return Http::response(['data' => [[
                'id' => 'act_123', 'account_id' => '123', 'name' => 'Anuncios', 'account_status' => 1,
                'currency' => 'MXN', 'timezone_name' => 'America/Mexico_City',
            ]]]);
        }
        if (str_contains($url, '/555/client_ad_accounts')) {
            return Http::response(['data' => []]);
        }
        if (str_contains($url, '/act_123/campaigns')) {
            return Http::response(['data' => [[
                'id' => '456', 'name' => 'Campaña real', 'status' => 'ACTIVE',
            ]]]);
        }
        if (str_contains($url, '/act_123/insights')) {
            return Http::response(['data' => [[
                'campaign_id' => '456', 'spend' => '12.00', 'clicks' => '7',
            ]]]);
        }

        return Http::response(['data' => []]);
    });

    $redirect = $this->actingAs($admin)->get('/crm/meta/oauth/redirect');
    $redirect->assertRedirect();
    $url = $redirect->headers->get('Location');
    parse_str(parse_url($url, PHP_URL_QUERY), $query);
    expect($query['client_id'])->toBe('app-123')
        ->and($query['redirect_uri'])->toBe('http://localhost:8000/crm/meta/oauth/callback')
        ->and($query['scope'])->toContain('ads_read');

    $this->actingAs($admin)->get('/crm/meta/oauth/callback?'.http_build_query([
        'state' => $query['state'], 'code' => 'valid-code',
    ]))->assertRedirect(route('crm.campanas'))->assertSessionHasNoErrors();

    $connection = MetaConnection::query()->where('auth_user_id', $admin->id)->firstOrFail();
    expect($connection->company_id)->toBe($admin->company_id)
        ->and($connection->access_token)->toBe('long-token')
        ->and(DB::table('meta_connections')->value('access_token'))->not->toBe('long-token');

    $this->actingAs($admin)->post('/crm/meta/business', ['business_id' => '555'])
        ->assertRedirect(route('crm.campanas'))->assertSessionHasNoErrors();
    expect(DB::table('meta_ad_accounts')->where('external_id', '123')->value('company_id'))->toBe($admin->company_id);

    $this->actingAs($admin)->getJson('/crm/meta/campaigns?account_id=123&since=2026-11-01&until=2026-11-23')
        ->assertOk()->assertJsonPath('data.0.name', 'Campaña real')
        ->assertJsonPath('data.0.metrics.spend', '12.00');

    Http::assertSent(fn (Request $request) => str_contains($request->url(), '/act_123/campaigns')
        && $request->hasHeader('Authorization', 'Bearer long-token'));
});

test('OAuth state cannot be reused and another company cannot read linked campaigns', function () {
    config()->set('services.meta.app_id', 'app-123');
    config()->set('services.meta.app_secret', 'test-secret');
    $first = metaAdministrator('Empresa A');
    $second = metaAdministrator('Empresa B');
    $redirect = $this->actingAs($first)->get('/crm/meta/oauth/redirect');
    parse_str(parse_url($redirect->headers->get('Location'), PHP_URL_QUERY), $query);

    $this->actingAs($second)->get('/crm/meta/oauth/callback?state='.$query['state'].'&code=code')->assertForbidden();
    expect(MetaConnection::query()->count())->toBe(0);

    $this->actingAs($first)->get('/crm/meta/oauth/callback?state='.$query['state'].'&code=code')->assertForbidden();
});

test('two administrators of one company can maintain separate Meta connections', function () {
    config()->set('services.meta.app_id', 'app-123');
    config()->set('services.meta.app_secret', 'test-secret');
    $first = metaAdministrator('Empresa compartida');
    $second = User::factory()->create(['company_id' => $first->company_id, 'role_id' => $first->role_id]);

    Http::fake(function (Request $request) {
        $url = $request->url();
        if (str_contains($url, '/oauth/access_token')) {
            if (str_contains($url, 'fb_exchange_token=')) {
                return Http::response(['access_token' => str_contains($url, 'short-one') ? 'long-one' : 'long-two', 'expires_in' => 5184000]);
            }

            return Http::response(['access_token' => str_contains($url, 'code=one') ? 'short-one' : 'short-two']);
        }
        if (str_contains($url, '/me/businesses')) {
            return Http::response(['data' => [['id' => '555', 'name' => 'Negocio']]]);
        }
        if (str_contains($url, '/me?')) {
            return Http::response(['id' => '999']);
        }

        return Http::response(['data' => []]);
    });

    foreach ([[$first, 'one'], [$second, 'two']] as [$admin, $code]) {
        $redirect = $this->actingAs($admin)->get('/crm/meta/oauth/redirect');
        parse_str(parse_url($redirect->headers->get('Location'), PHP_URL_QUERY), $query);
        $this->actingAs($admin)->get('/crm/meta/oauth/callback?'.http_build_query([
            'state' => $query['state'], 'code' => $code,
        ]))->assertRedirect(route('crm.campanas'))->assertSessionHasNoErrors();
    }

    expect(MetaConnection::query()->where('company_id', $first->company_id)->count())->toBe(2)
        ->and(MetaConnection::query()->where('auth_user_id', $first->id)->firstOrFail()->access_token)->toBe('long-one')
        ->and(MetaConnection::query()->where('auth_user_id', $second->id)->firstOrFail()->access_token)->toBe('long-two');
});
