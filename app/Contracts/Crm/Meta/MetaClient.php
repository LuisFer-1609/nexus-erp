<?php

namespace App\Contracts\Crm\Meta;

interface MetaClient
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function adAccounts(): array;
}
