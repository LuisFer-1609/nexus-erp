<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $canViewCrm = in_array($request->user()->role?->name, ['Administrador', 'Marketing'], true);
        $companyId = (int) $request->user()->company_id;

        return Inertia::render('Dashboard', [
            'canViewCrm' => $canViewCrm,
            // Campañas en vivo vienen de Meta desde el navegador; esto es la copia guardada.
            'savedCampaigns' => $canViewCrm ? $this->savedCampaigns($companyId) : [],
            'leadStats' => $canViewCrm ? $this->leadStats($companyId) : null,
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    private function savedCampaigns(int $companyId): array
    {
        // Una fila por campaña: la copia guardada más reciente.
        return DB::table('meta_saved_campaigns as sc')
            ->join('meta_ad_accounts as a', fn ($join) => $join
                ->on('a.external_id', '=', 'sc.ad_account_id')
                ->on('a.company_id', '=', 'sc.company_id'))
            ->where('sc.company_id', $companyId)
            ->orderByDesc('sc.fetched_at')
            ->get([
                'sc.meta_campaign_id as id',
                'sc.name',
                'sc.status',
                'sc.effective_status',
                'sc.currency',
                'sc.metrics',
                'a.name as accountName',
            ])
            ->unique('id')
            ->values()
            ->map(fn ($row) => [
                'id' => $row->id,
                'name' => $row->name,
                'status' => $row->status,
                'effective_status' => $row->effective_status,
                'currency' => $row->currency,
                'accountName' => $row->accountName,
                'metrics' => json_decode((string) $row->metrics, true) ?? [],
            ])
            ->all();
    }

    /** @return array{total: int, byStage: array<int, array{name: string, count: int}>, bySource: array<int, array{name: string, count: int}>} */
    private function leadStats(int $companyId): array
    {
        // Mismo rango de 30 días que las campañas de Meta. Columnas calificadas: lead_stages y lead_sources también tienen deleted_at y created_at.
        $base = DB::table('leads')
            ->where('leads.company_id', $companyId)
            ->whereNull('leads.deleted_at')
            ->where('leads.created_at', '>=', now()->subDays(29)->startOfDay());

        return [
            'total' => (clone $base)->count(),
            'byStage' => (clone $base)
                ->join('lead_stages as s', 's.id', '=', 'leads.lead_stage_id')
                ->selectRaw('s.name as name, count(*) as count')
                ->groupBy('s.id', 's.name', 's.position')
                ->orderBy('s.position')
                ->get()->all(),
            'bySource' => (clone $base)
                ->join('lead_sources as o', 'o.id', '=', 'leads.lead_source_id')
                ->selectRaw('o.name as name, count(*) as count')
                ->groupBy('o.id', 'o.name')
                ->orderByDesc('count')
                ->get()->all(),
        ];
    }
}
