<?php

namespace App\Contracts\Crm\Meta;

interface MetaClient
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function adAccounts(?string $accessToken = null): array;

    /** @return array<int, array<string, mixed>> */
    public function businesses(string $accessToken): array;

    /** @return array<int, array<string, mixed>> */
    public function businessAdAccounts(string $businessId, string $accessToken): array;

    /** @return array<int, array<string, mixed>> */
    public function businessPages(string $businessId, string $accessToken): array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function campaigns(string $adAccountId, string $accessToken): array;

    /** @return array<int, array<string, mixed>> */
    public function pages(string $accessToken): array;

    /** @return array<int, array<string, mixed>> */
    public function insights(string $adAccountId, string $accessToken, string $since, string $until): array;

    /** @return array<int, array<string, mixed>> */
    public function leadForms(string $pageId, string $accessToken): array;

    /** @return array<int, array<string, mixed>> */
    public function formLeads(string $formId, string $accessToken): array;
}
