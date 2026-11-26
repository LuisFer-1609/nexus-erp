<?php

namespace App\Services;

use App\Models\Nexus\Area;
use App\Models\Nexus\Attendance;
use App\Models\Nexus\Employee;
use App\Models\Nexus\Payroll;
use App\Models\Nexus\PayrollConcept;
use App\Models\Nexus\PayrollDetail;
use App\Models\Nexus\PayrollFrequency;
use App\Models\Nexus\PayrollPeriod;
use App\Models\Nexus\Position;
use App\Models\Nexus\User as NexusUser;
use Illuminate\Support\Facades\DB;
use Throwable;

class PreNominaService
{
    /**
     * Obtiene los períodos y el desglose de pre-nómina EXCLUSIVAMENTE desde la base de datos.
     */
    public function getPayrollData(?string $periodId = null, ?string $frequency = null): array
    {
        try {
            $frequencies = [
                ['id' => 'todas', 'name' => 'Todas las frecuencias'],
            ];
            $dbFrequencies = PayrollFrequency::query()
                ->whereNull(PayrollFrequency::DELETED_AT)
                ->orderBy(PayrollFrequency::ID)
                ->get();

            $frequencyMap = [];
            foreach ($dbFrequencies as $freq) {
                $id = (int) $freq->getAttribute(PayrollFrequency::ID);
                $name = (string) $freq->getAttribute(PayrollFrequency::NAME);
                $frequencyMap[$id] = $name;
                $frequencies[] = [
                    'id' => (string) $id,
                    'name' => $name,
                ];
            }

            $periodQuery = PayrollPeriod::query()
                ->whereNull(PayrollPeriod::DELETED_AT)
                ->orderByDesc(PayrollPeriod::START_DATE);

            if ($frequency !== null && $frequency !== '' && $frequency !== 'todas') {
                $periodQuery->where(PayrollPeriod::PAYROLL_FREQUENCY_ID, (int) $frequency);
            }

            $periodModels = $periodQuery->get();

            if ($periodModels->isEmpty()) {
                return [
                    'selectedPeriodId' => '',
                    'selectedFrequency' => $frequency ?? 'todas',
                    'frequencies' => $frequencies,
                    'periods' => [],
                    'currentPeriod' => null,
                ];
            }

            $periods = [];
            foreach ($periodModels as $model) {
                $id = (string) $model->getAttribute(PayrollPeriod::ID);
                $freqId = (int) $model->getAttribute(PayrollPeriod::PAYROLL_FREQUENCY_ID);
                $freqName = $frequencyMap[$freqId] ?? 'Ordinaria';
                $startDate = (string) $model->getAttribute(PayrollPeriod::START_DATE);
                $endDate = (string) $model->getAttribute(PayrollPeriod::END_DATE);
                $paymentDate = (string) ($model->getAttribute(PayrollPeriod::PAYMENT_DATE) ?? $endDate);
                $rawStatus = (string) $model->getAttribute(PayrollPeriod::STATUS);

                $code = 'NOM-'.str_pad($id, 3, '0', STR_PAD_LEFT);

                $periods[] = [
                    'id' => $id,
                    'code' => $code,
                    'label' => $this->formatPeriodLabel($startDate, $endDate),
                    'frequency' => $freqName,
                    'type' => 'Nómina ordinaria',
                    'status' => ucfirst($rawStatus !== '' ? $rawStatus : 'Borrador'),
                    'tone' => strtolower($rawStatus) === 'cerrado' ? 'green' : 'amber',
                    'startDate' => $startDate,
                    'endDate' => $endDate,
                    'paymentDate' => $paymentDate,
                ];
            }

            $selectedMeta = $periods[0];
            if ($periodId !== null && $periodId !== '') {
                foreach ($periods as $candidate) {
                    if ($candidate['id'] === $periodId) {
                        $selectedMeta = $candidate;
                        break;
                    }
                }
            }

            $rows = $this->buildPayrollRows((int) $selectedMeta['id']);
            $summary = $this->summarizeRows($rows);

            return [
                'selectedPeriodId' => $selectedMeta['id'],
                'selectedFrequency' => $frequency ?? 'todas',
                'frequencies' => $frequencies,
                'periods' => $periods,
                'currentPeriod' => [
                    'id' => $selectedMeta['id'],
                    'code' => $selectedMeta['code'],
                    'label' => $selectedMeta['label'],
                    'frequency' => $selectedMeta['frequency'],
                    'type' => $selectedMeta['type'],
                    'status' => $selectedMeta['status'],
                    'tone' => $selectedMeta['tone'],
                    'summary' => $summary,
                    'rows' => $rows,
                ],
            ];
        } catch (Throwable) {
            return [
                'selectedPeriodId' => '',
                'selectedFrequency' => 'todas',
                'frequencies' => [['id' => 'todas', 'name' => 'Todas las frecuencias']],
                'periods' => [],
                'currentPeriod' => null,
            ];
        }
    }

