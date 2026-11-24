<?php

use App\Models\AiPendingAction;
use App\Models\AiReport;
use App\Services\Ai\Actions\PendingActionService;
use App\Services\Ai\Payroll\PreNominaService;
use App\Services\Ai\Tools\InvalidToolCallException;
use App\Services\Ai\Tools\ToolboxFactory;
use App\Services\Ai\Tools\ToolOutcome;
use Database\Seeders\NexusAiDemoSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/*
 * Estas pruebas usan information_schema (MySQL/MariaDB). En SQLite se omiten.
 */
$requiresMysql = fn (): bool => ! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true);

beforeEach(function () {
    Cache::flush();
    Storage::fake('local');
});

function auroraCompanyId(): int
{
    return (int) DB::table('companies')->where('name', 'Distribuidora Aurora (Demo)')->value('id');
}

function runTool(int $company, string $name, array $args = []): ToolOutcome
{
    return app(ToolboxFactory::class)->make($company, null, 'Tester')->execute($name, $args);
}

/** @return array<string, int|string> */
function keyed(ToolOutcome $outcome): array
{
    return collect($outcome->response['filas'])->mapWithKeys(fn (array $row): array => [$row[0] => $row[1]])->sortKeys()->all();
}

test('cuenta y agrupa con nombres legibles, no con ids', function () {
    $this->seed(NexusAiDemoSeeder::class);

    $outcome = runTool(1, 'consultar_datos', [
        'tabla' => 'leads',
        'agrupar_por' => [['columna' => 'lead_stage_id']],
        'agregaciones' => [['funcion' => 'count', 'columna' => '*', 'alias' => 'total']],
    ]);

    expect($outcome->response)->not->toHaveKey('error')
        ->and($outcome->response['columnas'])->toBe(['lead_stage_id', 'total'])
        ->and(array_map('intval', keyed($outcome)))->toBe(['Cerrado' => 1, 'Contactado' => 2, 'Negociación' => 1, 'Nuevo' => 2]);
})->skip($requiresMysql, 'Requiere MySQL/MariaDB.');

test('cada consulta se limita a la empresa: es imposible leer datos de otra', function () {
    $this->seed(NexusAiDemoSeeder::class);
    $aurora = auroraCompanyId();
    $args = ['tabla' => 'leads', 'columnas' => ['full_name'], 'limite' => 200];

    $nexus = collect(runTool(1, 'consultar_datos', $args)->response['filas'])->flatten();
    $other = collect(runTool($aurora, 'consultar_datos', $args)->response['filas'])->flatten();

    expect($nexus)->toHaveCount(6)->and($nexus)->toContain('Carlos Beltrán')->and($nexus)->not->toContain('Roberto Quiroz');
    expect($other)->toHaveCount(5)->and($other)->toContain('Roberto Quiroz')->and($other)->not->toContain('Carlos Beltrán');

    // Un parámetro de empresa que invente el modelo se ignora: no existe tal opción.
    $injected = runTool(1, 'consultar_datos', $args + ['company_id' => $aurora, 'empresa' => $aurora]);
    expect(collect($injected->response['filas'])->flatten())->not->toContain('Roberto Quiroz');
})->skip($requiresMysql, 'Requiere MySQL/MariaDB.');

test('las columnas sensibles no se pueden consultar ni aparecen por omisión', function () {
    $this->seed(NexusAiDemoSeeder::class);

    foreach ([['users', 'passwd'], ['employees', 'curp'], ['employees', 'ssn']] as [$table, $column]) {
        $outcome = runTool(1, 'consultar_datos', ['tabla' => $table, 'columnas' => [$column]]);

        expect($outcome->response)->toHaveKey('error')
            ->and($outcome->response['error'])->not->toContain('passwd, ')
            ->and($outcome->response)->not->toHaveKey('filas');
    }

    $listing = runTool(1, 'consultar_datos', ['tabla' => 'users']);
    expect($listing->response['columnas'])->not->toContain('passwd')
        ->and(json_encode($listing->response))->not->toContain('$2y$');
})->skip($requiresMysql, 'Requiere MySQL/MariaDB.');

