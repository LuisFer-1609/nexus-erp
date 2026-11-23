<?php

namespace App\Services\Crm\Meta;

use App\Models\Nexus\MetaConnection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MetaAccess
{
    public function companyId(Request $request): int
    {
        return (int) $request->user()->company_id;
    }

    public function canManage(Request $request): bool
    {
        return $request->user()->role?->name === 'Administrador';
    }

    public function assertReadable(Request $request): void
    {
        abort_unless(in_array($request->user()->role?->name, ['Administrador', 'Marketing'], true), 403);
    }

    public function assertManage(Request $request): void
    {
        abort_unless($this->canManage($request), 403);
    }

    public function connection(Request $request): ?MetaConnection
    {
        return MetaConnection::query()->where('company_id', $this->companyId($request))->first();
    }

    public function token(Request $request): string
    {
        $connection = $this->connection($request);
        abort_unless($connection !== null, 409, 'La empresa todavía no tiene una conexión con Meta.');

        return $connection->access_token;
    }

    public function assertLinkedAccount(Request $request, string $accountId): void
    {
        abort_unless(DB::table('meta_ad_accounts')
            ->where('company_id', $this->companyId($request))
            ->where('external_id', $accountId)->exists(), 403);
    }
}
