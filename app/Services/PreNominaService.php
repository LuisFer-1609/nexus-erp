<?php

namespace App\Services;

use App\Models\Nexus\Area;
use App\Models\Nexus\Attendance;
use App\Models\Nexus\Employee;
use App\Models\Nexus\Payroll;
use App\Models\Nexus\PayrollPeriod;
use App\Models\Nexus\Position;
use Throwable;

class PreNominaService
{
    /**
     * Obtiene los períodos y el desglose de pre-nómina desde la base de datos.
     *
     * @return array{
     *     selectedPeriodId: string,
     *     periods: list<array{id: string, label: string, status: string, tone: string}>,
     *     currentPeriod: array{
     *         id: string,
     *         label: string,
     *         status: string,
     *         tone: string,
     *         summary: array{employees: int, perceptions: float, deductions: float, net: float},
     *         rows: list<array<string, int|float|string>>
     *     }|null
     * }
     */
    public function getPayrollData(?string $periodId = null): array
    {
        try {
            $periodModels = PayrollPeriod::query()
                ->whereNull(PayrollPeriod::DELETED_AT)
                ->orderByDesc(PayrollPeriod::START_DATE)
                ->get();

            if ($periodModels->isEmpty()) {
                return [
                    'selectedPeriodId' => '',
                    'periods' => [],
                    'currentPeriod' => null,
                ];
            }

            $periods = [];
            foreach ($periodModels as $model) {
                $id = (string) $model->getAttribute(PayrollPeriod::ID);
                $startDate = (string) $model->getAttribute(PayrollPeriod::START_DATE);
                $endDate = (string) $model->getAttribute(PayrollPeriod::END_DATE);
                $rawStatus = (string) $model->getAttribute(PayrollPeriod::STATUS);

                $periods[] = [
                    'id' => $id,
                    'label' => $this->formatPeriodLabel($startDate, $endDate),
                    'status' => ucfirst($rawStatus !== '' ? $rawStatus : 'Borrador'),
                    'tone' => strtolower($rawStatus) === 'cerrado' ? 'green' : 'amber',
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
            $perceptions = 0.0;
            $deductions = 0.0;
            foreach ($rows as $row) {
                $perceptions += (float) $row['perceptions'];
                $deductions += (float) $row['deductions'];
            }

            return [
                'selectedPeriodId' => $selectedMeta['id'],
                'periods' => $periods,
                'currentPeriod' => [
                    'id' => $selectedMeta['id'],
                    'label' => $selectedMeta['label'],
                    'status' => $selectedMeta['status'],
                    'tone' => $selectedMeta['tone'],
                    'summary' => [
                        'employees' => count($rows),
                        'perceptions' => $perceptions,
                        'deductions' => $deductions,
                        'net' => max(0.0, $perceptions - $deductions),
                    ],
                    'rows' => $rows,
                ],
            ];
        } catch (Throwable) {
            return [
                'selectedPeriodId' => '',
                'periods' => [],
                'currentPeriod' => null,
            ];
        }
    }

    /**
     * Obtiene el directorio de empleados activos y catálogo de áreas.
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
     * Obtiene los registros de asistencia vinculados a empleados.
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

    /**
     * Construye las filas de pre-nómina para un período específico.
     *
     * @return list<array<string, int|float|string>>
     */
    private function buildPayrollRows(int $periodId): array
    {
        $employees = Employee::query()
            ->whereNull(Employee::DELETED_AT)
            ->get()
            ->keyBy(Employee::ID);

        $payrolls = Payroll::query()
            ->where(Payroll::PAYROLL_PERIOD_ID, $periodId)
            ->whereNull(Payroll::DELETED_AT)
            ->get();

        $rows = [];
        foreach ($payrolls as $payroll) {
            $empId = (int) $payroll->getAttribute(Payroll::EMPLOYEE_ID);
            $emp = $employees->get($empId);
            $fullName = $emp !== null
                ? trim(((string) $emp->getAttribute(Employee::FIRST_NAME)).' '.((string) $emp->getAttribute(Employee::LAST_NAME)))
                : 'Empleado #'.$empId;
            $dailySalary = $emp !== null ? (float) $emp->getAttribute(Employee::BASE_SALARY) / 15.0 : 500.0;
            $overtimeRate = $emp !== null ? (float) $emp->getAttribute(Employee::OVERTIME_RATE) : 150.0;

            $delays = (int) $payroll->getAttribute(Payroll::LATE_COUNT);
            $absences = (int) $payroll->getAttribute(Payroll::ABSENCE_COUNT);
            $hasIssues = $delays > 0 || $absences > 0;

            $rows[] = [
                'id' => (int) $payroll->getAttribute(Payroll::ID),
                'initials' => $this->initials($fullName),
                'name' => $fullName,
                'dailyRate' => round($dailySalary, 2),
                'hourlyOvertimeRate' => round($overtimeRate, 2),
                'days' => (int) $payroll->getAttribute(Payroll::DAYS_WORKED),
                'delays' => $delays,
                'absences' => $absences,
                'extra' => (float) $payroll->getAttribute(Payroll::OVERTIME_HOURS),
                'perceptions' => (float) $payroll->getAttribute(Payroll::TOTAL_PERCEPTIONS),
                'deductions' => (float) $payroll->getAttribute(Payroll::TOTAL_DEDUCTIONS),
                'net' => (float) $payroll->getAttribute(Payroll::NET_AMOUNT),
                'status' => $hasIssues ? 'Revisar' : 'Correcto',
                'tone' => $hasIssues ? 'amber' : 'green',
            ];
        }

        return $rows;
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
