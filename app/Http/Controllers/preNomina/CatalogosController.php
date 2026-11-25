<?php

namespace App\Http\Controllers\preNomina;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\CatalogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Administración de los 6 catálogos de Pre-Nómina: áreas, puestos, frecuencias
 * de nómina, conceptos, nacionalidades y tipos de negocio.
 */
class CatalogosController extends Controller
{
    /** @var array<string, string> */
    private const PAGES = [
        'areas' => 'preNomina/catalogos/Areas',
        'puestos' => 'preNomina/catalogos/Puestos',
        'frecuencias' => 'preNomina/catalogos/Frecuencias',
        'conceptos' => 'preNomina/catalogos/Conceptos',
        'nacionalidades' => 'preNomina/catalogos/Nacionalidades',
        'tipos-negocio' => 'preNomina/catalogos/TiposNegocio',
    ];

    /** @var array<string, string> */
    private const LABELS = [
        'areas' => 'Área',
        'puestos' => 'Puesto',
        'frecuencias' => 'Frecuencia de nómina',
        'conceptos' => 'Concepto de nómina',
        'nacionalidades' => 'Nacionalidad',
        'tipos-negocio' => 'Tipo de negocio',
    ];

    public function __construct(
        private readonly CatalogService $catalogs,
    ) {}

    public function areas(Request $request): Response
    {
        return $this->render($request, 'areas');
    }

    public function puestos(Request $request): Response
    {
        return $this->render($request, 'puestos');
    }

    public function frecuencias(Request $request): Response
    {
        return $this->render($request, 'frecuencias');
    }

    public function conceptos(Request $request): Response
    {
        return $this->render($request, 'conceptos');
    }

    public function nacionalidades(Request $request): Response
    {
        return $this->render($request, 'nacionalidades');
    }

    public function tiposNegocio(Request $request): Response
    {
        return $this->render($request, 'tipos-negocio');
    }

    public function store(Request $request, string $catalog): RedirectResponse
    {
        abort_unless($this->catalogs->supports($catalog), 404);
        [$companyId, $userId] = $this->actor($request);

        $this->catalogs->create($catalog, $companyId, $userId, $request->all());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => self::LABELS[$catalog].' agregado correctamente.',
        ]);

        return back();
    }

    public function update(Request $request, string $catalog, int $id): RedirectResponse
    {
        abort_unless($this->catalogs->supports($catalog), 404);
        [$companyId, $userId] = $this->actor($request);

        $this->catalogs->update($catalog, $companyId, $userId, $id, $request->all());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => self::LABELS[$catalog].' actualizado correctamente.',
        ]);

        return back();
    }

    public function destroy(Request $request, string $catalog, int $id): RedirectResponse
    {
        abort_unless($this->catalogs->supports($catalog), 404);
        [$companyId, $userId] = $this->actor($request);

        $this->catalogs->delete($catalog, $companyId, $userId, $id);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => self::LABELS[$catalog].' eliminado correctamente.',
        ]);

        return back();
    }

    private function render(Request $request, string $slug): Response
    {
        [$companyId] = $this->actor($request);

        return Inertia::render(
            self::PAGES[$slug],
            $this->catalogs->listData($slug, $companyId),
        );
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function actor(Request $request): array
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->company_id !== null, 403, 'Tu cuenta no tiene una empresa asignada.');

        return [(int) $user->company_id, (int) $user->id];
    }
}
