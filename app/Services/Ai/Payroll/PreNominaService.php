<?php

namespace App\Services\Ai\Payroll;

use App\Services\Ai\Tools\InvalidToolCallException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;
use Throwable;

/**
 * Pre-nómina a partir de las asistencias del periodo.
 *
 * Calcula por empleado: días trabajados, retardos, faltas (sin las
 * justificadas por una incidencia) y horas extra. El esquema no guarda sueldos,
 * así que los importes quedan en 0: se dice explícitamente y nunca se inventan.
 */
class PreNominaService
{
    /** Retardos a partir de los cuales una pre-nómina se marca "revisar". */
    private const LATE_REVIEW_THRESHOLD = 3;

    private const MAX_DAYS = 31;

    /**
     * Calcula la pre-nómina sin guardar nada.
     *
     * @return array{frequency_id: int, frequency: string, start: string, end: string, rows: list<array<string, mixed>>, totals: array<string, mixed>, warnings: list<string>}
     *
     * @throws InvalidToolCallException
     */
    public function preview(int $companyId, string $start, string $end, ?string $frequency = null): array
    {
        [$from, $to] = $this->range($start, $end);
        $freq = $this->frequency($frequency, $from, $to);

        $employees = DB::table('employees as e')
            ->join('users as u', 'u.id', '=', 'e.id_user')
            ->where('u.company_id', $companyId)
            ->where('e.status', 'activo')
            ->whereNull('e.deleted_at')
            ->where('e.payroll_frequency_id', $freq->id)
            ->orderBy('e.id')
            ->get(['e.id', 'e.code', 'u.name', 'u.last_name']);

        if ($employees->isEmpty()) {
            throw new InvalidToolCallException("No hay empleados activos con frecuencia de pago «{$freq->name}» en esta empresa.");
        }

        $ids = $employees->pluck('id')->all();

        $stats = DB::table('attendances')
            ->whereIn('employee_id', $ids)
            ->whereBetween('attendance_date', [$from->toDateString(), $to->toDateString()])
            ->whereNull('deleted_at')
            ->selectRaw("employee_id,
                sum(status in ('presente', 'retardo')) as worked,
                sum(status = 'retardo') as late,
                coalesce(sum(overtime_hours), 0) as overtime,
                count(*) as records")
            ->groupBy('employee_id')
            ->get()
            ->keyBy('employee_id');

        $absences = DB::table('attendances')
            ->whereIn('employee_id', $ids)
            ->where('status', 'falta')
            ->whereBetween('attendance_date', [$from->toDateString(), $to->toDateString()])
            ->whereNull('deleted_at')
            ->get(['employee_id', 'attendance_date'])
            ->groupBy('employee_id');

        $justified = DB::table('incidents')
            ->whereIn('employee_id', $ids)
            ->where('status', 'justificada')
            ->whereNull('deleted_at')
            ->where('start_date', '<=', $to->toDateString())
            ->whereRaw('coalesce(end_date, start_date) >= ?', [$from->toDateString()])
            ->get(['employee_id', 'start_date', 'end_date'])
            ->groupBy('employee_id');

        $rows = [];
        $warnings = [];

        foreach ($employees as $employee) {
            $stat = $stats->get($employee->id);
            $records = (int) ($stat->records ?? 0);

            [$unjustified, $covered] = $this->splitAbsences(
                $absences->get($employee->id, collect()),
                $justified->get($employee->id, collect()),
            );

            $late = (int) ($stat->late ?? 0);
            $notes = [];
            if ($records === 0) {
                $notes[] = 'Sin asistencias registradas en el periodo';
                $warnings[] = trim($employee->name.' '.$employee->last_name).' no tiene asistencias registradas en el periodo.';
            }
            if ($unjustified > 0) {
                $notes[] = $unjustified.' falta(s) sin justificar';
            }
            if ($late >= self::LATE_REVIEW_THRESHOLD) {
                $notes[] = $late.' retardos';
            }

            $rows[] = [
                'employee_id' => (int) $employee->id,
                'empleado' => trim($employee->name.' '.$employee->last_name),
                'codigo' => $employee->code,
                'dias_trabajados' => (int) ($stat->worked ?? 0),
                'retardos' => $late,
                'faltas' => $unjustified,
                'faltas_justificadas' => $covered,
                'horas_extra' => round((float) ($stat->overtime ?? 0), 2),
                'estatus' => $notes === [] ? 'correcto' : 'revisar',
                'observaciones' => $notes === [] ? '' : implode('; ', $notes),
            ];
        }

        return [
            'frequency_id' => (int) $freq->id,
            'frequency' => $freq->name,
            'start' => $from->toDateString(),
            'end' => $to->toDateString(),
            'rows' => $rows,
            'totals' => [
                'empleados' => count($rows),
                'por_revisar' => count(array_filter($rows, static fn (array $r): bool => $r['estatus'] === 'revisar')),
                'dias_trabajados' => array_sum(array_column($rows, 'dias_trabajados')),
                'retardos' => array_sum(array_column($rows, 'retardos')),
                'faltas' => array_sum(array_column($rows, 'faltas')),
                'horas_extra' => round((float) array_sum(array_column($rows, 'horas_extra')), 2),
            ],
            'warnings' => $warnings,
        ];
    }

    /**
     * Calcula y guarda la pre-nómina (periodo y detalle por empleado). Regenerar
     * el mismo periodo actualiza los datos en lugar de duplicarlos.
     *
     * @return array{period_id: int, created: bool, preview: array<string, mixed>}
     *
     * @throws InvalidToolCallException
     */
    public function generate(int $companyId, string $start, string $end, string $frequency): array
    {
        $preview = $this->preview($companyId, $start, $end, $frequency);

        return DB::transaction(function () use ($companyId, $preview): array {
            $period = DB::table('payroll_periods')
                ->where('company_id', $companyId)
                ->where('payroll_frequency_id', $preview['frequency_id'])
                ->where('start_date', $preview['start'])
                ->first();

            if ($period !== null && $period->status === 'exportado') {
                throw new InvalidToolCallException('Ese periodo ya fue exportado y no se puede modificar.');
            }

            $created = $period === null;
            if ($created) {
                $periodId = (int) DB::table('payroll_periods')->insertGetId([
                    'company_id' => $companyId,
                    'payroll_frequency_id' => $preview['frequency_id'],
                    'start_date' => $preview['start'],
                    'end_date' => $preview['end'],
                    'status' => 'calculado',
                ]);
            } else {
                $periodId = (int) $period->id;
                DB::table('payroll_periods')->where('id', $periodId)->update([
                    'end_date' => $preview['end'],
                    'status' => 'calculado',
                ]);
            }

            foreach ($preview['rows'] as $row) {
                $values = [
                    'days_worked' => $row['dias_trabajados'],
                    'late_count' => $row['retardos'],
                    'absence_count' => $row['faltas'],
                    'overtime_hours' => $row['horas_extra'],
                    'status' => $row['estatus'],
                ];
                $key = ['payroll_period_id' => $periodId, 'employee_id' => $row['employee_id']];

                if (DB::table('payrolls')->where($key)->exists()) {
                    // Los importes ya capturados no se tocan.
                    DB::table('payrolls')->where($key)->update($values);
                } else {
                    DB::table('payrolls')->insert([
                        ...$key,
                        ...$values,
                        'total_perceptions' => '0.00',
                        'total_deductions' => '0.00',
                        'net_amount' => '0.00',
                    ]);
                }
            }

            return ['period_id' => $periodId, 'created' => $created, 'preview' => $preview];
        });
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function range(string $start, string $end): array
    {
        $from = $this->date($start, 'inicio');
        $to = $this->date($end, 'fin');

        if ($to->lt($from)) {
            throw new InvalidToolCallException('La fecha de fin no puede ser anterior a la de inicio.');
        }
        if ((int) $from->diffInDays($to) + 1 > self::MAX_DAYS) {
            throw new InvalidToolCallException('Un periodo de pre-nómina no puede durar más de '.self::MAX_DAYS.' días.');
        }

        return [$from, $to];
    }

    private function date(string $value, string $label): CarbonImmutable
    {
        try {
            $parsed = CarbonImmutable::createFromFormat('!Y-m-d', trim($value));
        } catch (Throwable) {
            $parsed = null;
        }

        if (! $parsed instanceof CarbonImmutable || $parsed->format('Y-m-d') !== trim($value)) {
            throw new InvalidToolCallException("La fecha de {$label} debe tener el formato AAAA-MM-DD.");
        }

        return $parsed;
    }

    /**
     * Frecuencia por código/nombre o, si no se indica, la que mejor encaja con la
     * duración del periodo (7 → semanal, 15 → quincenal, 30 → mensual).
     */
    private function frequency(?string $requested, CarbonImmutable $from, CarbonImmutable $to): stdClass
    {
        $frequencies = DB::table('payroll_frequencies')->whereNull('deleted_at')->orderBy('days')->get();

        if ($frequencies->isEmpty()) {
            throw new InvalidToolCallException('No hay frecuencias de pago configuradas.');
        }

        if ($requested !== null && trim($requested) !== '') {
            $needle = mb_strtolower(trim($requested));
            $match = $frequencies->first(fn (stdClass $f): bool => mb_strtolower((string) $f->code) === $needle || mb_strtolower((string) $f->name) === $needle);

            if ($match === null) {
                throw new InvalidToolCallException('Frecuencia no válida. Opciones: '.$frequencies->pluck('code')->implode(', ').'.');
            }

            return $match;
        }

        $span = (int) $from->diffInDays($to) + 1;

        /** @var Collection<int, stdClass> $sorted */
        $sorted = $frequencies->sortBy(fn (stdClass $f): int => abs((int) $f->days - $span))->values();

        return $sorted->first();
    }

    /**
     * Separa las faltas en las que cuentan y las cubiertas por una incidencia justificada.
     *
     * @param  Collection<int, stdClass>  $absences
     * @param  Collection<int, stdClass>  $incidents
     * @return array{0: int, 1: int}
     */
    private function splitAbsences(Collection $absences, Collection $incidents): array
    {
        $unjustified = 0;
        $covered = 0;

        foreach ($absences as $absence) {
            $date = (string) $absence->attendance_date;
            $isCovered = $incidents->contains(
                fn (stdClass $i): bool => (string) $i->start_date <= $date && $date <= (string) ($i->end_date ?? $i->start_date),
            );

            $isCovered ? $covered++ : $unjustified++;
        }

        return [$unjustified, $covered];
    }
}
