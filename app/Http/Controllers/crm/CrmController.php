<?php

namespace App\Http\Controllers\crm;

use App\Http\Controllers\Controller;
use App\Services\Crm\Meta\MetaAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CrmController extends Controller
{
    public function leads(Request $request, MetaAccess $access): Response
    {
        $access->assertReadable($request);

        return Inertia::render('crm/Leads', [
            'leads' => DB::table('leads as l')
                ->join('lead_stages as s', 's.id', '=', 'l.lead_stage_id')
                ->join('lead_sources as o', 'o.id', '=', 'l.lead_source_id')
                ->where('l.company_id', $access->companyId($request))
                ->whereNull('l.deleted_at')
                ->select('l.id', 'l.full_name', 'l.email', 'l.phone', 'l.meta_page_id', 'l.meta_form_id', 'l.meta_campaign_id', 'l.created_at', 's.name as stage', 'o.name as source')
                ->orderByDesc('l.created_at')->paginate(50),
        ]);
    }

    public function clientes(): Response
    {
        return Inertia::render('crm/Clientes');
    }

    public function cliente(string $cliente): Response
    {
        return Inertia::render('crm/Cliente', [
            'clienteId' => $cliente,
        ]);
    }

    public function asistente(): Response
    {
        return Inertia::render('crm/Asistente');
    }

    public function configuracion(Request $request, MetaAccess $access): Response
    {
        $connection = $access->connection($request);

        return Inertia::render('crm/Configuracion', [
            'hasConnection' => $connection !== null,
            'businessName' => $connection?->business_name,
            'metaConfigured' => (bool) (config('services.meta.app_id') && config('services.meta.app_secret')),
        ]);
    }
}
