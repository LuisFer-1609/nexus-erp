<?php

use App\Models\Nexus\Area;
use App\Models\Nexus\Attendance;
use App\Models\Nexus\BusinessType;
use App\Models\Nexus\Employee;
use App\Models\Nexus\Nationality;
use App\Models\Nexus\PayrollConcept;
use App\Models\Nexus\PayrollFrequency;
use App\Models\Nexus\Position;
use App\Models\Nexus\WorkSchedule;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

$requiresMysql = fn (): bool => ! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true);

function createCompanyUser(?int $companyId = 1): User
{
    $user = User::factory()->create();
    $user->forceFill([User::COMPANY_ID => $companyId])->save();

    return $user;
}

test('se pueden administrar los 6 catálogos de pre-nómina con aislamiento por empresa', function () {
    $user = createCompanyUser(1);
    $otherCompanyId = (int) DB::table('companies')->insertGetId([
        'name' => 'Empresa Externa',
        'business_name' => 'Empresa Externa SA',
        'rfc' => 'EEX010101000',
        'type_of_business' => 'Servicios',
        'mail' => 'ext@test.com',
        'phone' => '8710000001',
        'address' => 'Calle 1',
        'is_active' => 1,
    ]);
    $otherArea = Area::query()->create([
        Area::COMPANY_ID => $otherCompanyId,
        Area::CODE => 'EXT',
        Area::NAME => 'Área Externa',
    ]);

    $this->actingAs($user);

    // 1. Áreas (CRUD + aislamiento)
    $this->get(route('pre-nomina.catalogos.areas'))
        ->assertOk()
        ->assertDontSee('Área Externa');

    $this->post(route('pre-nomina.catalogos.store', 'areas'), [
        'code' => 'cal',
        'name' => 'Calidad',
    ])->assertRedirect();

    $area = Area::query()->where(Area::COMPANY_ID, 1)->where(Area::CODE, 'CAL')->firstOrFail();

    $this->put(route('pre-nomina.catalogos.update', ['areas', $area->id]), [
        'code' => 'cal',
        'name' => 'Aseguramiento de Calidad',
    ])->assertRedirect();
    expect($area->fresh()?->name)->toBe('Aseguramiento de Calidad');

    // No puede editar área de otra empresa
    $this->put(route('pre-nomina.catalogos.update', ['areas', $otherArea->id]), [
        'code' => 'HACK',
        'name' => 'Hack',
    ])->assertNotFound();

    // 2. Puestos
    $this->post(route('pre-nomina.catalogos.store', 'puestos'), [
        'code' => 'qa',
        'name' => 'Analista QA',
        'area_id' => $area->id,
    ])->assertRedirect();
    $position = Position::query()->where(Position::COMPANY_ID, 1)->where(Position::CODE, 'QA')->firstOrFail();

    // Protege eliminar área mientras tenga un puesto asociado
    $this->delete(route('pre-nomina.catalogos.destroy', ['areas', $area->id]))
        ->assertSessionHasErrors('catalog');

    $this->delete(route('pre-nomina.catalogos.destroy', ['puestos', $position->id]))->assertRedirect();
    $this->delete(route('pre-nomina.catalogos.destroy', ['areas', $area->id]))->assertRedirect();

    // 3. Frecuencias
    $this->post(route('pre-nomina.catalogos.store', 'frecuencias'), [
        'code' => 'catorcenal',
        'name' => 'Catorcenal',
        'days' => 14,
    ])->assertRedirect();
    $freq = PayrollFrequency::query()->where(PayrollFrequency::CODE, 'catorcenal')->firstOrFail();
    $this->delete(route('pre-nomina.catalogos.destroy', ['frecuencias', $freq->id]))->assertRedirect();

    // 4. Conceptos
    $this->post(route('pre-nomina.catalogos.store', 'conceptos'), [
        'code' => 'val',
        'name' => 'Vales de despensa',
        'payroll_concept_type_id' => 1,
        'is_active' => true,
    ])->assertRedirect();
    $concept = PayrollConcept::query()->where(PayrollConcept::COMPANY_ID, 1)->where(PayrollConcept::CODE, 'VAL')->firstOrFail();
    $this->delete(route('pre-nomina.catalogos.destroy', ['conceptos', $concept->id]))->assertRedirect();

    // 5. Nacionalidades
    $this->post(route('pre-nomina.catalogos.store', 'nacionalidades'), [
        'nationality' => 'Prueba',
        'c_pais' => 'prb',
        'country' => 'País Prueba',
    ])->assertRedirect();
    $nat = Nationality::query()->where(Nationality::NATIONALITY, 'Prueba')->firstOrFail();
    $this->delete(route('pre-nomina.catalogos.destroy', ['nacionalidades', $nat->id]))->assertRedirect();

    // 6. Tipos de negocio
    $this->post(route('pre-nomina.catalogos.store', 'tipos-negocio'), [
        'code' => 'log',
        'name' => 'Logística',
        'descripcion' => 'Transporte y distribución',
    ])->assertRedirect();
    $bt = BusinessType::query()->where(BusinessType::CODE, 'LOG')->firstOrFail();
    $this->delete(route('pre-nomina.catalogos.destroy', ['tipos-negocio', $bt->id]))->assertRedirect();
})->skip($requiresMysql, 'Requiere MySQL/MariaDB.');

