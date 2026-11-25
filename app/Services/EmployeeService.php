<?php

namespace App\Services;

use App\Models\Nexus\Area;
use App\Models\Nexus\Attendance;
use App\Models\Nexus\Employee;
use App\Models\Nexus\Incident;
use App\Models\Nexus\Nationality;
use App\Models\Nexus\PayrollFrequency;
use App\Models\Nexus\Position;
use App\Models\Nexus\Role;
use App\Models\Nexus\User as NexusUser;
use App\Models\Nexus\WorkSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Administración de empleados (users + employees + horario base), relación con
 * asistencias/faltas e importación masiva mediante CSV con vista previa.
 */
class EmployeeService
{
    private const ROLE_GENERAL = 4;

    /**
     * @return array<string, mixed>
     */
    public function listPageData(int $companyId): array
    {
        $areas = Area::query()
            ->where(Area::COMPANY_ID, $companyId)
            ->orderBy(Area::NAME)
            ->get()
            ->map(fn (Area $area): array => [
                'id' => (int) $area->getAttribute(Area::ID),
                'code' => (string) $area->getAttribute(Area::CODE),
                'name' => (string) $area->getAttribute(Area::NAME),
            ])
            ->values()
            ->all();

        $positions = Position::query()
            ->where(Position::COMPANY_ID, $companyId)
            ->orderBy(Position::NAME)
            ->get()
            ->map(fn (Position $position): array => [
                'id' => (int) $position->getAttribute(Position::ID),
                'area_id' => $position->getAttribute(Position::AREA_ID) !== null
                    ? (int) $position->getAttribute(Position::AREA_ID)
                    : null,
                'code' => (string) $position->getAttribute(Position::CODE),
                'name' => (string) $position->getAttribute(Position::NAME),
            ])
            ->values()
            ->all();

        $nationalities = Nationality::query()
            ->orderBy(Nationality::NATIONALITY)
            ->get()
            ->map(fn (Nationality $nat): array => [
                'id' => (int) $nat->getAttribute(Nationality::ID),
                'nationality' => (string) $nat->getAttribute(Nationality::NATIONALITY),
                'c_pais' => $nat->getAttribute(Nationality::C_PAIS),
                'country' => $nat->getAttribute(Nationality::COUNTRY),
            ])
            ->values()
            ->all();

        $frequencies = PayrollFrequency::query()
            ->orderBy(PayrollFrequency::DAYS)
            ->get()
            ->map(fn (PayrollFrequency $freq): array => [
                'id' => (int) $freq->getAttribute(PayrollFrequency::ID),
                'code' => (string) $freq->getAttribute(PayrollFrequency::CODE),
                'name' => (string) $freq->getAttribute(PayrollFrequency::NAME),
                'days' => (int) $freq->getAttribute(PayrollFrequency::DAYS),
            ])
            ->values()
            ->all();

        $roles = Role::query()
            ->whereNull(Role::DELETED_AT)
            ->orderBy(Role::ID)
            ->get()
            ->map(fn (Role $role): array => [
                'id' => (int) $role->getAttribute(Role::ID),
                'name' => (string) $role->getAttribute(Role::NAME),
            ])
            ->values()
            ->all();

        $employees = Employee::query()
            ->with(['user.role', 'area', 'position', 'nationality', 'payrollFrequency', 'workSchedules'])
            ->whereHas('user', fn ($q) => $q->where(NexusUser::COMPANY_ID, $companyId))
            ->orderByDesc(Employee::ID)
            ->get();

        $employeeIds = $employees->pluck(Employee::ID)->all();

        $attendanceSummary = $employeeIds === []
            ? collect()
            : Attendance::query()
                ->whereIn(Attendance::EMPLOYEE_ID, $employeeIds)
                ->selectRaw("employee_id,
                    count(*) as total,
                    sum(case when status in ('on_time', 'presente', 'late', 'retardo') then 1 else 0 end) as attended,
                    sum(case when status in ('late', 'retardo') then 1 else 0 end) as delays,
                    sum(case when status in ('absent', 'falta') then 1 else 0 end) as absences")
                ->groupBy(Attendance::EMPLOYEE_ID)
                ->get()
                ->keyBy(Attendance::EMPLOYEE_ID);

        $incidentCounts = $employeeIds === []
            ? collect()
            : Incident::query()
                ->whereIn(Incident::EMPLOYEE_ID, $employeeIds)
                ->whereNull(Incident::DELETED_AT)
                ->selectRaw('employee_id, count(*) as total')
                ->groupBy(Incident::EMPLOYEE_ID)
                ->pluck('total', 'employee_id');

        $rows = $employees->map(function (Employee $emp) use ($attendanceSummary, $incidentCounts): array {
            $user = $emp->user;
            $firstName = (string) ($user->getAttribute(NexusUser::NAME) ?? '');
            $lastName = (string) ($user->getAttribute(NexusUser::LAST_NAME) ?? '');
            $fullName = trim($firstName.' '.$lastName);
            $att = $attendanceSummary->get($emp->id);

            return [
                'id' => (int) $emp->id,
                'code' => (string) ($emp->code ?? ''),
                'initials' => $this->initials($fullName),
                'name' => $fullName,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'phone' => (string) ($user->getAttribute(NexusUser::PHONE) ?? ''),
                'contact' => (string) ($user->getAttribute(NexusUser::PHONE) ?: '—'),
                'nickname' => (string) ($user->getAttribute(NexusUser::NICKNAME) ?? ''),
                'role_id' => (int) ($user->getAttribute(NexusUser::ROLE_ID) ?? self::ROLE_GENERAL),
                'role_name' => (string) ($user->role?->getAttribute(Role::NAME) ?? 'General'),
                'area_id' => (int) $emp->area_id,
                'area' => (string) ($emp->area?->getAttribute(Area::NAME) ?? 'Sin área'),
                'position_id' => (int) $emp->position_id,
                'role' => (string) ($emp->position?->getAttribute(Position::NAME) ?? 'Sin puesto'),
                'nationality_id' => (int) $emp->nationality_id,
                'nationality_name' => (string) ($emp->nationality?->getAttribute(Nationality::NATIONALITY) ?? ''),
                'payroll_frequency_id' => (int) $emp->payroll_frequency_id,
                'frequency_name' => (string) ($emp->payrollFrequency?->getAttribute(PayrollFrequency::NAME) ?? ''),
                'sex' => (string) ($emp->sex ?? ''),
                'curp' => (string) ($emp->curp ?? ''),
                'ssn' => (string) ($emp->ssn ?? ''),
                'hire_date' => $emp->hire_date?->toDateString() ?? '',
                'date' => $emp->hire_date?->toDateString() ?? '—',
                'status' => $emp->status === 'activo' ? 'Activo' : 'Inactivo',
                'raw_status' => $emp->status,
                'schedule' => $this->formatSchedule($emp),
                'attendance_stats' => [
                    'attended' => (int) ($att->attended ?? 0),
                    'delays' => (int) ($att->delays ?? 0),
                    'absences' => (int) ($att->absences ?? 0),
                    'incidents' => (int) ($incidentCounts->get($emp->id) ?? 0),
                ],
            ];
        })->values()->all();

        return [
            'employees' => $rows,
            'catalogs' => [
                'areas' => $areas,
                'positions' => $positions,
                'nationalities' => $nationalities,
                'frequencies' => $frequencies,
                'roles' => $roles,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function create(int $companyId, int $actorId, array $input): Employee
    {
        $data = $this->validate($companyId, $input);

        return DB::transaction(function () use ($companyId, $actorId, $data): Employee {
            $nickname = $this->uniqueNickname(
                $companyId,
                $data['first_name'],
                $data['last_name'],
                $data['code'] ?: null,
            );

            $status = (string) ($data['status'] ?? 'activo');

            $nexusUser = NexusUser::query()->create([
                NexusUser::COMPANY_ID => $companyId,
                NexusUser::ROLE_ID => (int) ($data['role_id'] ?? self::ROLE_GENERAL),
                NexusUser::NICKNAME => $nickname,
                NexusUser::NAME => $data['first_name'],
                NexusUser::LAST_NAME => $data['last_name'],
                NexusUser::PHONE => $data['phone'] ?? null,
                NexusUser::PASSWD => Hash::make(Str::random(40)),
                NexusUser::IS_ACTIVE => $status === 'activo',
                NexusUser::IS_ADMIN => false,
                NexusUser::CREATED_USER_ID => $actorId,
            ]);

            $code = trim((string) ($data['code'] ?? ''));
            if ($code === '') {
                $code = 'EMP-'.str_pad((string) $nexusUser->id, 4, '0', STR_PAD_LEFT);
            }

            $employee = Employee::query()->create([
                Employee::ID_USER => $nexusUser->id,
                Employee::NATIONALITY_ID => (int) $data['nationality_id'],
                Employee::PAYROLL_FREQUENCY_ID => (int) $data['payroll_frequency_id'],
                Employee::AREA_ID => (int) $data['area_id'],
                Employee::POSITION_ID => (int) $data['position_id'],
                Employee::CODE => $code,
                Employee::SEX => $data['sex'] ?? null,
                Employee::CURP => $data['curp'] ?? null,
                Employee::SSN => $data['ssn'] ?? null,
                Employee::HIRE_DATE => $data['hire_date'] ?? null,
                Employee::STATUS => $status,
                Employee::CREATED_USER_ID => $actorId,
            ]);

            $this->seedDefaultSchedules($employee, $actorId, $data['hire_date'] ?? null);

            return $employee;
        });
    }

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function update(int $companyId, int $actorId, Employee $employee, array $input): Employee
    {
        $this->assertBelongsToCompany($companyId, $employee);
        $data = $this->validate($companyId, $input, $employee->id);

        return DB::transaction(function () use ($actorId, $employee, $data): Employee {
            $status = (string) ($data['status'] ?? $employee->status);
            $user = $employee->user;

            $user->fill([
                NexusUser::ROLE_ID => (int) ($data['role_id'] ?? $user->getAttribute(NexusUser::ROLE_ID) ?? self::ROLE_GENERAL),
                NexusUser::NAME => $data['first_name'],
                NexusUser::LAST_NAME => $data['last_name'],
                NexusUser::PHONE => $data['phone'] ?? null,
                NexusUser::IS_ACTIVE => $status === 'activo',
                NexusUser::UPDATED_USER_ID => $actorId,
            ]);
            $user->save();

            $code = trim((string) ($data['code'] ?? ''));
            $employee->fill([
                Employee::NATIONALITY_ID => (int) $data['nationality_id'],
                Employee::PAYROLL_FREQUENCY_ID => (int) $data['payroll_frequency_id'],
                Employee::AREA_ID => (int) $data['area_id'],
                Employee::POSITION_ID => (int) $data['position_id'],
                Employee::CODE => $code !== '' ? $code : $employee->code,
                Employee::SEX => $data['sex'] ?? null,
                Employee::CURP => $data['curp'] ?? null,
                Employee::SSN => $data['ssn'] ?? null,
                Employee::HIRE_DATE => $data['hire_date'] ?? null,
                Employee::STATUS => $status,
                Employee::UPDATED_USER_ID => $actorId,
            ]);
            $employee->save();

            return $employee;
        });
    }

    public function delete(int $companyId, int $actorId, Employee $employee): void
    {
        $this->assertBelongsToCompany($companyId, $employee);

        DB::transaction(function () use ($actorId, $employee): void {
            $employee->fill([
                Employee::STATUS => 'inactivo',
                Employee::DELETED_USER_ID => $actorId,
            ]);
            $employee->save();
            $employee->delete();

            $user = $employee->user;
            $user->fill([
                NexusUser::IS_ACTIVE => false,
                NexusUser::DELETED_USER_ID => $actorId,
            ]);
            $user->save();
            $user->delete();
        });
    }

    public function assertBelongsToCompany(int $companyId, Employee $employee): void
    {
        $employee->loadMissing('user');
        abort_unless(
            (int) $employee->user->getAttribute(NexusUser::COMPANY_ID) === $companyId,
            404,
        );
    }

    /**
     * Lee el CSV subido, resuelve catálogos y valida fila por fila sin guardar nada.
     *
     * @return array{summary: array{total: int, valid: int, invalid: int}, rows: list<array<string, mixed>>}
     *
     * @throws ValidationException
     */
    public function previewCsv(int $companyId, UploadedFile $file): array
    {
        $raw = (string) file_get_contents($file->getRealPath());
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw) ?? $raw;
        $lines = preg_split('/\r\n|\r|\n/', trim($raw)) ?: [];

        if (count($lines) < 2) {
            throw ValidationException::withMessages([
                'file' => 'El archivo CSV debe incluir una fila de encabezados y al menos un empleado.',
            ]);
        }

        $delimiter = $this->detectDelimiter($lines[0]);
        $headers = array_map(
            fn (mixed $h): string => $this->normalizeHeader((string) $h),
            str_getcsv($lines[0], $delimiter),
        );

        $lookups = $this->buildCsvLookups($companyId);
        $existingCodes = Employee::query()
            ->whereHas('user', fn ($q) => $q->where(NexusUser::COMPANY_ID, $companyId))
            ->whereNotNull(Employee::CODE)
            ->pluck(Employee::CODE)
            ->map(fn (mixed $c): string => mb_strtoupper(trim((string) $c)))
            ->filter()
            ->flip()
            ->all();

        $seenCodesInFile = [];
        $rows = [];
        $validCount = 0;

        foreach (array_slice($lines, 1) as $offset => $line) {
            if (trim($line) === '') {
                continue;
            }

            $cells = str_getcsv($line, $delimiter);
            $mapped = $this->mapCsvRow($headers, $cells);
            $result = $this->validateCsvRow($mapped, $lookups, $existingCodes, $seenCodesInFile);

            $rowCode = mb_strtoupper(trim((string) ($mapped['code'] ?? '')));
            if ($rowCode !== '') {
                $seenCodesInFile[$rowCode] = true;
            }

            if ($result['valid']) {
                $validCount++;
            }

            $rows[] = [
                'rowNumber' => $offset + 2,
                'valid' => $result['valid'],
                'errors' => $result['errors'],
                'raw' => $mapped,
                'payload' => $result['payload'],
            ];
        }

        if ($rows === []) {
            throw ValidationException::withMessages([
                'file' => 'No se encontraron filas de empleados en el CSV.',
            ]);
        }

        return [
            'summary' => [
                'total' => count($rows),
                'valid' => $validCount,
                'invalid' => count($rows) - $validCount,
            ],
            'rows' => $rows,
        ];
    }

    /**
     * Importa únicamente las filas válidas después de la confirmación del usuario.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return array{imported: int, skipped: int}
     */
    public function importConfirmedRows(int $companyId, int $actorId, array $rows): array
    {
        $imported = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            try {
                $this->create($companyId, $actorId, $row);
                $imported++;
            } catch (Throwable) {
                $skipped++;
            }
        }

        return ['imported' => $imported, 'skipped' => $skipped];
    }

    public function csvTemplate(): string
    {
        $header = implode(',', [
            'codigo',
            'nombre',
            'apellidos',
            'telefono',
            'sexo',
            'curp',
            'nss',
            'fecha_ingreso',
            'nacionalidad',
            'area',
            'puesto',
            'frecuencia_nomina',
            'estado',
        ]);

        $example = implode(',', [
            'EMP-101',
            'María',
            'López García',
            '8711234567',
            'Femenino',
            'LOGM950312MNLNPR01',
            '12345678901',
            '2026-11-01',
            'Mexicana',
            'RH',
            'AUX-RH',
            'quincenal',
            'activo',
        ]);

        return "\xEF\xBB\xBF".$header."\n".$example."\n";
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    private function validate(int $companyId, array $input, ?int $ignoreEmployeeId = null): array
    {
        $normalized = $input;
        foreach (['code', 'curp'] as $upperKey) {
            if (isset($normalized[$upperKey]) && is_string($normalized[$upperKey])) {
                $trimmed = trim($normalized[$upperKey]);
                $normalized[$upperKey] = $trimmed === '' ? null : mb_strtoupper($trimmed);
            }
        }
        foreach (['phone', 'ssn'] as $trimKey) {
            if (isset($normalized[$trimKey]) && is_string($normalized[$trimKey])) {
                $trimmed = trim($normalized[$trimKey]);
                $normalized[$trimKey] = $trimmed === '' ? null : $trimmed;
            }
        }

        $companyEmployeeIds = Employee::query()
            ->whereHas('user', fn ($q) => $q->where(NexusUser::COMPANY_ID, $companyId))
            ->pluck(Employee::ID)
            ->all();

        $codeUniqueRule = Rule::unique('employees', 'code')
            ->where(fn ($q) => $q->whereIn('id', $companyEmployeeIds === [] ? [0] : $companyEmployeeIds)->whereNull('deleted_at'))
            ->ignore($ignoreEmployeeId);

        $rules = [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:20'],
            'code' => ['nullable', 'string', 'max:50', $codeUniqueRule],
            'sex' => ['nullable', 'string', Rule::in(['Masculino', 'Femenino', 'Otro'])],
            'curp' => ['nullable', 'string', 'size:18', 'regex:/^[A-Z0-9]{18}$/'],
            'ssn' => ['nullable', 'string', 'max:11', 'regex:/^[0-9]{1,11}$/'],
            'hire_date' => ['required', 'date'],
            'nationality_id' => [
                'required',
                'integer',
                Rule::exists('nationalities', 'id')->where(fn ($q) => $q->whereNull('deleted_at')),
            ],
            'area_id' => [
                'required',
                'integer',
                Rule::exists('areas', 'id')->where(fn ($q) => $q->where('company_id', $companyId)->whereNull('deleted_at')),
            ],
            'position_id' => [
                'required',
                'integer',
                Rule::exists('positions', 'id')->where(fn ($q) => $q->where('company_id', $companyId)->whereNull('deleted_at')),
            ],
            'payroll_frequency_id' => [
                'required',
                'integer',
                Rule::exists('payroll_frequencies', 'id')->where(fn ($q) => $q->whereNull('deleted_at')),
            ],
            'role_id' => [
                'nullable',
                'integer',
                Rule::exists('roles', 'id')->where(fn ($q) => $q->whereNull('deleted_at')),
            ],
            'status' => ['nullable', 'string', Rule::in(['activo', 'inactivo'])],
        ];

        $messages = [
            'first_name.required' => 'El nombre es obligatorio.',
            'last_name.required' => 'Los apellidos son obligatorios.',
            'code.unique' => 'Ya existe otro empleado con esa clave en la empresa.',
            'curp.size' => 'La CURP debe tener exactamente 18 caracteres.',
            'curp.regex' => 'La CURP solo puede contener letras y números.',
            'ssn.regex' => 'El NSS debe contener únicamente dígitos (máximo 11).',
            'hire_date.required' => 'La fecha de ingreso es obligatoria.',
            'nationality_id.required' => 'Selecciona una nacionalidad.',
            'area_id.required' => 'Selecciona un área.',
            'position_id.required' => 'Selecciona un puesto.',
            'payroll_frequency_id.required' => 'Selecciona una frecuencia de nómina.',
        ];

        return Validator::make($normalized, $rules, $messages)->validate();
    }

    private function seedDefaultSchedules(Employee $employee, int $actorId, ?string $hireDate): void
    {
        $validFrom = $hireDate ?: now()->toDateString();

        for ($weekday = 1; $weekday <= 7; $weekday++) {
            $isRestDay = $weekday >= 6;
            WorkSchedule::query()->create([
                WorkSchedule::EMPLOYEE_ID => $employee->id,
                WorkSchedule::WEEKDAY => $weekday,
                WorkSchedule::CHECK_IN_TIME => $isRestDay ? null : '09:00:00',
                WorkSchedule::CHECK_OUT_TIME => $isRestDay ? null : '18:00:00',
                WorkSchedule::IS_REST_DAY => $isRestDay,
                WorkSchedule::VALID_FROM => $validFrom,
                WorkSchedule::CREATED_USER_ID => $actorId,
            ]);
        }
    }

    private function uniqueNickname(int $companyId, string $firstName, string $lastName, ?string $code): string
    {
        $base = Str::slug(trim($firstName.' '.$lastName), '.');
        if ($base === '') {
            $base = $code !== null ? Str::slug($code, '.') : 'empleado';
        }
        $base = mb_substr($base, 0, 80);

        $candidate = $base;
        $counter = 1;
        while (NexusUser::withTrashed()->where(NexusUser::COMPANY_ID, $companyId)->where(NexusUser::NICKNAME, $candidate)->exists()) {
            $candidate = mb_substr($base, 0, 74).'.'.$counter;
            $counter++;
        }

        return $candidate;
    }

    private function formatSchedule(Employee $emp): string
    {
        $firstWorkday = $emp->workSchedules
            ->where('is_rest_day', false)
            ->sortBy('weekday')
            ->first();

        if ($firstWorkday === null || $firstWorkday->check_in_time === null || $firstWorkday->check_out_time === null) {
            return 'Sin horario';
        }

        return substr($firstWorkday->check_in_time, 0, 5).'–'.substr($firstWorkday->check_out_time, 0, 5);
    }

    private function initials(string $name): string
    {
        $parts = array_values(array_filter(explode(' ', trim($name))));
        $first = isset($parts[0]) ? mb_substr($parts[0], 0, 1) : 'E';
        $second = isset($parts[1]) ? mb_substr($parts[1], 0, 1) : '';

        return mb_strtoupper($first.$second);
    }

    private function detectDelimiter(string $headerLine): string
    {
        return substr_count($headerLine, ';') > substr_count($headerLine, ',') ? ';' : ',';
    }

    private function normalizeHeader(string $header): string
    {
        $ascii = Str::ascii(mb_strtolower(trim($header)));
        $clean = preg_replace('/[^a-z0-9]+/', '_', $ascii) ?? '';

        return trim($clean, '_');
    }

    /**
     * @param  list<string>  $headers
     * @param  list<string|null>  $cells
     * @return array<string, string>
     */
    private function mapCsvRow(array $headers, array $cells): array
    {
        $aliases = [
            'codigo' => 'code',
            'clave' => 'code',
            'code' => 'code',
            'nombre' => 'first_name',
            'nombres' => 'first_name',
            'first_name' => 'first_name',
            'apellidos' => 'last_name',
            'apellido' => 'last_name',
            'last_name' => 'last_name',
            'telefono' => 'phone',
            'phone' => 'phone',
            'sexo' => 'sex',
            'sex' => 'sex',
            'curp' => 'curp',
            'nss' => 'ssn',
            'ssn' => 'ssn',
            'numero_de_seguro_social' => 'ssn',
            'fecha_ingreso' => 'hire_date',
            'hire_date' => 'hire_date',
            'ingreso' => 'hire_date',
            'nacionalidad' => 'nationality',
            'nationality' => 'nationality',
            'area' => 'area',
            'puesto' => 'position',
            'position' => 'position',
            'frecuencia_nomina' => 'frequency',
            'frecuencia' => 'frequency',
            'frecuencia_de_nomina' => 'frequency',
            'payroll_frequency' => 'frequency',
            'estado' => 'status',
            'status' => 'status',
        ];

        $result = [
            'code' => '',
            'first_name' => '',
            'last_name' => '',
            'phone' => '',
            'sex' => '',
            'curp' => '',
            'ssn' => '',
            'hire_date' => '',
            'nationality' => '',
            'area' => '',
            'position' => '',
            'frequency' => '',
            'status' => 'activo',
        ];

        foreach ($headers as $idx => $header) {
            if (! isset($aliases[$header])) {
                continue;
            }
            $field = $aliases[$header];
            $value = trim((string) ($cells[$idx] ?? ''));
            if ($field === 'status' && $value === '') {
                continue;
            }
            $result[$field] = $value;
        }

        return $result;
    }

    /**
     * @return array{
     *   areas: array<string, int>,
     *   positions: array<string, int>,
     *   nationalities: array<string, int>,
     *   frequencies: array<string, int>
     * }
     */
    private function buildCsvLookups(int $companyId): array
    {
        $areas = [];
        foreach (Area::query()->where(Area::COMPANY_ID, $companyId)->get() as $area) {
            $id = (int) $area->getAttribute(Area::ID);
            $areas[$this->lookupKey((string) $area->getAttribute(Area::CODE))] = $id;
            $areas[$this->lookupKey((string) $area->getAttribute(Area::NAME))] = $id;
        }

        $positions = [];
        foreach (Position::query()->where(Position::COMPANY_ID, $companyId)->get() as $position) {
            $id = (int) $position->getAttribute(Position::ID);
            $positions[$this->lookupKey((string) $position->getAttribute(Position::CODE))] = $id;
            $positions[$this->lookupKey((string) $position->getAttribute(Position::NAME))] = $id;
        }

        $nationalities = [];
        foreach (Nationality::query()->get() as $nat) {
            $id = (int) $nat->getAttribute(Nationality::ID);
            $nationalities[$this->lookupKey((string) $nat->getAttribute(Nationality::NATIONALITY))] = $id;
            if ($nat->getAttribute(Nationality::C_PAIS)) {
                $nationalities[$this->lookupKey((string) $nat->getAttribute(Nationality::C_PAIS))] = $id;
            }
            if ($nat->getAttribute(Nationality::COUNTRY)) {
                $nationalities[$this->lookupKey((string) $nat->getAttribute(Nationality::COUNTRY))] = $id;
            }
        }

        $frequencies = [];
        foreach (PayrollFrequency::query()->get() as $freq) {
            $id = (int) $freq->getAttribute(PayrollFrequency::ID);
            $frequencies[$this->lookupKey((string) $freq->getAttribute(PayrollFrequency::CODE))] = $id;
            $frequencies[$this->lookupKey((string) $freq->getAttribute(PayrollFrequency::NAME))] = $id;
        }

        return compact('areas', 'positions', 'nationalities', 'frequencies');
    }

    /**
     * @param  array<string, string>  $row
     * @param  array{areas: array<string, int>, positions: array<string, int>, nationalities: array<string, int>, frequencies: array<string, int>}  $lookups
     * @param  array<string, int>  $existingCodes
     * @param  array<string, bool>  $seenCodesInFile
     * @return array{valid: bool, errors: list<string>, payload: array<string, mixed>|null}
     */
    private function validateCsvRow(array $row, array $lookups, array $existingCodes, array $seenCodesInFile): array
    {
        $errors = [];

        if ($row['first_name'] === '') {
            $errors[] = 'Falta el nombre.';
        }
        if ($row['last_name'] === '') {
            $errors[] = 'Faltan los apellidos.';
        }

        $upperCode = mb_strtoupper($row['code']);
        if ($upperCode !== '') {
            if (isset($existingCodes[$upperCode])) {
                $errors[] = "La clave «{$upperCode}» ya existe en la empresa.";
            } elseif (isset($seenCodesInFile[$upperCode])) {
                $errors[] = "La clave «{$upperCode}» está repetida en el CSV.";
            }
        }

        $curp = mb_strtoupper($row['curp']);
        if ($curp !== '' && preg_match('/^[A-Z0-9]{18}$/', $curp) !== 1) {
            $errors[] = 'CURP inválida (debe tener 18 caracteres alfanuméricos).';
        }

        $ssn = $row['ssn'];
        if ($ssn !== '' && preg_match('/^[0-9]{1,11}$/', $ssn) !== 1) {
            $errors[] = 'NSS inválido (solo dígitos, máximo 11).';
        }

        $hireDate = $row['hire_date'];
        if ($hireDate === '') {
            $errors[] = 'Falta la fecha de ingreso (AAAA-MM-DD).';
        } else {
            try {
                $parsed = CarbonImmutable::createFromFormat('!Y-m-d', $hireDate);
                if (! $parsed instanceof CarbonImmutable || $parsed->format('Y-m-d') !== $hireDate) {
                    $errors[] = 'Fecha de ingreso inválida (usa formato AAAA-MM-DD).';
                }
            } catch (Throwable) {
                $errors[] = 'Fecha de ingreso inválida (usa formato AAAA-MM-DD).';
            }
        }

        $sex = $this->normalizeSex($row['sex']);
        if ($row['sex'] !== '' && $sex === null) {
            $errors[] = 'Sexo no reconocido (usa Masculino, Femenino u Otro).';
        }

        $status = mb_strtolower($row['status'] ?: 'activo');
        if (! in_array($status, ['activo', 'inactivo'], true)) {
            $errors[] = 'Estado inválido (usa activo o inactivo).';
        }

        $natId = $lookups['nationalities'][$this->lookupKey($row['nationality'])] ?? null;
        if ($natId === null) {
            $errors[] = "Nacionalidad no encontrada: «{$row['nationality']}».";
        }

        $areaId = $lookups['areas'][$this->lookupKey($row['area'])] ?? null;
        if ($areaId === null) {
            $errors[] = "Área no encontrada en el catálogo: «{$row['area']}».";
        }

        $positionId = $lookups['positions'][$this->lookupKey($row['position'])] ?? null;
        if ($positionId === null) {
            $errors[] = "Puesto no encontrado en el catálogo: «{$row['position']}».";
        }

        $freqId = $lookups['frequencies'][$this->lookupKey($row['frequency'])] ?? null;
        if ($freqId === null) {
            $errors[] = "Frecuencia de nómina no encontrada: «{$row['frequency']}».";
        }

        if ($errors !== []) {
            return ['valid' => false, 'errors' => $errors, 'payload' => null];
        }

        return [
            'valid' => true,
            'errors' => [],
            'payload' => [
                'code' => $upperCode !== '' ? $upperCode : null,
                'first_name' => $row['first_name'],
                'last_name' => $row['last_name'],
                'phone' => $row['phone'] !== '' ? $row['phone'] : null,
                'sex' => $sex,
                'curp' => $curp !== '' ? $curp : null,
                'ssn' => $ssn !== '' ? $ssn : null,
                'hire_date' => $hireDate,
                'nationality_id' => $natId,
                'area_id' => $areaId,
                'position_id' => $positionId,
                'payroll_frequency_id' => $freqId,
                'status' => $status,
            ],
        ];
    }

    private function normalizeSex(string $raw): ?string
    {
        $val = mb_strtolower(trim($raw));

        return match ($val) {
            '' => null,
            'm', 'masculino', 'hombre' => 'Masculino',
            'f', 'femenino', 'mujer' => 'Femenino',
            'o', 'otro' => 'Otro',
            default => null,
        };
    }

    private function lookupKey(string $value): string
    {
        return Str::ascii(mb_strtolower(trim($value)));
    }
}
