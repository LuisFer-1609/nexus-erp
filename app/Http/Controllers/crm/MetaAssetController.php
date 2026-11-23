<?php

namespace App\Http\Controllers\crm;

use App\Http\Controllers\Controller;
use App\Services\Crm\Meta\MetaAccess;
use App\Services\Crm\Meta\MetaReadService;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MetaAssetController extends Controller
{
    public function pages(Request $request, MetaAccess $access, MetaReadService $meta): JsonResponse
    {
        $access->assertManage($request);
        $linked = DB::table('meta_pages')->where('company_id', $access->companyId($request))->pluck('external_id')->all();

        try {
            $pages = $meta->pages($access->token($request));
        } catch (RequestException) {
            return response()->json(['message' => 'No se pudieron consultar las páginas en Meta.'], 502);
        }

        return response()->json(['data' => array_map(
            fn (array $page) => [
                'id' => (string) $page['id'],
                'name' => (string) $page['name'],
                'linked' => in_array((string) $page['id'], $linked, true),
            ],
            $pages,
        )]);
    }

    public function linkAccount(Request $request, MetaAccess $access, MetaReadService $meta): RedirectResponse
    {
        $access->assertManage($request);
        $data = $request->validate(['account_id' => ['required', 'regex:/^\d+$/']]);
        try {
            $account = collect($meta->adAccounts($access->token($request)))
                ->firstWhere('id', $data['account_id']);
        } catch (RequestException) {
            return back()->withErrors(['meta' => 'No se pudo verificar la cuenta en Meta.']);
        }

        if (! $account) {
            throw ValidationException::withMessages(['account_id' => 'La cuenta no está disponible en esta conexión.']);
        }

        $this->link('meta_ad_accounts', $access->companyId($request), (string) $account['id'], (string) $account['name']);

        return back()->with('success', 'Cuenta publicitaria vinculada.');
    }

    public function linkPage(Request $request, MetaAccess $access, MetaReadService $meta): RedirectResponse
    {
        $access->assertManage($request);
        $data = $request->validate(['page_id' => ['required', 'regex:/^\d+$/']]);
        try {
            $page = collect($meta->pages($access->token($request)))
                ->first(fn (array $item) => (string) $item['id'] === $data['page_id']);
        } catch (RequestException) {
            return back()->withErrors(['meta' => 'No se pudo verificar la página en Meta.']);
        }

        if (! $page) {
            throw ValidationException::withMessages(['page_id' => 'La página no está disponible en esta conexión.']);
        }

        $this->link('meta_pages', $access->companyId($request), $data['page_id'], (string) $page['name']);

        return back()->with('success', 'Página vinculada para importar leads.');
    }

    private function link(string $table, int $companyId, string $externalId, string $name): void
    {
        $owner = DB::table($table)->where('external_id', $externalId)->first();
        if ($owner && (int) $owner->company_id !== $companyId) {
            throw ValidationException::withMessages(['meta' => 'Este activo ya pertenece a otra empresa.']);
        }

        DB::table($table)->updateOrInsert(
            ['external_id' => $externalId],
            ['company_id' => $companyId, 'name' => $name, 'updated_at' => now(), 'created_at' => $owner ? $owner->created_at : now()],
        );
    }
}