test('una consulta inválida devuelve un error claro al modelo para que la corrija', function () {
    $this->seed(NexusAiDemoSeeder::class);

    expect(runTool(1, 'consultar_datos', ['tabla' => 'no_existe'])->response['error'])->toContain('Tablas disponibles');
    expect(runTool(1, 'consultar_datos', ['tabla' => 'leads', 'columnas' => ['inventada']])->response['error'])->toContain('Columnas disponibles');
    expect(runTool(1, 'consultar_datos', ['tabla' => 'leads', 'filtros' => [['columna' => 'priority', 'operador' => 'DROP']]])->response['error'])->toContain('Operador');
    expect(runTool(1, 'consultar_datos', ['tabla' => 'leads', 'agregaciones' => [['funcion' => 'sum', 'columna' => 'priority']]])->response['error'])->toContain('numéricas');
    expect(runTool(1, 'consultar_datos', ['tabla' => 'leads', 'filtros' => [['columna' => 'priority', 'operador' => '=']]])->response['error'])->toContain('value');
    expect(runTool(1, 'herramienta_falsa')->response['error'])->toContain('desconocida');
})->skip($requiresMysql, 'Requiere MySQL/MariaDB.');

test('los valores con comillas o SQL se tratan como texto y no rompen la consulta', function () {
    $this->seed(NexusAiDemoSeeder::class);

    $outcome = runTool(1, 'consultar_datos', [
        'tabla' => 'leads',
        'filtros' => [['columna' => 'full_name', 'operador' => 'contiene', 'valor' => "x'; DROP TABLE leads; --"]],
        'agregaciones' => [['funcion' => 'count', 'columna' => '*']],
    ]);

    expect($outcome->response['filas'])->toBe([[0]])->and(DB::table('leads')->count())->toBeGreaterThan(0);
})->skip($requiresMysql, 'Requiere MySQL/MariaDB.');

test('filtra por el nombre de la persona sin conocer su id y agrupa por mes', function () {
    $this->seed(NexusAiDemoSeeder::class);

    $ana = runTool(1, 'consultar_datos', [
        'tabla' => 'attendances',
        'filtros' => [['columna' => 'employee_id', 'operador' => 'etiqueta_contiene', 'valor' => 'Ana']],
        'agregaciones' => [['funcion' => 'count', 'columna' => '*']],
    ]);
    expect($ana->response['filas'])->toBe([[10]]);

    $nadie = runTool(1, 'consultar_datos', [
        'tabla' => 'attendances',
        'filtros' => [['columna' => 'employee_id', 'operador' => 'etiqueta_contiene', 'valor' => 'Inexistente']],
        'agregaciones' => [['funcion' => 'count', 'columna' => '*']],
    ]);
    expect($nadie->response['filas'])->toBe([[0]]);

    $byMonth = runTool(1, 'consultar_datos', [
        'tabla' => 'attendances',
        'agrupar_por' => [['columna' => 'attendance_date', 'periodo' => 'month']],
        'agregaciones' => [['funcion' => 'count', 'columna' => '*', 'alias' => 'registros']],
    ]);
    expect($byMonth->response['columnas'])->toBe(['attendance_date_month', 'registros']);
    foreach ($byMonth->response['filas'] as $row) {
        expect($row[0])->toMatch('/^\d{4}-\d{2}$/');
    }
    expect(array_sum(array_column($byMonth->response['filas'], 1)))->toBe(30);
})->skip($requiresMysql, 'Requiere MySQL/MariaDB.');

test('los totales y promedios de una columna numérica los calcula la base de datos', function () {
    $this->seed(NexusAiDemoSeeder::class);

    $outcome = runTool(1, 'consultar_datos', [
        'tabla' => 'attendances',
        'agregaciones' => [
            ['funcion' => 'sum', 'columna' => 'late_minutes', 'alias' => 'minutos'],
            ['funcion' => 'max', 'columna' => 'late_minutes', 'alias' => 'maximo'],
        ],
    ]);

    $expected = (int) DB::table('attendances as a')->join('employees as e', 'e.id', '=', 'a.employee_id')->join('users as u', 'u.id', '=', 'e.id_user')
        ->where('u.company_id', 1)->sum('a.late_minutes');

    expect((int) $outcome->response['filas'][0][0])->toBe($expected)
        ->and((int) $outcome->response['filas'][0][1])->toBe(17);
})->skip($requiresMysql, 'Requiere MySQL/MariaDB.');

test('el límite se respeta y avisa si hay más filas', function () {
    $this->seed(NexusAiDemoSeeder::class);

    $outcome = runTool(1, 'consultar_datos', ['tabla' => 'attendances', 'limite' => 5]);

    expect($outcome->response['filas'])->toHaveCount(5)->and($outcome->response['truncado'])->toBeTrue();
})->skip($requiresMysql, 'Requiere MySQL/MariaDB.');

