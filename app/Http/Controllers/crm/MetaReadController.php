<?php

namespace App\Http\Controllers\crm;

use App\Http\Controllers\Controller;
use App\Services\Crm\Meta\MetaAccess;
use App\Services\Crm\Meta\MetaReadService;
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
        $linked = DB::table('meta_ad_accounts')->where('company_id', $companyId)->pluck('external_id')->all();

        try {
            $accounts = $service->adAccounts($access->token($request));
        } catch (RequestException) {
            return response()->json(['message' => 'No se pudieron consultar las cuentas en Meta.'], 502);
        }
        if (! $access->canManage($request)) {
            $accounts = array_values(array_filter($accounts, fn (array $account) => in_array($account['id'], $linked, true)));
        }

        return response()->json([
            'data' => array_map(
                fn (array $account) => [...$account, 'linked' => in_array($account['id'], $linked, true)],
                $accounts,
            ),
        ]);
    }
}
