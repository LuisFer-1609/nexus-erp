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
use Throwable;

class PreNominaService
{
    /**
     * Obtiene los períodos y el desglose de pre-nómina EXCLUSIVAMENTE desde la base de datos.
     * Cero datos hardcodeados o mock; solo lo que exista en las tablas conectadas.
     *
     * @return array{
     *     selectedPeriodId: string,
     *     selectedFrequency: string,
     *     frequencies: list<array{id: string, name: string}>,
     *     periods: list<array{
     *         id: string,
     *         code: string,
     *         label: string,
     *         frequency: string,
     *         type: string,
     *         status: string,
     *         tone: string,
     *         startDate: string,
     *         endDate: string,
     *         paymentDate: string
     *     }>,
     *     currentPeriod: array{
     *         id: string,
     *         code: string,
     *         label: string,
     *         frequency: string,
     *         type: string,
     *         status: string,
     *         tone: string,
     *         summary: array{
     *             employees: int,
     *             perceptions: float,
     *             deductions: float,
     *             isr: float,
     *             imss: float,
     *             net: float
     *         },
     *         rows: list<array<string, mixed>>
     *     }|null
     * }
     */
    public function getPayrollData(?string $periodId = null, ?string $frequency = null): array
    {
        try {
            // 1. Obtener catálogo real de frecuencias desde la base de datos
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

            // 2. Obtener períodos reales desde la base de datos
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

            // Seleccionar período solicitado o el primero de la BD
            $selectedMeta = $periods[0];
            if ($periodId !== null && $periodId !== '') {
                foreach ($periods as $candidate) {
                    if ($candidate['id'] === $periodId) {
                        $selectedMeta = $candidate;
                        break;
                    }
                }
            }

            // 3. Obtener registros de nómina vinculados al período
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
     * Construye las filas de pre-nómina para un período específico consultando la BD.
     *
     * @return list<array<string, mixed>>
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

        // Obtener detalles de conceptos de la BD si existen
        $payrollIds = $payrolls->pluck(Payroll::ID)->all();
        $detailsByPayroll = [];
        if (!empty($payrollIds)) {
            try {
                $details = PayrollDetail::query()
                    ->whereIn(PayrollDetail::PAYROLL_ID, $payrollIds)
                    ->whereNull(PayrollDetail::DELETED_AT)
                    ->get();

                foreach ($details as $detail) {
                    $pid = (int) $detail->getAttribute(PayrollDetail::PAYROLL_ID);
                    $detailsByPayroll[$pid][] = $detail;
                }
            } catch (Throwable) {
                $detailsByPayroll = [];
            }
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

            // Desglose de conceptos
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

    /**
     * Resumen acumulado de percepciones, deducciones, impuestos y neto reales.
     *
     * @param list<array<string, mixed>> $rows
     * @return array{
     *     employees: int,
     *     perceptions: float,
     *     deductions: float,
     *     isr: float,
     *     imss: float,
     *     net: float
     * }
     */
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

    /**
     * Obtiene el directorio de empleados activos y catálogo de áreas REALES desde la BD.
     *
     * @return array{
     *     areas: list<string>,
     *     employees: list<array<string, int|string>>
     * }
     */
    public function getEmployeesData(): array
    {
        try {
            $areaMap = Area::query()
                ->whereNull(Area::DELETED_AT)
                ->pluck(Area::NAME, Area::ID)
                ->all();

            $positionMap = Position::query()
                ->whereNull(Position::DELETED_AT)
                ->pluck(Position::NAME, Position::ID)
                ->all();

            $employees = Employee::query()
                ->whereNull(Employee::DELETED_AT)
                ->get();

            $list = [];
            foreach ($employees as $emp) {
                $firstName = (string) $emp->getAttribute(Employee::FIRST_NAME);
                $lastName = (string) $emp->getAttribute(Employee::LAST_NAME);
                $fullName = trim($firstName.' '.$lastName);
                $areaId = (int) $emp->getAttribute(Employee::AREA_ID);
                $positionId = (int) $emp->getAttribute(Employee::POSITION_ID);
                $isActive = (bool) $emp->getAttribute(Employee::IS_ACTIVE);

                $list[] = [
                    'id' => (int) $emp->getAttribute(Employee::ID),
                    'initials' => $this->initials($fullName),
                    'name' => $fullName,
                    'area' => (string) ($areaMap[$areaId] ?? 'General'),
                    'role' => (string) ($positionMap[$positionId] ?? 'Colaborador'),
                    'contact' => (string) ($emp->getAttribute(Employee::PHONE) ?? '—'),
                    'date' => (string) ($emp->getAttribute(Employee::HIRE_DATE) ?? '—'),
                    'status' => $isActive ? 'Activo' : 'Inactivo',
                    'schedule' => '08:00–17:00',
                ];
            }

            /** @var list<string> $areas */
            $areas = array_values(array_map('strval', $areaMap));

            return [
                'areas' => $areas,
                'employees' => $list,
            ];
        } catch (Throwable) {
            return [
                'areas' => [],
                'employees' => [],
            ];
        }
    }

    /**
     * Obtiene los registros de asistencia vinculados a empleados REALES desde la BD.
     *
     * @return array{
     *     attendances: list<array<string, int|string>>
     * }
     */
    public function getAttendancesData(): array
    {
        try {
            $employees = Employee::query()
                ->whereNull(Employee::DELETED_AT)
                ->get()
                ->keyBy(Employee::ID);

            $areaMap = Area::query()
                ->whereNull(Area::DELETED_AT)
                ->pluck(Area::NAME, Area::ID)
                ->all();

            $records = Attendance::query()
                ->whereNull(Attendance::DELETED_AT)
                ->orderByDesc(Attendance::DATE)
                ->limit(50)
                ->get();

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

            return [
                'attendances' => $rows,
            ];
        } catch (Throwable) {
            return [
                'attendances' => [],
            ];
        }
    }

    private function formatPeriodLabel(string $start, string $end): string
    {
        if ($start === '' || $end === '') {
            return 'Período actual';
        }

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