    /**
     * Guarda un ajuste manual por concepto persistiendo en la tabla payrolls y payroll_details.
     */
    public function saveAdjustment(int $payrollId, array $data): bool
    {
        try {
            DB::beginTransaction();

            $payroll = Payroll::query()->find($payrollId);
            if (! $payroll) {
                DB::rollBack();
                return false;
            }

            if (isset($data['days'])) {
                $payroll->setAttribute(Payroll::DAYS_WORKED, (int) $data['days']);
            }
            if (isset($data['delays'])) {
                $payroll->setAttribute(Payroll::LATE_COUNT, (int) $data['delays']);
            }
            if (isset($data['absences'])) {
                $payroll->setAttribute(Payroll::ABSENCE_COUNT, (int) $data['absences']);
            }
            if (isset($data['extra'])) {
                $payroll->setAttribute(Payroll::OVERTIME_HOURS, (float) $data['extra']);
            }
            if (isset($data['perceptions'])) {
                $payroll->setAttribute(Payroll::TOTAL_PERCEPTIONS, (float) $data['perceptions']);
            }
            if (isset($data['deductions'])) {
                $payroll->setAttribute(Payroll::TOTAL_DEDUCTIONS, (float) $data['deductions']);
            }
            if (isset($data['net'])) {
                $payroll->setAttribute(Payroll::NET_AMOUNT, (float) $data['net']);
            }

            $payroll->save();

            // Si hay notas de ajuste o bonos, registrar detalle
            if (!empty($data['adjustmentReason']) || !empty($data['customDeductions']) || !empty($data['bonus'])) {
                $conceptId = 1; // Concepto estándar o bono
                try {
                    $firstConcept = PayrollConcept::query()->first();
                    if ($firstConcept) {
                        $conceptId = (int) $firstConcept->getAttribute(PayrollConcept::ID);
                    }
                } catch (Throwable) {}

                PayrollDetail::query()->create([
                    'payroll_id' => $payrollId,
                    'payroll_concept_id' => $conceptId,
                    'amount' => (float) ($data['customDeductions'] ?? $data['bonus'] ?? 0),
                    'is_manual_adjustment' => 1,
                    'notes' => (string) ($data['adjustmentReason'] ?? 'Ajuste manual aplicado desde pre-nómina'),
                ]);
            }

            DB::commit();
            return true;
        } catch (Throwable) {
            DB::rollBack();
            return false;
        }
    }

