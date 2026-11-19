<?php

namespace App\Services\Crm\Meta;

use App\Contracts\Crm\Meta\MetaClient;

class MetaReadService
{
    public function __construct(private readonly MetaClient $metaClient) {}

    /**
     * @return array<int, array<string, int|string>>
     */
    public function adAccounts(): array
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
            $this->metaClient->adAccounts(),
        );
    }
}
