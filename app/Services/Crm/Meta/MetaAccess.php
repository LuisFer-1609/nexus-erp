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
        return MetaConnection::query()
            ->where('company_id', $this->companyId($request))
            ->where('auth_user_id', $request->user()->id)
            ->first();
    }

    public function token(Request $request): string
    {
        $connection = $this->connection($request);
        abort_unless($connection !== null, 409, 'Conecta tu cuenta de Meta.');

        return $this->tokenForConnection($connection);
    }

    public function tokenForAccount(Request $request, string $accountId): string
    {
        $connectionId = DB::table('meta_ad_accounts')
            ->where('company_id', $this->companyId($request))
            ->where('external_id', $accountId)
            ->value('meta_connection_id');
        $connection = MetaConnection::query()
            ->where('company_id', $this->companyId($request))
            ->whereKey($connectionId)
            ->first();
        abort_unless($connection !== null, 409, 'Reconecta esta cuenta con Meta.');

        return $this->tokenForConnection($connection);
    }

    private function tokenForConnection(MetaConnection $connection): string
    {
        abort_if($connection->expires_at && $connection->expires_at->isPast(), 409, 'La conexión de Meta expiró. El administrador debe reconectarla.');

        return $connection->access_token;
    }

    public function assertLinkedAccount(Request $request, string $accountId): void
    {
        abort_unless(DB::table('meta_ad_accounts')
            ->where('company_id', $this->companyId($request))
            ->where('external_id', $accountId)->exists(), 403);
    }
}