test('genera un reporte PDF con datos reales y lo deja listo para descargar', function () {
    $this->seed(NexusAiDemoSeeder::class);

    $outcome = runTool(1, 'generar_reporte_pdf', [
        'titulo' => 'Leads por etapa',
        'subtitulo' => 'Noviembre de 2026',
        'secciones' => [
            ['titulo' => 'Por etapa', 'descripcion' => 'Cuántos leads hay en cada etapa.', 'consulta' => [
                'tabla' => 'leads',
                'agrupar_por' => [['columna' => 'lead_stage_id']],
                'agregaciones' => [['funcion' => 'count', 'columna' => '*', 'alias' => 'total']],
            ]],
            ['titulo' => 'Prioridad alta', 'consulta' => [
                'tabla' => 'leads', 'columnas' => ['full_name', 'email'],
                'filtros' => [['columna' => 'priority', 'operador' => '=', 'valor' => 'alta']],
            ]],
        ],
    ]);

    expect($outcome->response)->not->toHaveKey('error')
        ->and($outcome->response['reporte_listo'])->toBeTrue()
        ->and($outcome->artifacts)->toHaveCount(1)
        ->and($outcome->artifacts[0]['url'])->toContain('/ia/reportes/');

    $report = AiReport::query()->findOrFail($outcome->artifacts[0]['id']);
    $pdf = Storage::disk('local')->get($report->path);

    expect($report->company_id)->toBe(1)
        ->and($report->meta['sections'])->toBe(2)
        ->and($report->meta['rows'])->toBe(4 + 3)
        ->and(str_starts_with((string) $pdf, '%PDF'))->toBeTrue()
        ->and(strlen((string) $pdf))->toBeGreaterThan(2000);
})->skip($requiresMysql, 'Requiere MySQL/MariaDB.');

test('un reporte con una consulta inválida no se genera y se le explica al modelo', function () {
    $this->seed(NexusAiDemoSeeder::class);

    $outcome = runTool(1, 'generar_reporte_pdf', [
        'titulo' => 'Roto',
        'secciones' => [['titulo' => 'Mala', 'consulta' => ['tabla' => 'no_existe']]],
    ]);

    expect($outcome->response)->toHaveKey('error')->and($outcome->artifacts)->toBe([]);
    expect(AiReport::query()->count())->toBe(0);
})->skip($requiresMysql, 'Requiere MySQL/MariaDB.');

test('la pre-nómina se calcula con las asistencias y descuenta las faltas justificadas', function () {
    $this->seed(NexusAiDemoSeeder::class);
    $from = now()->subDays(20)->toDateString();
    $to = now()->toDateString();

    $service = app(PreNominaService::class);
    $preview = $service->preview(1, $from, $to, 'quincenal');

    expect($preview['rows'])->toHaveCount(3)->and($preview['totals']['empleados'])->toBe(3);
    $first = $preview['rows'][0];
    $expected = DB::table('attendances')->where('employee_id', $first['employee_id'])->whereBetween('attendance_date', [$from, $to])
        ->selectRaw("sum(status in ('presente','retardo')) w, sum(status='retardo') l, sum(status='falta') f")->first();
    expect($first['dias_trabajados'])->toBe((int) $expected->w)
        ->and($first['retardos'])->toBe((int) $expected->l)
        ->and($first['faltas'])->toBe((int) $expected->f);

    // Una incidencia justificada que cubre una falta deja de contarla.
    $absence = DB::table('attendances')->where('employee_id', $first['employee_id'])->where('status', 'falta')->first();
    if ($absence !== null) {
        DB::table('incidents')->insert([
            'employee_id' => $first['employee_id'], 'incident_type_id' => (int) DB::table('incident_types')->value('id'),
            'start_date' => $absence->attendance_date, 'end_date' => $absence->attendance_date, 'status' => 'justificada',
        ]);
        $after = $service->preview(1, $from, $to, 'quincenal')['rows'][0];
        expect($after['faltas'])->toBe($first['faltas'] - 1)->and($after['faltas_justificadas'])->toBe(1);
    }
})->skip($requiresMysql, 'Requiere MySQL/MariaDB.');

test('la pre-nómina valida fechas, periodo y frecuencia con mensajes claros', function () {
    $this->seed(NexusAiDemoSeeder::class);
    $service = app(PreNominaService::class);

    expect(fn () => $service->preview(1, '15/11/2026', '2026-11-30'))->toThrow(InvalidToolCallException::class, 'AAAA-MM-DD');
    expect(fn () => $service->preview(1, '2026-11-30', '2026-11-01'))->toThrow(InvalidToolCallException::class, 'anterior');
    expect(fn () => $service->preview(1, '2026-01-01', '2026-12-31'))->toThrow(InvalidToolCallException::class, 'más de 31 días');
    expect(fn () => $service->preview(1, '2026-11-01', '2026-11-15', 'bimestral'))->toThrow(InvalidToolCallException::class, 'Opciones');
    expect(fn () => $service->preview(1, '2026-11-01', '2026-11-15', 'mensual'))->toThrow(InvalidToolCallException::class, 'No hay empleados');
})->skip($requiresMysql, 'Requiere MySQL/MariaDB.');

