<?php

namespace App\Services\Crm\Meta;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class MetaOAuth
{
    public function redirectUri(): string
    {
        return (string) (config('services.meta.redirect_uri') ?: route('crm.meta.oauth.callback'));
    }

    public function authorizationUrl(string $state): string
    {
        $this->assertConfigured();
        $params = [
            'client_id' => (string) config('services.meta.app_id'),
            'redirect_uri' => $this->redirectUri(),
            'state' => $state,
            'response_type' => 'code',
        ];
        $configId = (string) config('services.meta.login_config_id');
        if ($configId !== '') {
            $params['config_id'] = $configId;
            $params['override_default_response_type'] = 'true';
        } else {
            $params['scope'] = 'ads_read,business_management,pages_show_list,pages_read_engagement,leads_retrieval';
        }

        $version = trim((string) config('services.meta.version'), '/');

        return "https://www.facebook.com/{$version}/dialog/oauth?".http_build_query($params);
    }

    /** @return array{access_token: string, expires_in: int, meta_user_id: string} */
    public function exchange(string $code): array
    {
        $this->assertConfigured();
        $base = rtrim((string) config('services.meta.url'), '/').'/'.trim((string) config('services.meta.version'), '/');
        $appId = (string) config('services.meta.app_id');
        $secret = (string) config('services.meta.app_secret');

        $short = Http::timeout(15)->get("{$base}/oauth/access_token", [
            'client_id' => $appId,
            'client_secret' => $secret,
            'redirect_uri' => $this->redirectUri(),
            'code' => $code,
        ]);
        if (! $short->successful() || ! is_string($short->json('access_token'))) {
            throw new RuntimeException('Meta no pudo autorizar la conexión.');
        }

        $long = Http::timeout(15)->get("{$base}/oauth/access_token", [
            'grant_type' => 'fb_exchange_token',
            'client_id' => $appId,
            'client_secret' => $secret,
            'fb_exchange_token' => $short->json('access_token'),
        ]);
        if (! $long->successful() || ! is_string($long->json('access_token'))) {
            throw new RuntimeException('Meta no pudo extender la sesión.');
        }

        $token = $long->json('access_token');
        $profile = Http::withToken($token)->timeout(15)->get("{$base}/me", ['fields' => 'id']);
        if (! $profile->successful() || ! is_string($profile->json('id'))) {
            throw new RuntimeException('Meta no pudo identificar al administrador.');
        }

        return [
            'access_token' => $token,
            'expires_in' => (int) $long->json('expires_in', 0),
            'meta_user_id' => $profile->json('id'),
        ];
    }

    private function assertConfigured(): void
    {
        if (! config('services.meta.app_id') || ! config('services.meta.app_secret')) {
            throw new RuntimeException('Configura META_APP_ID y META_APP_SECRET para conectar Meta.');
        }
    }
}
