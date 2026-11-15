<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use Inertia\Inertia;
use Inertia\Response;

class PreNominaController extends Controller
{
    public function empleados(): Response
    {
        return Inertia::render('preNomina/Empleados');
    }

    public function asistencias(): Response
    {
        return Inertia::render('preNomina/Asistencias', [
            'employees' => Employee::query()
                ->with('workSchedules')
                ->where('is_active', true)
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get([
                    'id',
                    'employee_number',
                    'first_name',
                    'last_name',
                ]),
        ]);
    }

    public function index(): Response
    {
        return Inertia::render('preNomina/PreNomina');
    }
}
