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
     * Obtiene los períodos y el desglose de pre-nómina desde la base de datos o fallback dinámico.
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
        $frequencies = [
            ['id' => 'todas', 'name' => 'Todas las frecuencias'],
            ['id' => 'semanal', 'name' => 'Semanal'],
            ['id' => 'quincenal', 'name' => 'Quincenal'],
            ['id' => 'mensual', 'name' => 'Mensual'],
        ];

        try {
            $periodQuery = PayrollPeriod::query()
                ->whereNull(PayrollPeriod::DELETED_AT)
                ->orderByDesc(PayrollPeriod::START_DATE);

            $periodModels = $periodQuery->get();

            if ($periodModels->isEmpty()) {
                return $this->getDynamicMockPayrollData($periodId, $frequency, $frequencies);
            }

            // Mapeo de frecuencias de la BD
            $frequencyMap = [];
            try {
                $frequencyMap = PayrollFrequency::query()
                    ->whereNull(PayrollFrequency::DELETED_AT)
                    ->pluck(PayrollFrequency::NAME, PayrollFrequency::ID)
                    ->all();
            } catch (Throwable) {
                $frequencyMap = [1 => 'Semanal', 2 => 'Quincenal', 3 => 'Mensual'];
            }

            $periods = [];
            foreach ($periodModels as $model) {
                $id = (string) $model->getAttribute(PayrollPeriod::ID);
                $freqId = (int) $model->getAttribute(PayrollPeriod::PAYROLL_FREQUENCY_ID);
                $freqName = $frequencyMap[$freqId] ?? 'Quincenal';
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
            if (empty($rows)) {
                $rows = $this->generateCalculatedRowsForFrequency($selectedMeta['frequency']);
            }

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
            return $this->getDynamicMockPayrollData($periodId, $frequency, $frequencies);
        }
    }

    /**
     * Datos dinámicos cuando la BD aún no cuenta con registros de períodos.
     *
     * @param list<array{id: string, name: string}> $frequencies
     * @return array{
     *     selectedPeriodId: string,
     *     selectedFrequency: string,
     *     frequencies: list<array{id: string, name: string}>,
     *     periods: list<array<string, string>>,
     *     currentPeriod: array<string, mixed>
     * }
     */
    public function getDynamicMockPayrollData(?string $periodId, ?string $frequency, array $frequencies): array
    {
        $mockPeriods = [
            [
                'id' => '1',
                'code' => 'NOM-225',
                'label' => '16 sept — 30 sept 2026',
                'frequency' => 'Quincenal',
                'type' => 'Nómina ordinaria',
                'status' => 'Borrador',
                'tone' => 'amber',
                'startDate' => '2026-09-16',
                'endDate' => '2026-09-30',
                'paymentDate' => '2026-09-30',
            ],
            [
                'id' => '2',
                'code' => 'NOM-214',
                'label' => '01 sept — 15 sept 2026',
                'frequency' => 'Quincenal',
                'type' => 'Nómina ordinaria',
                'status' => 'Cerrado',
                'tone' => 'green',
                'startDate' => '2026-09-01',
                'endDate' => '2026-09-15',
                'paymentDate' => '2026-09-15',
            ],
            [
                'id' => '3',
                'code' => 'NOM-213',
                'label' => '24 ago — 29 ago 2026',
                'frequency' => 'Semanal',
                'type' => 'Nómina ordinaria',
                'status' => 'Cerrado',
                'tone' => 'green',
                'startDate' => '2026-08-24',
                'endDate' => '2026-08-29',
                'paymentDate' => '2026-08-29',
            ],
            [
                'id' => '4',
                'code' => 'NOM-210',
                'label' => '01 ago — 31 ago 2026',
                'frequency' => 'Mensual',
                'type' => 'Nómina ordinaria',
                'status' => 'Cerrado',
                'tone' => 'green',
                'startDate' => '2026-08-01',
                'endDate' => '2026-08-31',
                'paymentDate' => '2026-08-31',
            ],
            [
                'id' => '5',
                'code' => 'NOM-230',
                'label' => 'Finiquito Sergio García',
                'frequency' => 'Otra Periodicidad',
                'type' => 'Finiquito Ordinario',
                'status' => 'Cerrado',
                'tone' => 'green',
                'startDate' => '2026-10-15',
                'endDate' => '2026-10-19',
                'paymentDate' => '2026-10-19',
            ],
        ];

        $selectedMeta = $mockPeriods[0];
        if ($periodId !== null && $periodId !== '') {
            foreach ($mockPeriods as $candidate) {
                if ($candidate['id'] === $periodId) {
                    $selectedMeta = $candidate;
                    break;
                }
            }
        }

        $rows = $this->generateCalculatedRowsForFrequency($selectedMeta['frequency']);
        $summary = $this->summarizeRows($rows);

        return [
            'selectedPeriodId' => $selectedMeta['id'],
            'selectedFrequency' => $frequency ?? 'todas',
            'frequencies' => $frequencies,
            'periods' => $mockPeriods,
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
    }

    /**
     * Calcula percepciones, deducciones (ISR, IMSS, retardos, faltas) por empleado.
     *
     * @return list<array<string, mixed>>
     */
    public function generateCalculatedRowsForFrequency(string $frequency): array
    {
        $maxDays = match (strtolower($frequency)) {
            'semanal' => 6,
            'mensual' => 30,
            default => 15,
        };

        $baseEmployees = [
            ['id' => 1, 'name' => 'Daniel Ortiz', 'area' => 'Ventas', 'baseSalary' => 18000, 'overtimeRate' => 160, 'days' => min(10, $maxDays), 'delays' => 0, 'absences' => 0, 'extra' => 2.5, 'bonus' => 500],
            ['id' => 2, 'name' => 'Fernanda Cruz', 'area' => 'Recursos Humanos', 'baseSalary' => 16500, 'overtimeRate' => 140, 'days' => min(9, $maxDays), 'delays' => 2, 'absences' => 0, 'extra' => 0, 'bonus' => 0],
            ['id' => 3, 'name' => 'Jorge Medina', 'area' => 'TI / Sistemas', 'baseSalary' => 22000, 'overtimeRate' => 200, 'days' => min(8, $maxDays), 'delays' => 1, 'absences' => 1, 'extra' => 4.0, 'bonus' => 800],
            ['id' => 4, 'name' => 'Andrea Salas', 'area' => 'Operaciones', 'baseSalary' => 19500, 'overtimeRate' => 170, 'days' => min(10, $maxDays), 'delays' => 0, 'absences' => 0, 'extra' => 1.0, 'bonus' => 400],
            ['id' => 5, 'name' => 'Raúl Pérez', 'area' => 'Administración', 'baseSalary' => 17000, 'overtimeRate' => 150, 'days' => min(10, $maxDays), 'delays' => 0, 'absences' => 0, 'extra' => 0, 'bonus' => 0],
            ['id' => 6, 'name' => 'Mariana Soto', 'area' => 'Marketing', 'baseSalary' => 17500, 'overtimeRate' => 155, 'days' => min(9, $maxDays), 'delays' => 1, 'absences' => 0, 'extra' => 3.0, 'bonus' => 300],
            ['id' => 7, 'name' => 'Carlos Valdés', 'area' => 'Logística', 'baseSalary' => 15000, 'overtimeRate' => 130, 'days' => min(7, $maxDays), 'delays' => 3, 'absences' => 1, 'extra' => 0, 'bonus' => 0],
        ];

        $rows = [];
        foreach ($baseEmployees as $emp) {
            $dailyRate = round($emp['baseSalary'] / 30.0, 2);
            $hourlyOvertimeRate = (float) $emp['overtimeRate'];

            // 1. Percepciones por concepto
            $sueldoBase = round($dailyRate * $emp['days'], 2);
            $horasExtraMonto = round($emp['extra'] * $hourlyOvertimeRate, 2);
            $bono = (float) $emp['bonus'];
            $totalPerceptions = round($sueldoBase + $horasExtraMonto + $bono, 2);

            // 2. Deducciones por concepto
            // Retardos: 3 retardos = sanción de $150 MXN o $50 cada uno
            $retardoMonto = round($emp['delays'] * 50.0, 2);
            // Falta: descuento de día completo de sueldo
            $faltaMonto = round($emp['absences'] * $dailyRate, 2);
            // IMSS obrero estimado: 2.725% de percepciones base
            $imss = round($sueldoBase * 0.02725, 2);
            // ISR estimado: tabulador escalonado simplificado (~6.5% a ~9.5%)
            $isrRate = $totalPerceptions > 10000 ? 0.088 : 0.055;
            $isr = round(($totalPerceptions - $imss) * $isrRate, 2);

            $totalDeductions = round($retardoMonto + $faltaMonto + $imss + $isr, 2);
            $net = max(0.0, round($totalPerceptions - $totalDeductions, 2));

            $hasIncidences = $emp['delays'] > 0 || $emp['absences'] > 0;

            $rows[] = [
                'id' => $emp['id'],
                'initials' => $this->initials($emp['name']),
                'name' => $emp['name'],
                'area' => $emp['area'],
                'dailyRate' => $dailyRate,
                'hourlyOvertimeRate' => $hourlyOvertimeRate,
                'days' => $emp['days'],
                'delays' => $emp['delays'],
                'absences' => $emp['absences'],
                'extra' => $emp['extra'],
                // Conceptos detallados de percepciones
                'concepts' => [
                    'salary' => $sueldoBase,
                    'overtime' => $horasExtraMonto,
                    'bonus' => $bono,
                    'delaysPenalty' => $retardoMonto,
                    'absencesPenalty' => $faltaMonto,
                    'imss' => $imss,
                    'isr' => $isr,
                ],
                'perceptions' => $totalPerceptions,
                'deductions' => $totalDeductions,
                'isr' => $isr,
                'imss' => $imss,
                'net' => $net,
                'status' => $hasIncidences ? 'Revisar' : 'Correcto',
                'tone' => $hasIncidences ? 'amber' : 'green',
            ];
        }

        return $rows;
    }

    /**
     * Resumen acumulado de percepciones, deducciones, impuestos y neto.
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

            if ($employees->isEmpty()) {
                return $this->getDynamicMockEmployees();
            }

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
            return $this->getDynamicMockEmployees();
        }
    }

    /**
     * Empleados mock para visualización si la BD está vacía.
     */
    private function getDynamicMockEmployees(): array
    {
        $areas = ['Dirección General', 'Recursos Humanos', 'Ventas', 'Operaciones', 'TI / Sistemas', 'Administración'];
        $employees = [
            ['id' => 1, 'initials' => 'DO', 'name' => 'Daniel Ortiz', 'area' => 'Ventas', 'role' => 'Ejecutivo Comercial Senior', 'contact' => '+52 81 1234 5678', 'date' => '2022-03-15', 'status' => 'Activo', 'schedule' => '08:00–17:00'],
            ['id' => 2, 'initials' => 'FC', 'name' => 'Fernanda Cruz', 'area' => 'Recursos Humanos', 'role' => 'Especialista en Nóminas', 'contact' => '+52 81 2345 6789', 'date' => '2021-08-01', 'status' => 'Activo', 'schedule' => '09:00–18:00'],
            ['id' => 3, 'initials' => 'JM', 'name' => 'Jorge Medina', 'area' => 'TI / Sistemas', 'role' => 'Líder Técnico Full-Stack', 'contact' => '+52 81 3456 7890', 'date' => '2023-01-10', 'status' => 'Activo', 'schedule' => '08:00–17:00'],
            ['id' => 4, 'initials' => 'AS', 'name' => 'Andrea Salas', 'area' => 'Operaciones', 'role' => 'Coordinadora de Procesos', 'contact' => '+52 81 4567 8901', 'date' => '2020-11-20', 'status' => 'Activo', 'schedule' => '08:30–17:30'],
            ['id' => 5, 'initials' => 'RP', 'name' => 'Raúl Pérez', 'area' => 'Administración', 'role' => 'Contador General', 'contact' => '+52 81 5678 9012', 'date' => '2019-05-04', 'status' => 'Activo', 'schedule' => '08:00–17:00'],
            ['id' => 6, 'initials' => 'MS', 'name' => 'Mariana Soto', 'area' => 'Marketing', 'role' => 'Diseñadora Visual', 'contact' => '+52 81 6789 0123', 'date' => '2024-02-15', 'status' => 'Activo', 'schedule' => '09:00–18:00'],
            ['id' => 7, 'initials' => 'CV', 'name' => 'Carlos Valdés', 'area' => 'Logística', 'role' => 'Supervisor de Almacén', 'contact' => '+52 81 7890 1234', 'date' => '2023-09-01', 'status' => 'Activo', 'schedule' => '07:00–16:00'],
        ];

        return [
            'areas' => $areas,
            'employees' => $employees,
        ];
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

            if ($records->isEmpty()) {
                return $this->getDynamicMockAttendances();
            }

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
            return $this->getDynamicMockAttendances();
        }
    }

    private function getDynamicMockAttendances(): array
    {
        return [
            'attendances' => [
                ['id' => 1, 'initials' => 'DO', 'name' => 'Daniel Ortiz', 'area' => 'Ventas', 'entry' => '07:58', 'exit' => '17:35', 'delay' => '—', 'extra' => '2.5 h', 'status' => 'Presente', 'tone' => 'green'],
                ['id' => 2, 'initials' => 'FC', 'name' => 'Fernanda Cruz', 'area' => 'Recursos Humanos', 'entry' => '09:22', 'exit' => '18:05', 'delay' => '22 min', 'extra' => '—', 'status' => 'Retardo', 'tone' => 'amber'],
                ['id' => 3, 'initials' => 'JM', 'name' => 'Jorge Medina', 'area' => 'TI / Sistemas', 'entry' => '08:15', 'exit' => '19:45', 'delay' => '15 min', 'extra' => '4.0 h', 'status' => 'Horas Extra', 'tone' => 'cyan'],
                ['id' => 4, 'initials' => 'AS', 'name' => 'Andrea Salas', 'area' => 'Operaciones', 'entry' => '08:29', 'exit' => '17:31', 'delay' => '—', 'extra' => '1.0 h', 'status' => 'Presente', 'tone' => 'green'],
                ['id' => 5, 'initials' => 'RP', 'name' => 'Raúl Pérez', 'area' => 'Administración', 'entry' => '07:55', 'exit' => '17:02', 'delay' => '—', 'extra' => '—', 'status' => 'Presente', 'tone' => 'green'],
                ['id' => 6, 'initials' => 'MS', 'name' => 'Mariana Soto', 'area' => 'Marketing', 'entry' => '09:12', 'exit' => '18:10', 'delay' => '12 min', 'extra' => '3.0 h', 'status' => 'Retardo', 'tone' => 'amber'],
                ['id' => 7, 'initials' => 'CV', 'name' => 'Carlos Valdés', 'area' => 'Logística', 'entry' => '07:45', 'exit' => '16:00', 'delay' => '—', 'extra' => '—', 'status' => 'Falta Justificada', 'tone' => 'amber'],
            ],
        ];
    }

    /**
     * Construye las filas de pre-nómina para un período específico.
     *
     * @return list<array<string, mixed>>
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

        if ($payrolls->isEmpty()) {
            return [];
        }

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
            $extra = (float) $payroll->getAttribute(Payroll::OVERTIME_HOURS);
            $daysWorked = (int) $payroll->getAttribute(Payroll::DAYS_WORKED);

            $perceptions = (float) $payroll->getAttribute(Payroll::TOTAL_PERCEPTIONS);
            $deductions = (float) $payroll->getAttribute(Payroll::TOTAL_DEDUCTIONS);
            $net = (float) $payroll->getAttribute(Payroll::NET_AMOUNT);

            $imss = round($perceptions * 0.02725, 2);
            $isr = round(max(0, $deductions - $imss - ($delays * 50) - ($absences * $dailySalary)), 2);

            $hasIssues = $delays > 0 || $absences > 0;

            $rows[] = [
                'id' => (int) $payroll->getAttribute(Payroll::ID),
                'initials' => $this->initials($fullName),
                'name' => $fullName,
                'dailyRate' => round($dailySalary, 2),
                'hourlyOvertimeRate' => round($overtimeRate, 2),
                'days' => $daysWorked,
                'delays' => $delays,
                'absences' => $absences,
                'extra' => $extra,
                'concepts' => [
                    'salary' => round($dailySalary * $daysWorked, 2),
                    'overtime' => round($extra * $overtimeRate, 2),
                    'bonus' => 0.0,
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
