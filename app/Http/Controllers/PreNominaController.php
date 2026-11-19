<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Attendance;
use App\Models\WorkSchedule;
use App\Services\AttendanceCalculator;
use Carbon\Carbon;
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

    public function asistencias(Request $request): Response
    {
        $date = $request->string('date')->toString() ?: (Attendance::query()->max('attendance_date') ?? now()->toDateString());
        $dayOfWeek = Carbon::parse($date)->dayOfWeekIso;
        $employeeId = $request->integer('employee_id') ?: null;

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
            'attendances' => Attendance::query()
                ->with(['employee.workSchedules'])
                ->whereDate('attendance_date', $date)
                ->when($employeeId, fn ($query) => $query->where('employee_id', $employeeId))
                ->orderBy('employee_id')
                ->get()
                ->map(function (Attendance $attendance) use ($dayOfWeek): array {
                    $schedule = $attendance->employee->workSchedules->firstWhere('day_of_week', $dayOfWeek);

                    return [
                        'id' => $attendance->id,
                        'employee_id' => $attendance->employee_id,
                        'employee' => $attendance->employee->first_name . ' ' . $attendance->employee->last_name,
                        'date' => $attendance->attendance_date->toDateString(),
                        'checkIn' => $attendance->check_in ? substr($attendance->check_in, 0, 5) : null,
                        'checkOut' => $attendance->check_out ? substr($attendance->check_out, 0, 5) : null,
                        'scheduleStart' => $schedule?->start_time ? substr($schedule->start_time, 0, 5) : null,
                        'scheduleEnd' => $schedule?->end_time ? substr($schedule->end_time, 0, 5) : null,
                        'delayMinutes' => $attendance->delay_minutes,
                        'overtimeMinutes' => $attendance->overtime_minutes,
                        'status' => match ($attendance->status) {
                            'late' => 'Retardo',
                            'absent' => 'Falta',
                            'day_off' => 'Descanso',
                            default => 'A tiempo',
                        },
                    ];
                })
                ->values(),
            'filters' => [
                'date' => $date,
                'employee_id' => $employeeId,
            ],
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

    public function attendanceDetail(Attendance $attendance): Response
    {
        $attendance->load('employee.workSchedules');
        $schedule = $attendance->employee->workSchedules->firstWhere(
            'day_of_week',
            $attendance->attendance_date->dayOfWeekIso,
        );

        return Inertia::render('preNomina/AsistenciaDetalle', [
            'attendance' => [
                'id' => $attendance->id,
                'date' => $attendance->attendance_date->toDateString(),
                'employee' => $attendance->employee->first_name . ' ' . $attendance->employee->last_name,
                'employeeNumber' => $attendance->employee->employee_number,
                'checkIn' => $attendance->check_in ? substr($attendance->check_in, 0, 5) : null,
                'checkOut' => $attendance->check_out ? substr($attendance->check_out, 0, 5) : null,
                'delayMinutes' => $attendance->delay_minutes,
                'overtimeMinutes' => $attendance->overtime_minutes,
                'status' => $attendance->status,
                'isManual' => $attendance->is_manual,
                'scheduleStart' => $schedule?->start_time ? substr($schedule->start_time, 0, 5) : null,
                'scheduleEnd' => $schedule?->end_time ? substr($schedule->end_time, 0, 5) : null,
                'isDayOff' => (bool) ($schedule?->is_day_off ?? false),
            ],
        ]);
    }

    public function updateAttendance(Request $request, Attendance $attendance, AttendanceCalculator $calculator): RedirectResponse
    {
        $validated = $request->validate([
            'check_in' => ['nullable', 'date_format:H:i'],
            'check_out' => ['nullable', 'date_format:H:i'],
        ]);

        Validator::make($validated, [
            'check_out' => ['nullable', 'after:check_in'],
        ])->validate();

        $attendance->load('employee.workSchedules');
        $schedule = $attendance->employee->workSchedules->firstWhere(
            'day_of_week',
            $attendance->attendance_date->dayOfWeekIso,
        );
        abort_unless($schedule, 422, 'No existe un horario para este día.');

        $attendance->update([
            'check_in' => $validated['check_in'] ?? null,
            'check_out' => $validated['check_out'] ?? null,
            ...$calculator->calculate($schedule, $validated['check_in'] ?? null, $validated['check_out'] ?? null),
            'is_manual' => true,
        ]);

        return to_route('pre-nomina.asistencias.show', $attendance)->with('success', 'Corrección guardada correctamente.');
    }

    public function index(): Response
    {
        return Inertia::render('preNomina/PreNomina');
    }
}
