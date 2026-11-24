<?php

namespace App\Services\Crm\Meta;

use App\Contracts\Crm\Meta\MetaClient;

class MetaReadService
{
    public function __construct(private readonly MetaClient $metaClient) {}

    /**
     * @return array<int, array<string, int|string>>
     */
    public function adAccounts(?string $accessToken = null): array
    {
        return $this->normalizeAccounts($this->metaClient->adAccounts($accessToken));
    }

    /** @return array<int, array<string, mixed>> */
    public function businesses(string $accessToken): array
    {
        return $this->metaClient->businesses($accessToken);
    }

    /** @return array<int, array<string, int|string>> */
    public function businessAdAccounts(string $businessId, string $accessToken): array
    {
        return $this->normalizeAccounts($this->metaClient->businessAdAccounts($businessId, $accessToken));
    }

    /** @return array<int, array<string, mixed>> */
    public function businessPages(string $businessId, string $accessToken): array
    {
        return $this->metaClient->businessPages($businessId, $accessToken);
    }

    /** @param array<int, array<string, mixed>> $accounts
     * @return array<int, array<string, int|string>>
     */
    private function normalizeAccounts(array $accounts): array
    {
        return array_map(
            fn (array $account): array => [
                'id' => (string) $account['account_id'],
                'graph_id' => (string) $account['id'],
                'name' => (string) $account['name'],
                'status' => (int) $account['account_status'],
                'currency' => (string) $account['currency'],
                'timezone' => (string) $account['timezone_name'],
            ],
            $accounts,
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function campaigns(string $adAccountId, string $accessToken): array
    {
        return $this->metaClient->campaigns($adAccountId, $accessToken);
    }

    /** @return array<int, array<string, mixed>> */
    public function pages(string $accessToken): array
    {
        return $this->metaClient->pages($accessToken);
    }

    /** @return array<int, array<string, mixed>> */
    public function insights(string $adAccountId, string $accessToken, string $since, string $until): array
    {
        return $this->metaClient->insights($adAccountId, $accessToken, $since, $until);
    }
}
