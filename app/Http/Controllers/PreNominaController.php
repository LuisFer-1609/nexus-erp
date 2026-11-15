<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\WorkSchedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
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

    public function updateSchedule(Request $request, Employee $employee, WorkSchedule $workSchedule): RedirectResponse
    {
        abort_unless($workSchedule->employee_id === $employee->id, 404);

        $validated = $request->validate([
            'day_of_week' => ['required', 'integer', 'between:1,7'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'is_day_off' => ['required', 'boolean'],
        ]);

        if (! $validated['is_day_off']) {
            Validator::make($validated, [
                'start_time' => ['required'],
                'end_time' => ['required', 'after:start_time'],
            ])->validate();
        }

        $workSchedule->update([
            'day_of_week' => $validated['day_of_week'],
            'start_time' => $validated['is_day_off'] ? null : $validated['start_time'],
            'end_time' => $validated['is_day_off'] ? null : $validated['end_time'],
            'is_day_off' => $validated['is_day_off'],
        ]);

        return to_route('pre-nomina.asistencias')->with('success', 'Horario actualizado correctamente.');
    }

    public function index(): Response
    {
        return Inertia::render('preNomina/PreNomina');
    }
}