test('crud de empleados relaciona catálogos, horarios y estadísticas de asistencias', function () {
    $user = createCompanyUser(1);
    $this->actingAs($user);

    $area = Area::query()->where(Area::COMPANY_ID, 1)->firstOrFail();
    $position = Position::query()->where(Position::COMPANY_ID, 1)->firstOrFail();
    $nationality = Nationality::query()->firstOrFail();
    $frequency = PayrollFrequency::query()->firstOrFail();

    $this->post(route('pre-nomina.empleados.store'), [
        'first_name' => 'Lucía',
        'last_name' => 'Méndez',
        'phone' => '8711234567',
        'code' => 'EMP-901',
        'sex' => 'Femenino',
        'curp' => 'MELU950101MNLNPR01',
        'ssn' => '12345678901',
        'hire_date' => '2026-11-01',
        'nationality_id' => $nationality->id,
        'area_id' => $area->id,
        'position_id' => $position->id,
        'payroll_frequency_id' => $frequency->id,
        'status' => 'activo',
    ])->assertRedirect();

    $employee = Employee::query()->where(Employee::CODE, 'EMP-901')->firstOrFail();
    expect(WorkSchedule::query()->where(WorkSchedule::EMPLOYEE_ID, $employee->id)->count())->toBe(7);

    Attendance::query()->create([
        Attendance::EMPLOYEE_ID => $employee->id,
        Attendance::ATTENDANCE_DATE => '2026-11-10',
        Attendance::STATUS => 'absent',
        Attendance::LATE_MINUTES => 0,
        Attendance::OVERTIME_HOURS => 0,
    ]);

    $this->get(route('pre-nomina.empleados'))
        ->assertOk()
        ->assertSee('EMP-901');

    $this->put(route('pre-nomina.empleados.update', $employee->id), [
        'first_name' => 'Lucía',
        'last_name' => 'Méndez Pérez',
        'phone' => '8719990000',
        'code' => 'EMP-901',
        'sex' => 'Femenino',
        'curp' => 'MELU950101MNLNPR01',
        'ssn' => '12345678901',
        'hire_date' => '2026-11-01',
        'nationality_id' => $nationality->id,
        'area_id' => $area->id,
        'position_id' => $position->id,
        'payroll_frequency_id' => $frequency->id,
        'status' => 'activo',
    ])->assertRedirect();

    expect($employee->fresh()?->user->last_name)->toBe('Méndez Pérez');

    $this->delete(route('pre-nomina.empleados.destroy', $employee->id))->assertRedirect();
    expect(Employee::query()->find($employee->id))->toBeNull();
})->skip($requiresMysql, 'Requiere MySQL/MariaDB.');

test('importación csv marca errores en vista previa e importa solo los registros válidos', function () {
    $user = createCompanyUser(1);
    $this->actingAs($user);

    $area = Area::query()->where(Area::COMPANY_ID, 1)->firstOrFail();
    $position = Position::query()->where(Position::COMPANY_ID, 1)->firstOrFail();
    $nat = Nationality::query()->firstOrFail();
    $freq = PayrollFrequency::query()->firstOrFail();

    $csv = implode("\n", [
        'codigo,nombre,apellidos,telefono,sexo,curp,nss,fecha_ingreso,nacionalidad,area,puesto,frecuencia_nomina,estado',
        "EMP-CSV1,Diego,Vargas,8711112233,Masculino,VADI900101HNLNPR01,11122233344,2026-11-05,{$nat->nationality},{$area->code},{$position->code},{$freq->code},activo",
        "EMP-CSV2,,Sin Nombre,8710000000,Masculino,CURP_MALA,abc,2026-11-05,NoExiste,NOAREA,NOPOS,NOFREQ,activo",
    ]);

    $file = UploadedFile::fake()->createWithContent('empleados.csv', $csv);

    $preview = $this->postJson(route('pre-nomina.empleados.csv-preview'), ['file' => $file])
        ->assertOk()
        ->json();

    expect($preview['summary'])->toBe(['total' => 2, 'valid' => 1, 'invalid' => 1])
        ->and($preview['rows'][0]['valid'])->toBeTrue()
        ->and($preview['rows'][1]['valid'])->toBeFalse()
        ->and($preview['rows'][1]['errors'])->not->toBeEmpty();

    $this->post(route('pre-nomina.empleados.csv-confirm'), [
        'rows' => [$preview['rows'][0]['payload']],
    ])->assertRedirect();

    expect(Employee::query()->where(Employee::CODE, 'EMP-CSV1')->exists())->toBeTrue()
        ->and(Employee::query()->where(Employee::CODE, 'EMP-CSV2')->exists())->toBeFalse();
})->skip($requiresMysql, 'Requiere MySQL/MariaDB.');
