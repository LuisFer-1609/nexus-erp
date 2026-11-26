<?php

namespace App\Http\Controllers\preNomina;

use App\Http\Controllers\Controller;
use App\Models\Nexus\Attendance;
use App\Models\Nexus\Employee;
use App\Models\Nexus\WorkSchedule;
use App\Models\User;
use App\Services\AttendanceCalculator;
use App\Services\EmployeeService;
use App\Services\PreNominaService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;

class PreNominaController extends Controller
{
    public function __construct(
        private readonly PreNominaService $preNominaService,
        private readonly EmployeeService $employeeService,
    ) {}

    public function empleados(Request $request): Response
    {
        [$companyId] = $this->actor($request);

        return Inertia::render(
            'preNomina/Empleados',
            $this->employeeService->listPageData($companyId),
        );
    }

    public function storeEmpleado(Request $request): RedirectResponse
    {
        [$companyId, $actorId] = $this->actor($request);
        $this->employeeService->create($companyId, $actorId, $request->all());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Empleado registrado correctamente.',
        ]);

        return back();
    }

    public function updateEmpleado(Request $request, Employee $employee): RedirectResponse
    {
        [$companyId, $actorId] = $this->actor($request);
        $this->employeeService->update($companyId, $actorId, $employee, $request->all());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Datos del empleado actualizados.',
        ]);

        return back();
    }

    public function destroyEmpleado(Request $request, Employee $employee): RedirectResponse
    {
        [$companyId, $actorId] = $this->actor($request);
        $this->employeeService->delete($companyId, $actorId, $employee);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Empleado dado de baja correctamente.',
        ]);

        return back();
    }

    public function descargarPlantillaCsv(): HttpResponse
    {
        return response($this->employeeService->csvTemplate(), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="plantilla_empleados_nexuserp.csv"',
        ]);
    }

    public function previewEmpleadosCsv(Request $request): JsonResponse
    {
        [$companyId] = $this->actor($request);

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ], [
            'file.required' => 'Selecciona un archivo CSV.',
            'file.mimes' => 'El archivo debe ser formato CSV (.csv o .txt).',
        ]);

        /** @var \Illuminate\Http\UploadedFile $file */
        $file = $request->file('file');

        return response()->json($this->employeeService->previewCsv($companyId, $file));
    }

    public function confirmarEmpleadosCsv(Request $request): RedirectResponse
    {
        [$companyId, $actorId] = $this->actor($request);

        $validated = $request->validate([
            'rows' => ['required', 'array', 'min:1'],
            'rows.*' => ['required', 'array'],
        ], [
            'rows.required' => 'No hay filas válidas para importar.',
            'rows.min' => 'Debe haber al menos un registro válido para importar.',
        ]);

        /** @var list<array<string, mixed>> $rows */
        $rows = $validated['rows'];
        $result = $this->employeeService->importConfirmedRows($companyId, $actorId, $rows);

        $message = $result['skipped'] > 0
            ? "Se importaron {$result['imported']} empleado(s); {$result['skipped']} se omitieron por error."
            : "Se importaron {$result['imported']} empleado(s) correctamente.";

        Inertia::flash('toast', [
            'type' => $result['imported'] > 0 ? 'success' : 'warning',
            'message' => $message,
        ]);

        return back();
    }

    public function asistencias(Request $request): Response
    {
        $date = $request->string('date')->toString() ?: (Attendance::query()->max('attendance_date') ?? now()->toDateString());
        $day = Carbon::parse($date)->dayOfWeekIso;
        $employeeId = $request->integer('employee_id') ?: null;
        $employees = Employee::query()->with(['user', 'workSchedules'])->where('status', 'activo')->orderBy('code')->get();
        $attendances = Attendance::query()->with(['employee.user', 'employee.workSchedules'])->whereDate('attendance_date', $date)->when($employeeId, fn ($query) => $query->where('employee_id', $employeeId))->orderBy('employee_id')->get()->map(fn (Attendance $record) => $this->attendanceRow($record, $day))->values();

        return Inertia::render('preNomina/Asistencias', ['employees' => $employees->map(fn (Employee $employee) => $this->employeeRow($employee))->values(), 'attendances' => $attendances, 'filters' => ['date' => $date, 'employee_id' => $employeeId]]);
    }

    public function updateSchedule(Request $request, Employee $employee, WorkSchedule $workSchedule): RedirectResponse
    {
        abort_unless($workSchedule->employee_id === $employee->id, 404);
        $validated = $request->validate(['day_of_week' => ['required', 'integer', 'between:1,7'], 'start_time' => ['nullable', 'date_format:H:i'], 'end_time' => ['nullable', 'date_format:H:i'], 'is_day_off' => ['required', 'boolean']]);
        if (! $validated['is_day_off']) {
            Validator::make($validated, ['start_time' => ['required'], 'end_time' => ['required', 'after:start_time']])->validate();
        }
        $workSchedule->update(['weekday' => $validated['day_of_week'], 'check_in_time' => $validated['is_day_off'] ? null : $validated['start_time'], 'check_out_time' => $validated['is_day_off'] ? null : $validated['end_time'], 'is_rest_day' => $validated['is_day_off']]);

        return to_route('pre-nomina.asistencias')->with('success', 'Horario actualizado correctamente.');
    }

    public function attendanceDetail(Attendance $attendance): Response
    {
        $attendance->load(['employee.user', 'employee.workSchedules']);

        return Inertia::render('preNomina/AsistenciaDetalle', ['attendance' => $this->attendanceDetailRow($attendance)]);
    }

    public function updateAttendance(Request $request, Attendance $attendance, AttendanceCalculator $calculator): RedirectResponse
    {
        $validated = $request->validate(['check_in' => ['nullable', 'date_format:H:i'], 'check_out' => ['nullable', 'date_format:H:i']]);
        Validator::make($validated, ['check_out' => ['nullable', 'after:check_in']])->validate();
        $attendance->load(['employee.workSchedules']);
        $schedule = $attendance->employee->workSchedules->firstWhere('weekday', $attendance->attendance_date->dayOfWeekIso);
        abort_unless($schedule !== null, 422, 'No existe un horario para este día.');
        $calculated = $calculator->calculate($schedule, $validated['check_in'] ?? null, $validated['check_out'] ?? null);
        $attendance->update(['check_in' => $validated['check_in'] ?? null, 'check_out' => $validated['check_out'] ?? null, ...$calculated, 'expected_check_in' => $schedule->check_in_time, 'expected_check_out' => $schedule->check_out_time]);

        return to_route('pre-nomina.asistencias.show', $attendance)->with('success', 'Corrección guardada correctamente.');
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

    /** @return array<string, mixed> */
    private function employeeRow(Employee $employee): array
    {
        return ['id' => $employee->id, 'employee_number' => $employee->code, 'first_name' => $employee->user->name, 'last_name' => $employee->user->last_name ?? '', 'work_schedules' => $employee->workSchedules->map(fn (WorkSchedule $schedule) => ['id' => $schedule->id, 'day_of_week' => $schedule->weekday, 'start_time' => $schedule->check_in_time, 'end_time' => $schedule->check_out_time, 'is_day_off' => (bool) $schedule->is_rest_day])->values()];
    }

    /** @return array<string, mixed> */
    private function attendanceRow(Attendance $record, int $day): array
    {
        $schedule = $record->employee->workSchedules->firstWhere('weekday', $day);

        return ['id' => $record->id, 'employee_id' => $record->employee_id, 'employee' => trim($record->employee->user->name.' '.($record->employee->user->last_name ?? '')), 'date' => $record->attendance_date->toDateString(), 'checkIn' => $record->check_in ? substr($record->check_in, 0, 5) : null, 'checkOut' => $record->check_out ? substr($record->check_out, 0, 5) : null, 'scheduleStart' => $schedule?->check_in_time ? substr($schedule->check_in_time, 0, 5) : null, 'scheduleEnd' => $schedule?->check_out_time ? substr($schedule->check_out_time, 0, 5) : null, 'delayMinutes' => (int) $record->late_minutes, 'overtimeMinutes' => (int) round(((float) $record->overtime_hours) * 60), 'status' => $this->statusLabel($record->status)];
    }

    /** @return array<string, mixed> */
    private function attendanceDetailRow(Attendance $record): array
    {
        $schedule = $record->employee->workSchedules->firstWhere('weekday', $record->attendance_date->dayOfWeekIso);

        return ['id' => $record->id, 'date' => $record->attendance_date->toDateString(), 'employee' => trim($record->employee->user->name.' '.($record->employee->user->last_name ?? '')), 'employeeNumber' => $record->employee->code, 'checkIn' => $record->check_in ? substr($record->check_in, 0, 5) : null, 'checkOut' => $record->check_out ? substr($record->check_out, 0, 5) : null, 'delayMinutes' => (int) $record->late_minutes, 'overtimeMinutes' => (int) round(((float) $record->overtime_hours) * 60), 'status' => $record->status, 'isManual' => false, 'scheduleStart' => $schedule?->check_in_time ? substr($schedule->check_in_time, 0, 5) : null, 'scheduleEnd' => $schedule?->check_out_time ? substr($schedule->check_out_time, 0, 5) : null, 'isDayOff' => (bool) ($schedule?->is_rest_day ?? false)];
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

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'late' => 'Retardo', 'absent' => 'Falta', 'day_off' => 'Descanso', default => 'A tiempo'
        };
    }
}
