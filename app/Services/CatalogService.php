<?php

namespace App\Services;

use App\Models\Nexus\Area;
use App\Models\Nexus\BusinessType;
use App\Models\Nexus\Customer;
use App\Models\Nexus\Employee;
use App\Models\Nexus\Nationality;
use App\Models\Nexus\PayrollConcept;
use App\Models\Nexus\PayrollConceptType;
use App\Models\Nexus\PayrollDetail;
use App\Models\Nexus\PayrollFrequency;
use App\Models\Nexus\PayrollPeriod;
use App\Models\Nexus\Position;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Servicio unificado para administrar los 6 catálogos de Pre-Nómina.
 *
 * Los catálogos con `company_id` (áreas, puestos y conceptos) están estrictamente
 * aislados por empresa; los catálogos globales (frecuencias, nacionalidades y
 * tipos de negocio) están disponibles en todo el sistema.
 */
class CatalogService
{
    /** @var list<string> */
    public const SLUGS = [
        'areas',
        'puestos',
        'frecuencias',
        'conceptos',
        'nacionalidades',
        'tipos-negocio',
    ];

    public function supports(string $slug): bool
    {
        return in_array($slug, self::SLUGS, true);
    }

    /**
     * @return array<string, mixed>
     */
    public function listData(string $slug, int $companyId): array
    {
        return match ($slug) {
            'areas' => [
                'items' => Area::query()
                    ->where(Area::COMPANY_ID, $companyId)
                    ->orderBy(Area::NAME)
                    ->get()
                    ->map(fn (Area $area): array => [
                        'id' => (int) $area->getAttribute(Area::ID),
                        'code' => (string) $area->getAttribute(Area::CODE),
                        'name' => (string) $area->getAttribute(Area::NAME),
                    ])
                    ->values()
                    ->all(),
            ],
            'puestos' => [
                'items' => Position::query()
                    ->with('area')
                    ->where(Position::COMPANY_ID, $companyId)
                    ->orderBy(Position::NAME)
                    ->get()
                    ->map(fn (Position $position): array => [
                        'id' => (int) $position->getAttribute(Position::ID),
                        'code' => (string) $position->getAttribute(Position::CODE),
                        'name' => (string) $position->getAttribute(Position::NAME),
                        'area_id' => $position->getAttribute(Position::AREA_ID) !== null
                            ? (int) $position->getAttribute(Position::AREA_ID)
                            : null,
                        'area_name' => $position->area?->getAttribute(Area::NAME) ?? 'Sin área',
                    ])
                    ->values()
                    ->all(),
                'areas' => Area::query()
                    ->where(Area::COMPANY_ID, $companyId)
                    ->orderBy(Area::NAME)
                    ->get([Area::ID, Area::CODE, Area::NAME])
                    ->map(fn (Area $area): array => [
                        'id' => (int) $area->getAttribute(Area::ID),
                        'code' => (string) $area->getAttribute(Area::CODE),
                        'name' => (string) $area->getAttribute(Area::NAME),
                    ])
                    ->values()
                    ->all(),
            ],
            'frecuencias' => [
                'items' => PayrollFrequency::query()
                    ->orderBy(PayrollFrequency::DAYS)
                    ->get()
                    ->map(fn (PayrollFrequency $freq): array => [
                        'id' => (int) $freq->getAttribute(PayrollFrequency::ID),
                        'code' => (string) $freq->getAttribute(PayrollFrequency::CODE),
                        'name' => (string) $freq->getAttribute(PayrollFrequency::NAME),
                        'days' => (int) $freq->getAttribute(PayrollFrequency::DAYS),
                    ])
                    ->values()
                    ->all(),
            ],
            'conceptos' => [
                'items' => PayrollConcept::query()
                    ->with('conceptType')
                    ->where(PayrollConcept::COMPANY_ID, $companyId)
                    ->orderBy(PayrollConcept::PAYROLL_CONCEPT_TYPE_ID)
                    ->orderBy(PayrollConcept::NAME)
                    ->get()
                    ->map(fn (PayrollConcept $concept): array => [
                        'id' => (int) $concept->getAttribute(PayrollConcept::ID),
                        'code' => (string) $concept->getAttribute(PayrollConcept::CODE),
                        'name' => (string) $concept->getAttribute(PayrollConcept::NAME),
                        'payroll_concept_type_id' => (int) $concept->getAttribute(PayrollConcept::PAYROLL_CONCEPT_TYPE_ID),
                        'type_name' => (string) ($concept->conceptType?->getAttribute(PayrollConceptType::NAME) ?? ''),
                        'type_code' => (string) ($concept->conceptType?->getAttribute(PayrollConceptType::CODE) ?? ''),
                        'is_active' => (bool) $concept->getAttribute(PayrollConcept::IS_ACTIVE),
                    ])
                    ->values()
                    ->all(),
                'conceptTypes' => PayrollConceptType::query()
                    ->orderBy(PayrollConceptType::ID)
                    ->get()
                    ->map(fn (PayrollConceptType $type): array => [
                        'id' => (int) $type->getAttribute(PayrollConceptType::ID),
                        'code' => (string) $type->getAttribute(PayrollConceptType::CODE),
                        'name' => (string) $type->getAttribute(PayrollConceptType::NAME),
                    ])
                    ->values()
                    ->all(),
            ],
            'nacionalidades' => [
                'items' => Nationality::query()
                    ->orderBy(Nationality::NATIONALITY)
                    ->get()
                    ->map(fn (Nationality $nat): array => [
                        'id' => (int) $nat->getAttribute(Nationality::ID),
                        'nationality' => (string) $nat->getAttribute(Nationality::NATIONALITY),
                        'c_pais' => $nat->getAttribute(Nationality::C_PAIS),
                        'country' => $nat->getAttribute(Nationality::COUNTRY),
                    ])
                    ->values()
                    ->all(),
            ],
            'tipos-negocio' => [
                'items' => BusinessType::query()
                    ->orderBy(BusinessType::NAME)
                    ->get()
                    ->map(fn (BusinessType $type): array => [
                        'id' => (int) $type->getAttribute(BusinessType::ID),
                        'code' => (string) $type->getAttribute(BusinessType::CODE),
                        'name' => (string) $type->getAttribute(BusinessType::NAME),
                        'descripcion' => $type->getAttribute(BusinessType::DESCRIPCION),
                    ])
                    ->values()
                    ->all(),
            ],
            default => [],
        };
    }

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function create(string $slug, int $companyId, int $userId, array $input): Model
    {
        $data = $this->validate($slug, $companyId, $input);

        return match ($slug) {
            'areas' => Area::query()->create([
                Area::COMPANY_ID => $companyId,
                Area::CODE => $data['code'],
                Area::NAME => $data['name'],
                Area::CREATED_USER_ID => $userId,
            ]),
            'puestos' => Position::query()->create([
                Position::COMPANY_ID => $companyId,
                Position::AREA_ID => $data['area_id'] ?? null,
                Position::CODE => $data['code'],
                Position::NAME => $data['name'],
                Position::CREATED_USER_ID => $userId,
            ]),
            'frecuencias' => PayrollFrequency::query()->create([
                PayrollFrequency::CODE => $data['code'],
                PayrollFrequency::NAME => $data['name'],
                PayrollFrequency::DAYS => (int) $data['days'],
                PayrollFrequency::CREATED_USER_ID => $userId,
            ]),
            'conceptos' => PayrollConcept::query()->create([
                PayrollConcept::COMPANY_ID => $companyId,
                PayrollConcept::PAYROLL_CONCEPT_TYPE_ID => (int) $data['payroll_concept_type_id'],
                PayrollConcept::CODE => $data['code'],
                PayrollConcept::NAME => $data['name'],
                PayrollConcept::IS_ACTIVE => (bool) ($data['is_active'] ?? true),
                PayrollConcept::CREATED_USER_ID => $userId,
            ]),
            'nacionalidades' => Nationality::query()->create([
                Nationality::NATIONALITY => $data['nationality'],
                Nationality::C_PAIS => $data['c_pais'] ?? null,
                Nationality::COUNTRY => $data['country'] ?? null,
                Nationality::CREATED_USER_ID => $userId,
            ]),
            'tipos-negocio' => BusinessType::query()->create([
                BusinessType::CODE => $data['code'],
                BusinessType::NAME => $data['name'],
                BusinessType::DESCRIPCION => $data['descripcion'] ?? null,
                BusinessType::CREATED_USER_ID => $userId,
            ]),
        };
    }

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function update(string $slug, int $companyId, int $userId, int $id, array $input): Model
    {
        $record = $this->findForCompany($slug, $companyId, $id);
        $data = $this->validate($slug, $companyId, $input, $id);

        $attributes = match ($slug) {
            'areas' => [
                Area::CODE => $data['code'],
                Area::NAME => $data['name'],
            ],
            'puestos' => [
                Position::AREA_ID => $data['area_id'] ?? null,
                Position::CODE => $data['code'],
                Position::NAME => $data['name'],
            ],
            'frecuencias' => [
                PayrollFrequency::CODE => $data['code'],
                PayrollFrequency::NAME => $data['name'],
                PayrollFrequency::DAYS => (int) $data['days'],
            ],
            'conceptos' => [
                PayrollConcept::PAYROLL_CONCEPT_TYPE_ID => (int) $data['payroll_concept_type_id'],
                PayrollConcept::CODE => $data['code'],
                PayrollConcept::NAME => $data['name'],
                PayrollConcept::IS_ACTIVE => (bool) ($data['is_active'] ?? true),
            ],
            'nacionalidades' => [
                Nationality::NATIONALITY => $data['nationality'],
                Nationality::C_PAIS => $data['c_pais'] ?? null,
                Nationality::COUNTRY => $data['country'] ?? null,
            ],
            'tipos-negocio' => [
                BusinessType::CODE => $data['code'],
                BusinessType::NAME => $data['name'],
                BusinessType::DESCRIPCION => $data['descripcion'] ?? null,
            ],
        };

        $record->fill([...$attributes, 'updated_user_id' => $userId]);
        $record->save();

        return $record;
    }

