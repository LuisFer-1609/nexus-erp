<?php

namespace App\Http\Controllers\crm;

use App\Http\Controllers\Controller;
use App\Services\Crm\Meta\MetaAccess;
use App\Services\Crm\Meta\MetaReadService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MetaReadController extends Controller
{
    public function adAccounts(Request $request, MetaReadService $service, MetaAccess $access): JsonResponse
    {
        $access->assertReadable($request);
        $companyId = $access->companyId($request);
        $linked = DB::table('meta_ad_accounts')->where('company_id', $companyId)->get()->keyBy('external_id');

        if (! $access->canManage($request)) {
            return response()->json(['data' => $linked->map(fn ($account) => [
                'id' => $account->external_id,
                'name' => $account->name,
                'currency' => $account->currency ?? '',
                'status' => 1,
                'linked' => true,
            ])->values()->all()]);
        }

        $connection = $access->connection($request);
        if (! $connection || ! $connection->business_id) {
            return response()->json(['data' => []]);
        }

        try {
            $accounts = $service->businessAdAccounts($connection->business_id, $access->token($request));
        } catch (RequestException|ConnectionException) {
            return response()->json(['message' => 'No se pudieron consultar las cuentas del Business Manager en Meta.'], 502);
        }

        return response()->json([
            'data' => array_map(
                fn (array $account) => [...$account, 'linked' => $linked->has($account['id']) && (int) $linked->get($account['id'])->company_id === $companyId],
                $accounts,
            ),
        ]);
    }
}
