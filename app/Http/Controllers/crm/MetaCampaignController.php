<?php

namespace App\Http\Controllers\crm;

use App\Http\Controllers\Controller;
use App\Models\Nexus\MetaConnection;
use App\Services\Crm\Meta\MetaAccess;
use App\Services\Crm\Meta\MetaReadService;
use App\Services\Crm\Meta\SaveMetaCampaigns;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class MetaCampaignController extends Controller
{
    public function index(Request $request, MetaAccess $access): Response
    {
        $access->assertReadable($request);

        return Inertia::render('crm/Campanas', [
            'companyName' => $request->user()->company->name,
            'canManage' => $access->canManage($request),
            'hasConnection' => $access->canManage($request)
                ? $access->connection($request) !== null
                : DB::table('meta_ad_accounts')->where('company_id', $access->companyId($request))->exists(),
            'businessName' => $access->connection($request)?->business_name,
            'metaConfigured' => (bool) (config('services.meta.app_id') && config('services.meta.app_secret')),
        ]);
    }

    public function list(Request $request, MetaAccess $access, MetaReadService $meta): JsonResponse
    {
        $access->assertReadable($request);
        $data = $request->validate($this->rangeRules());
        $access->assertLinkedAccount($request, $data['account_id']);
        $token = $access->tokenForAccount($request, $data['account_id']);

        try {
            $campaigns = $meta->campaigns($data['account_id'], $token);
        } catch (RequestException|ConnectionException) {
            return response()->json(['message' => 'No se pudieron consultar las campañas en Meta.'], 502);
        }

        $metricsError = false;
        try {
            $insights = collect($meta->insights($data['account_id'], $token, $data['since'], $data['until']))->keyBy('campaign_id');
        } catch (RequestException|ConnectionException) {
            $insights = collect();
            $metricsError = true;
        }

        $saved = DB::table('meta_saved_campaigns')
            ->where('company_id', $access->companyId($request))
            ->where('ad_account_id', $data['account_id'])
            ->where('period_start', $data['since'])
            ->where('period_end', $data['until'])
            ->pluck('meta_campaign_id')->all();

        return response()->json(['metrics_error' => $metricsError, 'data' => array_values(array_map(
            fn (array $campaign) => [
                'id' => (string) $campaign['id'],
                'name' => (string) $campaign['name'],
                'status' => $campaign['status'] ?? null,
                'effective_status' => $campaign['effective_status'] ?? null,
                'objective' => $campaign['objective'] ?? null,
                'metrics' => $insights->get((string) $campaign['id'], []),
                'saved' => in_array((string) $campaign['id'], $saved, true),
            ],
            $campaigns,
        ))]);
    }

    public function save(Request $request, MetaAccess $access, MetaReadService $meta, SaveMetaCampaigns $saver): RedirectResponse
    {
        $access->assertReadable($request);
        $data = $request->validate([
            ...$this->rangeRules(),
            'campaign_ids' => ['required', 'array', 'min:1', 'max:1000'],
            'campaign_ids.*' => ['required', 'regex:/^\d+$/'],
        ]);
        $access->assertLinkedAccount($request, $data['account_id']);
        $token = $access->tokenForAccount($request, $data['account_id']);
        try {
            $connection = MetaConnection::query()->where('company_id', $access->companyId($request))
                ->whereKey(DB::table('meta_ad_accounts')->where('company_id', $access->companyId($request))->where('external_id', $data['account_id'])->value('meta_connection_id'))->firstOrFail();
            $account = collect($meta->businessAdAccounts($connection->business_id, $token))->firstWhere('id', $data['account_id']);
            abort_unless($account !== null, 403);
            $counts = $saver->save(
                $access->companyId($request),
                $request->user()->id,
                $data['account_id'],
                (string) $account['currency'],
                $token,
                $data['since'],
                $data['until'],
                $data['campaign_ids'],
            );
        } catch (RequestException|ConnectionException) {
            return back()->withErrors(['meta' => 'No se pudieron guardar las campañas porque Meta no respondió.']);
        }

        return back()->with('success', "Campañas: {$counts['created']} nuevas, {$counts['updated']} actualizadas, {$counts['unchanged']} sin cambios.");
    }

    /** @return array<string, array<int, string>> */
    private function rangeRules(): array
    {
        return [
            'account_id' => ['required', 'regex:/^\d+$/'],
            'since' => ['required', 'date_format:Y-m-d'],
            'until' => ['required', 'date_format:Y-m-d', 'after_or_equal:since'],
        ];
    }
}
