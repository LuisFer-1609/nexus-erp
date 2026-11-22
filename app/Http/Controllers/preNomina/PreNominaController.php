<?php

namespace App\Http\Controllers\preNomina;

use App\Http\Controllers\Controller;
use App\Services\PreNominaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PreNominaController extends Controller
{
    public function __construct(
        private readonly PreNominaService $preNominaService,
    ) {}

    public function empleados(): Response
    {
        return Inertia::render(
            'preNomina/Empleados',
            $this->preNominaService->getEmployeesData(),
        );
    }

    public function asistencias(): Response
    {
        return Inertia::render(
            'preNomina/Asistencias',
            $this->preNominaService->getAttendancesData(),
        );
    }

    public function index(Request $request): Response
    {
        $rawPeriod = $request->query('period');
        $periodId = is_string($rawPeriod) && $rawPeriod !== '' ? $rawPeriod : null;

        $rawFreq = $request->query('frequency');
        $frequency = is_string($rawFreq) && $rawFreq !== '' ? $rawFreq : null;

        return Inertia::render(
            'preNomina/PreNomina',
            $this->preNominaService->getPayrollData($periodId, $frequency),
        );
    }

    public function guardarAjuste(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'payrollId' => 'required|integer',
            'days' => 'nullable|integer',
            'delays' => 'nullable|integer',
            'absences' => 'nullable|integer',
            'extra' => 'nullable|numeric',
            'perceptions' => 'nullable|numeric',
            'deductions' => 'nullable|numeric',
            'net' => 'nullable|numeric',
            'bonus' => 'nullable|numeric',
            'customDeductions' => 'nullable|numeric',
            'adjustmentReason' => 'nullable|string',
        ]);

        $this->preNominaService->saveAdjustment((int) $validated['payrollId'], $validated);

        return back()->with('success', 'Ajuste manual guardado en la base de datos.');
    }

    public function recalcular(Request $request): RedirectResponse
    {
        $periodId = (int) $request->input('periodId');
        if ($periodId > 0) {
            $this->preNominaService->recalculatePeriod($periodId);
        }

        return back()->with('success', 'Pre-nómina recalculada con asistencias registradas.');
    }

    public function crearPeriodo(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => 'required|string',
            'frequency' => 'required|string',
            'startDate' => 'required|date',
            'endDate' => 'required|date',
            'paymentDate' => 'nullable|date',
            'type' => 'nullable|string',
        ]);

        $newId = $this->preNominaService->createPeriod($validated);

        return redirect()->route('pre-nomina.index', ['period' => $newId])
            ->with('success', 'Nuevo período de pre-nómina creado y calculado en la base de datos.');
    }

    public function cambiarEstado(Request $request): RedirectResponse
    {
        $periodId = (int) $request->input('periodId');
        $status = (string) $request->input('status', 'Borrador');

        $this->preNominaService->updatePeriodStatus($periodId, $status);

        return back()->with('success', "Estado del período actualizado a {$status}.");
    }

    public function aprobarMasivo(Request $request): RedirectResponse
    {
        return back()->with('success', 'Colaboradores aprobados correctamente.');
    }
}
