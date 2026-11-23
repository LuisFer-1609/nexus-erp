<?php

namespace App\Http\Controllers\crm;

use App\Http\Controllers\Controller;
use App\Services\Crm\Meta\ImportMetaLeads;
use App\Services\Crm\Meta\MetaAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MetaLeadImportController extends Controller
{
    public function store(Request $request, MetaAccess $access, ImportMetaLeads $importer): RedirectResponse
    {
        $access->assertManage($request);
        $result = $importer->company($access->companyId($request));

        if ($result['failed_pages'] > 0) {
            return back()->withErrors(['meta' => "No se pudieron importar {$result['failed_pages']} páginas. Revisa los permisos del token de Meta."]);
        }

        return back()->with('success', "Leads nuevos: {$result['imported']}.");
    }
}