    /**
     * Recalcula automáticamente la pre-nómina de un período a partir de los registros de asistencia reales.
     */
    public function recalculatePeriod(int $periodId): bool
    {
        try {
            $period = PayrollPeriod::query()->find($periodId);
            if (! $period) {
                return false;
            }

            $startDate = (string) $period->getAttribute(PayrollPeriod::START_DATE);
            $endDate = (string) $period->getAttribute(PayrollPeriod::END_DATE);

            $payrolls = Payroll::query()->where(Payroll::PAYROLL_PERIOD_ID, $periodId)->get();
            foreach ($payrolls as $payroll) {
                $empId = (int) $payroll->getAttribute(Payroll::EMPLOYEE_ID);
                $emp = Employee::query()->find($empId);
                $dailySalary = $emp ? (float) $emp->getAttribute(Employee::BASE_SALARY) / 30.0 : 500.0;
                $otRate = $emp ? (float) $emp->getAttribute(Employee::OVERTIME_RATE) : 150.0;

                // Contar asistencias reales en el rango de fechas
                $attendances = Attendance::query()
                    ->where(Attendance::EMPLOYEE_ID, $empId)
                    ->whereBetween(Attendance::DATE, [$startDate, $endDate])
                    ->get();

                $daysWorked = $attendances->count() > 0 ? $attendances->count() : 15;
                $delays = $attendances->where('late_minutes', '>', 0)->count();
                $extraHours = (float) $attendances->sum('overtime_hours');

                $perceptions = round(($daysWorked * $dailySalary) + ($extraHours * $otRate), 2);
                $imss = round($perceptions * 0.02725, 2);
                $isr = round(($perceptions - $imss) * 0.08, 2);
                $deductions = round(($delays * 50) + $imss + $isr, 2);
                $net = max(0.0, round($perceptions - $deductions, 2));

                $payroll->setAttribute(Payroll::DAYS_WORKED, $daysWorked);
                $payroll->setAttribute(Payroll::LATE_COUNT, $delays);
                $payroll->setAttribute(Payroll::OVERTIME_HOURS, $extraHours);
                $payroll->setAttribute(Payroll::TOTAL_PERCEPTIONS, $perceptions);
                $payroll->setAttribute(Payroll::TOTAL_DEDUCTIONS, $deductions);
                $payroll->setAttribute(Payroll::NET_AMOUNT, $net);
                $payroll->save();
            }

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Crea un nuevo período de pre-nómina en la base de datos y genera sus registros de nómina.
     */
    public function createPeriod(array $data): ?int
    {
        try {
            DB::beginTransaction();

            $frequencyId = 2; // Default Quincenal
            if (isset($data['frequency'])) {
                $freqName = strtolower((string) $data['frequency']);
                if (str_contains($freqName, 'seman')) $frequencyId = 1;
                elseif (str_contains($freqName, 'mens')) $frequencyId = 3;
            }

            $companyId = 1;
            $period = PayrollPeriod::query()->create([
                'company_id' => $companyId,
                'payroll_frequency_id' => $frequencyId,
                'start_date' => $data['startDate'],
                'end_date' => $data['endDate'],
                'payment_date' => $data['paymentDate'] ?? $data['endDate'],
                'status' => 'Borrador',
            ]);

            $periodId = (int) $period->getAttribute('id');

            // Crear registros de nómina iniciales para los empleados activos
            $activeEmployees = Employee::query()->where(Employee::IS_ACTIVE, 1)->get();
            foreach ($activeEmployees as $emp) {
                $baseSalary = (float) $emp->getAttribute(Employee::BASE_SALARY);
                $dailyRate = $baseSalary > 0 ? $baseSalary / 30.0 : 500.0;
                $days = $frequencyId === 1 ? 6 : ($frequencyId === 3 ? 30 : 15);
                $perceptions = round($dailyRate * $days, 2);
                $imss = round($perceptions * 0.02725, 2);
                $isr = round(($perceptions - $imss) * 0.075, 2);
                $deductions = round($imss + $isr, 2);
                $net = max(0.0, round($perceptions - $deductions, 2));

                Payroll::query()->create([
                    'payroll_period_id' => $periodId,
                    'employee_id' => (int) $emp->getAttribute('id'),
                    'days_worked' => $days,
                    'late_count' => 0,
                    'absence_count' => 0,
                    'overtime_hours' => 0,
                    'total_perceptions' => $perceptions,
                    'total_deductions' => $deductions,
                    'net_amount' => $net,
                ]);
            }

            DB::commit();
            return $periodId;
        } catch (Throwable) {
            DB::rollBack();
            return null;
        }
    }

    /**
     * Cambia el estado del período en la base de datos (Borrador, En Revisión, Aprobado RH, Cerrado).
     */
    public function updatePeriodStatus(int $periodId, string $status): bool
    {
        try {
            $period = PayrollPeriod::query()->find($periodId);
            if ($period) {
                $period->setAttribute(PayrollPeriod::STATUS, $status);
                return $period->save();
            }
            return false;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Construye las filas de pre-nómina para un período específico consultando la BD.
     */
    private function buildPayrollRows(int $periodId): array
    {
        $employees = Employee::query()
            ->whereNull(Employee::DELETED_AT)
            ->get()
            ->keyBy(Employee::ID);

        $areaMap = Area::query()
            ->whereNull(Area::DELETED_AT)
            ->pluck(Area::NAME, Area::ID)
            ->all();

        $payrolls = Payroll::query()
            ->where(Payroll::PAYROLL_PERIOD_ID, $periodId)
            ->whereNull(Payroll::DELETED_AT)
            ->get();

        if ($payrolls->isEmpty()) {
            return [];
        }

        $rows = [];
        foreach ($payrolls as $payroll) {
            $payrollId = (int) $payroll->getAttribute(Payroll::ID);
            $empId = (int) $payroll->getAttribute(Payroll::EMPLOYEE_ID);
            $emp = $employees->get($empId);

            $fullName = $emp !== null
                ? trim(((string) $emp->getAttribute(Employee::FIRST_NAME)).' '.((string) $emp->getAttribute(Employee::LAST_NAME)))
                : 'Empleado #'.$empId;

            $areaId = $emp !== null ? (int) $emp->getAttribute(Employee::AREA_ID) : 0;
            $areaName = (string) ($areaMap[$areaId] ?? 'General');

            $baseSalary = $emp !== null ? (float) $emp->getAttribute(Employee::BASE_SALARY) : 0.0;
            $dailySalary = $baseSalary > 0 ? $baseSalary / 30.0 : 0.0;
            $overtimeRate = $emp !== null ? (float) $emp->getAttribute(Employee::OVERTIME_RATE) : 0.0;

            $delays = (int) $payroll->getAttribute(Payroll::LATE_COUNT);
            $absences = (int) $payroll->getAttribute(Payroll::ABSENCE_COUNT);
            $extra = (float) $payroll->getAttribute(Payroll::OVERTIME_HOURS);
            $daysWorked = (int) $payroll->getAttribute(Payroll::DAYS_WORKED);

            $perceptions = (float) $payroll->getAttribute(Payroll::TOTAL_PERCEPTIONS);
            $deductions = (float) $payroll->getAttribute(Payroll::TOTAL_DEDUCTIONS);
            $net = (float) $payroll->getAttribute(Payroll::NET_AMOUNT);

            $salaryPart = round($dailySalary * $daysWorked, 2);
            $overtimePart = round($extra * $overtimeRate, 2);
            $imss = round($perceptions * 0.02725, 2);
            $isr = round(max(0.0, $deductions - $imss), 2);

            $hasIssues = $delays > 0 || $absences > 0;

            $rows[] = [
                'id' => $payrollId,
                'employeeId' => $empId,
                'initials' => $this->initials($fullName),
                'name' => $fullName,
                'area' => $areaName,
                'dailyRate' => round($dailySalary, 2),
                'hourlyOvertimeRate' => round($overtimeRate, 2),
                'days' => $daysWorked,
                'delays' => $delays,
                'absences' => $absences,
                'extra' => $extra,
                'concepts' => [
                    'salary' => $salaryPart,
                    'overtime' => $overtimePart,
                    'bonus' => max(0.0, round($perceptions - $salaryPart - $overtimePart, 2)),
                    'delaysPenalty' => round($delays * 50, 2),
                    'absencesPenalty' => round($absences * $dailySalary, 2),
                    'imss' => $imss,
                    'isr' => $isr,
                ],
                'perceptions' => $perceptions,
                'deductions' => $deductions,
                'isr' => $isr,
                'imss' => $imss,
                'net' => $net,
                'status' => $hasIssues ? 'Revisar' : 'Correcto',
                'tone' => $hasIssues ? 'amber' : 'green',
            ];
        }

        return $rows;
    }

    private function summarizeRows(array $rows): array
    {
        $perceptions = 0.0;
        $deductions = 0.0;
        $isr = 0.0;
        $imss = 0.0;

        foreach ($rows as $row) {
            $perceptions += (float) ($row['perceptions'] ?? 0);
            $deductions += (float) ($row['deductions'] ?? 0);
            $isr += (float) ($row['isr'] ?? 0);
            $imss += (float) ($row['imss'] ?? 0);
        }

        return [
            'employees' => count($rows),
            'perceptions' => round($perceptions, 2),
            'deductions' => round($deductions, 2),
            'isr' => round($isr, 2),
            'imss' => round($imss, 2),
            'net' => max(0.0, round($perceptions - $deductions, 2)),
        ];
    }

    public function getEmployeesData(): array
    {
        try {
            $areaMap = Area::query()->whereNull(Area::DELETED_AT)->pluck(Area::NAME, Area::ID)->all();
            $positionMap = Position::query()->whereNull(Position::DELETED_AT)->pluck(Position::NAME, Position::ID)->all();
            $employees = Employee::query()->with('user')->whereNull(Employee::DELETED_AT)->get();

            $list = [];
            foreach ($employees as $emp) {
                $firstName = (string) ($emp->user->getAttribute(NexusUser::NAME) ?? '');
                $lastName = (string) ($emp->user->getAttribute(NexusUser::LAST_NAME) ?? '');
                $fullName = trim($firstName.' '.$lastName);
                $areaId = (int) $emp->getAttribute(Employee::AREA_ID);
                $positionId = (int) $emp->getAttribute(Employee::POSITION_ID);
                $isActive = (string) $emp->getAttribute(Employee::STATUS) === 'activo';

                $list[] = [
                    'id' => (int) $emp->getAttribute(Employee::ID),
                    'initials' => $this->initials($fullName),
                    'name' => $fullName,
                    'area' => (string) ($areaMap[$areaId] ?? 'General'),
                    'role' => (string) ($positionMap[$positionId] ?? 'Colaborador'),
                    'contact' => (string) ($emp->user->getAttribute(NexusUser::PHONE) ?? '—'),
                    'date' => $emp->hire_date?->toDateString() ?? '—',
                    'status' => $isActive ? 'Activo' : 'Inactivo',
                    'schedule' => '09:00–18:00',
                ];
            }

            return ['areas' => array_values(array_map('strval', $areaMap)), 'employees' => $list];
        } catch (Throwable) {
            return ['areas' => [], 'employees' => []];
        }
    }

    public function getAttendancesData(): array
    {
        try {
            $employees = Employee::query()->whereNull(Employee::DELETED_AT)->get()->keyBy(Employee::ID);
            $areaMap = Area::query()->whereNull(Area::DELETED_AT)->pluck(Area::NAME, Area::ID)->all();
            $records = Attendance::query()->whereNull(Attendance::DELETED_AT)->orderByDesc(Attendance::DATE)->limit(50)->get();

            $rows = [];
            foreach ($records as $record) {
                $empId = (int) $record->getAttribute(Attendance::EMPLOYEE_ID);
                $emp = $employees->get($empId);
                $fullName = $emp !== null
                    ? trim(((string) $emp->getAttribute(Employee::FIRST_NAME)).' '.((string) $emp->getAttribute(Employee::LAST_NAME)))
                    : 'Empleado #'.$empId;
                $areaId = $emp !== null ? (int) $emp->getAttribute(Employee::AREA_ID) : 0;
                $lateMinutes = (int) $record->getAttribute(Attendance::LATE_MINUTES);
                $overtime = (float) $record->getAttribute(Attendance::OVERTIME_HOURS);
                $rawStatus = (string) $record->getAttribute(Attendance::STATUS);

                $rows[] = [
                    'id' => (int) $record->getAttribute(Attendance::ID),
                    'initials' => $this->initials($fullName),
                    'name' => $fullName,
                    'area' => (string) ($areaMap[$areaId] ?? 'General'),
                    'entry' => (string) ($record->getAttribute(Attendance::CHECK_IN) ?? '—'),
                    'exit' => (string) ($record->getAttribute(Attendance::CHECK_OUT) ?? '—'),
                    'delay' => $lateMinutes > 0 ? $lateMinutes.' min' : '—',
                    'extra' => $overtime > 0 ? $overtime.' h' : '—',
                    'status' => ucfirst($rawStatus !== '' ? $rawStatus : 'Presente'),
                    'tone' => $lateMinutes > 0 ? 'amber' : 'green',
                ];
            }

            return ['attendances' => $rows];
        } catch (Throwable) {
            return ['attendances' => []];
        }
    }

    private function formatPeriodLabel(string $start, string $end): string
    {
        if ($start === '' || $end === '') return 'Período actual';
        return $start.' — '.$end;
    }

    private function initials(string $name): string
    {
        $parts = array_values(array_filter(explode(' ', trim($name))));
        $first = isset($parts[0]) ? mb_substr($parts[0], 0, 1) : 'U';
        $second = isset($parts[1]) ? mb_substr($parts[1], 0, 1) : '';
        return mb_strtoupper($first.$second);
    }
}
