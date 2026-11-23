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
    public function adAccounts(?string $accessToken = null): array
    {
        return $this->fetchAll('me/adaccounts', $accessToken ?? (string) config('services.meta.access_token'), [
            'fields' => 'id,account_id,name,account_status,currency,timezone_name',
        ]);
    }

    public function pages(string $accessToken): array
    {
        return $this->fetchAll('me/accounts', $accessToken, ['fields' => 'id,name']);
    }

    public function campaigns(string $adAccountId, string $accessToken): array
    {
        $this->assertId($adAccountId);

        return $this->fetchAll("act_{$adAccountId}/campaigns", $accessToken, [
            'fields' => 'id,account_id,name,status,effective_status,objective,created_time,start_time,stop_time',
        ]);
    }

    public function insights(string $adAccountId, string $accessToken, string $since, string $until): array
    {
        $this->assertId($adAccountId);

        return $this->fetchAll("act_{$adAccountId}/insights", $accessToken, [
            'level' => 'campaign',
            'fields' => 'campaign_id,spend,impressions,reach,clicks,ctr,cpc,cpm,actions,cost_per_action_type',
            'time_range' => json_encode(['since' => $since, 'until' => $until], JSON_THROW_ON_ERROR),
        ]);
    }

    public function leadForms(string $pageId, string $accessToken): array
    {
        $this->assertId($pageId);

        return $this->fetchAll("{$pageId}/leadgen_forms", $accessToken, ['fields' => 'id,name']);
    }

    public function formLeads(string $formId, string $accessToken): array
    {
        $this->assertId($formId);

        return $this->fetchAll("{$formId}/leads", $accessToken, [
            'fields' => 'id,created_time,form_id,ad_id,campaign_id,field_data',
        ]);
    }

    /**
     * @param  array<string, int|string>  $parameters
     * @return array<int, array<string, mixed>>
     */
    private function fetchAll(string $path, string $accessToken, array $parameters): array
    {

        if ($accessToken === '') {
            throw new RuntimeException('El token de acceso de Meta no está configurado.');
        }

        $items = [];
        $cursor = null;

        do {
            $response = Http::baseUrl($this->baseUrl())
                ->withToken($accessToken)
                ->acceptJson()
                ->timeout(15)
                ->get($path, array_filter([
                    ...$parameters,
                    'limit' => 100,
                    'after' => $cursor,
                ], fn ($value) => $value !== null));

            $response->throw();
            $items = [...$items, ...$response->json('data', [])];
            $next = $response->json('paging.cursors.after');
            $cursor = $response->json('paging.next') && is_string($next) && $next !== $cursor ? $next : null;
        } while ($cursor !== null);

        return $items;
    }

    private function assertId(string $id): void
    {
        if (! preg_match('/^\d+$/', $id)) {
            throw new RuntimeException('Identificador de Meta no válido.');
        }
    }

    private function baseUrl(): string
    {
        $url = rtrim((string) config('services.meta.url'), '/');
        $version = trim((string) config('services.meta.version'), '/');

        return "{$url}/{$version}";
    }
}