    /**
     * @throws ValidationException
     */
    public function delete(string $slug, int $companyId, int $userId, int $id): void
    {
        $record = $this->findForCompany($slug, $companyId, $id);
        $this->ensureCanDelete($slug, $companyId, $id);

        $record->setAttribute('deleted_user_id', $userId);
        $record->save();
        $record->delete();
    }

    private function findForCompany(string $slug, int $companyId, int $id): Model
    {
        return match ($slug) {
            'areas' => Area::query()->where(Area::COMPANY_ID, $companyId)->findOrFail($id),
            'puestos' => Position::query()->where(Position::COMPANY_ID, $companyId)->findOrFail($id),
            'frecuencias' => PayrollFrequency::query()->findOrFail($id),
            'conceptos' => PayrollConcept::query()->where(PayrollConcept::COMPANY_ID, $companyId)->findOrFail($id),
            'nacionalidades' => Nationality::query()->findOrFail($id),
            'tipos-negocio' => BusinessType::query()->findOrFail($id),
        };
    }

    /**
     * @throws ValidationException
     */
    private function ensureCanDelete(string $slug, int $companyId, int $id): void
    {
        $inUseMessage = match ($slug) {
            'areas' => Position::query()->where(Position::COMPANY_ID, $companyId)->where(Position::AREA_ID, $id)->exists()
                || Employee::query()->where(Employee::AREA_ID, $id)->exists()
                ? 'No se puede eliminar esta área porque tiene puestos o empleados activos asociados.'
                : null,
            'puestos' => Employee::query()->where(Employee::POSITION_ID, $id)->exists()
                ? 'No se puede eliminar este puesto porque hay empleados activos asignados.'
                : null,
            'frecuencias' => Employee::query()->where(Employee::PAYROLL_FREQUENCY_ID, $id)->exists()
                || PayrollPeriod::query()->where(PayrollPeriod::PAYROLL_FREQUENCY_ID, $id)->exists()
                ? 'No se puede eliminar esta frecuencia porque está asignada a empleados o períodos de nómina.'
                : null,
            'conceptos' => PayrollDetail::query()->where(PayrollDetail::PAYROLL_CONCEPT_ID, $id)->whereNull(PayrollDetail::DELETED_AT)->exists()
                ? 'No se puede eliminar este concepto porque ya aparece en detalles de pre-nómina.'
                : null,
            'nacionalidades' => Employee::query()->where(Employee::NATIONALITY_ID, $id)->exists()
                ? 'No se puede eliminar esta nacionalidad porque hay empleados registrados con ella.'
                : null,
            'tipos-negocio' => Customer::query()->where(Customer::BUSINESS_TYPE_ID, $id)->whereNull(Customer::DELETED_AT)->exists()
                ? 'No se puede eliminar este tipo de negocio porque hay clientes asignados.'
                : null,
            default => null,
        };

        if ($inUseMessage !== null) {
            throw ValidationException::withMessages(['catalog' => $inUseMessage]);
        }
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    private function validate(string $slug, int $companyId, array $input, ?int $ignoreId = null): array
    {
        $uniqueInCompany = static fn (string $table, string $column) => Rule::unique($table, $column)
            ->where(fn ($query) => $query->where('company_id', $companyId)->whereNull('deleted_at'))
            ->ignore($ignoreId);

        $uniqueGlobal = static fn (string $table, string $column) => Rule::unique($table, $column)
            ->where(fn ($query) => $query->whereNull('deleted_at'))
            ->ignore($ignoreId);

        $rules = match ($slug) {
            'areas' => [
                'code' => ['required', 'string', 'max:50', $uniqueInCompany('areas', 'code')],
                'name' => ['required', 'string', 'max:150'],
            ],
            'puestos' => [
                'code' => ['required', 'string', 'max:50', $uniqueInCompany('positions', 'code')],
                'name' => ['required', 'string', 'max:150'],
                'area_id' => [
                    'nullable',
                    'integer',
                    Rule::exists('areas', 'id')->where(fn ($q) => $q->where('company_id', $companyId)->whereNull('deleted_at')),
                ],
            ],
            'frecuencias' => [
                'code' => ['required', 'string', 'max:50', $uniqueGlobal('payroll_frequencies', 'code')],
                'name' => ['required', 'string', 'max:100'],
                'days' => ['required', 'integer', 'min:1', 'max:365'],
            ],
            'conceptos' => [
                'code' => ['required', 'string', 'max:50', $uniqueInCompany('payroll_concepts', 'code')],
                'name' => ['required', 'string', 'max:150'],
                'payroll_concept_type_id' => [
                    'required',
                    'integer',
                    Rule::exists('payroll_concept_types', 'id')->where(fn ($q) => $q->whereNull('deleted_at')),
                ],
                'is_active' => ['sometimes', 'boolean'],
            ],
            'nacionalidades' => [
                'nationality' => ['required', 'string', 'max:100', $uniqueGlobal('nationalities', 'nationality')],
                'c_pais' => ['nullable', 'string', 'max:10'],
                'country' => ['nullable', 'string', 'max:100'],
            ],
            'tipos-negocio' => [
                'code' => ['required', 'string', 'max:50', $uniqueGlobal('business_types', 'code')],
                'name' => ['required', 'string', 'max:100'],
                'descripcion' => ['nullable', 'string', 'max:255'],
            ],
        };

        $messages = [
            'code.required' => 'La clave es requerida.',
            'code.unique' => 'Ya existe un registro activo con esa clave.',
            'name.required' => 'El nombre es requerido.',
            'days.required' => 'Los días del período son requeridos.',
            'days.min' => 'Los días deben ser al menos 1.',
            'payroll_concept_type_id.required' => 'Selecciona si es percepción o deducción.',
            'nationality.required' => 'El gentilicio es requerido.',
            'nationality.unique' => 'Esa nacionalidad ya está registrada.',
        ];

        $normalized = $input;
        if (isset($normalized['code']) && is_string($normalized['code'])) {
            $normalized['code'] = $slug === 'frecuencias'
                ? mb_strtolower(trim($normalized['code']))
                : mb_strtoupper(trim($normalized['code']));
        }
        if (isset($normalized['c_pais']) && is_string($normalized['c_pais'])) {
            $trimmed = trim($normalized['c_pais']);
            $normalized['c_pais'] = $trimmed === '' ? null : mb_strtoupper($trimmed);
        }

        return Validator::make($normalized, $rules, $messages)->validate();
    }
}
