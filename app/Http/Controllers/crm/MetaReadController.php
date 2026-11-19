<?php

namespace App\Http\Controllers\crm;

use App\Http\Controllers\Controller;
use App\Services\Crm\Meta\MetaReadService;
use Illuminate\Http\JsonResponse;

class MetaReadController extends Controller
{
    public function adAccounts(MetaReadService $service): JsonResponse
    {
        return response()->json([
            'data' => $service->adAccounts(),
        ]);
    }
}