test('preparar la pre-nómina NO guarda nada: solo deja una acción pendiente de confirmar', function () {
    $this->seed(NexusAiDemoSeeder::class);

    $outcome = runTool(1, 'preparar_prenomina', ['inicio' => now()->subDays(14)->toDateString(), 'fin' => now()->toDateString(), 'frecuencia' => 'quincenal']);

    expect($outcome->response['accion_pendiente'])->toBeTrue()
        ->and($outcome->response['vista_previa']['totales']['empleados'])->toBe(3)
        ->and($outcome->actions)->toHaveCount(1)
        ->and($outcome->actions[0]['confirmLabel'])->toBe('Generar pre-nómina');

    expect(DB::table('payroll_periods')->count())->toBe(0)->and(DB::table('payrolls')->count())->toBe(0);
    expect(AiPendingAction::query()->where('status', 'pending')->count())->toBe(1);
})->skip($requiresMysql, 'Requiere MySQL/MariaDB.');

test('al confirmar se genera la pre-nómina y su PDF una sola vez; regenerar actualiza sin duplicar', function () {
    $this->seed(NexusAiDemoSeeder::class);
    $args = ['inicio' => now()->subDays(14)->toDateString(), 'fin' => now()->toDateString(), 'frecuencia' => 'quincenal'];
    $service = app(PendingActionService::class);

    $first = AiPendingAction::query()->findOrFail(runTool(1, 'preparar_prenomina', $args)->actions[0]['id']);
    $result = $service->confirm($first, 'Tester');

    expect($result['message'])->toContain('quedó generada')
        ->and($result['report'])->toBeInstanceOf(AiReport::class)
        ->and(DB::table('payroll_periods')->count())->toBe(1)
        ->and(DB::table('payrolls')->count())->toBe(3);
    expect(DB::table('payrolls')->pluck('net_amount')->unique()->all())->toBe(['0.00']);
    expect(str_starts_with((string) Storage::disk('local')->get($result['report']->path), '%PDF'))->toBeTrue();

    // Una segunda confirmación de la misma acción no hace nada.
    expect(fn () => $service->confirm($first->fresh(), 'Tester'))->toThrow(InvalidToolCallException::class, 'ya fue atendida');
    expect(DB::table('payrolls')->count())->toBe(3);

    // Pedir lo mismo otra vez actualiza el periodo, no lo duplica.
    $second = AiPendingAction::query()->findOrFail(runTool(1, 'preparar_prenomina', $args)->actions[0]['id']);
    expect($service->confirm($second, 'Tester')['message'])->toContain('se actualizó');
    expect(DB::table('payroll_periods')->count())->toBe(1)->and(DB::table('payrolls')->count())->toBe(3);

    // Un periodo exportado ya no se puede tocar.
    DB::table('payroll_periods')->update(['status' => 'exportado']);
    $third = AiPendingAction::query()->findOrFail(runTool(1, 'preparar_prenomina', $args)->actions[0]['id']);
    expect(fn () => $service->confirm($third, 'Tester'))->toThrow(InvalidToolCallException::class, 'exportado');
})->skip($requiresMysql, 'Requiere MySQL/MariaDB.');

test('una acción vencida no se ejecuta', function () {
    $this->seed(NexusAiDemoSeeder::class);
    $action = AiPendingAction::query()->findOrFail(runTool(1, 'preparar_prenomina', [
        'inicio' => now()->subDays(14)->toDateString(), 'fin' => now()->toDateString(), 'frecuencia' => 'quincenal',
    ])->actions[0]['id']);
    $action->forceFill(['expires_at' => now()->subMinute()])->save();

    expect(fn () => app(PendingActionService::class)->confirm($action->fresh(), 'Tester'))->toThrow(InvalidToolCallException::class, 'expiró');
    expect(DB::table('payrolls')->count())->toBe(0);
})->skip($requiresMysql, 'Requiere MySQL/MariaDB.');

test('las novedades de la plataforma y las aclaraciones funcionan como herramientas', function () {
    $updates = runTool(1, 'novedades_plataforma', ['limite' => 5]);
    expect($updates->response)->toHaveKey('novedades');
    foreach ($updates->response['novedades'] as $item) {
        expect($item)->toHaveKeys(['fecha', 'tipo', 'cambio']);
    }

    expect(runTool(1, 'pedir_aclaracion', ['pregunta' => '¿De qué periodo?'])->final)->toBe('¿De qué periodo?');
    expect(runTool(1, 'sin_datos')->final)->toContain('No cuento con esa información');
})->skip($requiresMysql, 'Requiere MySQL/MariaDB.');
