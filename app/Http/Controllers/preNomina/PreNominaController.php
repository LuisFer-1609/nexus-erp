<?php

namespace App\Http\Controllers\preNomina;

use App\Http\Controllers\Controller;
use App\Services\PreNominaService;
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
}
