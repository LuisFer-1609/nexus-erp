<?php

namespace App\Infrastructure\Crm\Meta;

use App\Contracts\Crm\Meta\MetaClient;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GraphMetaAdapter implements MetaClient
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function adAccounts(): array
    {
        $accessToken = (string) config('services.meta.access_token');

        if ($accessToken === '') {
            throw new RuntimeException('El token de acceso de Meta no está configurado.');
        }

        $response = Http::baseUrl($this->baseUrl())
            ->withToken($accessToken)
            ->acceptJson()
            ->timeout(10)
            ->get('me/adaccounts', [
                'fields' => 'id,account_id,name,account_status,currency,timezone_name',
            ]);

        $response->throw();

        return $response->json('data', []);
    }

    private function baseUrl(): string
    {
        $url = rtrim((string) config('services.meta.url'), '/');
        $version = trim((string) config('services.meta.version'), '/');

        return "{$url}/{$version}";
    }
}
