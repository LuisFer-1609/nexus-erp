<?php

use App\Models\AiChunk;
use App\Models\AiSyncRun;
use App\Services\Ai\Retriever;
use App\Services\Ai\Sync\SyncService;
use Database\Seeders\NexusAiDemoSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * La carga lee information_schema, así que estas pruebas requieren MySQL o
 * MariaDB. En SQLite (por ejemplo, en el CI) se omiten.
 */
$requiresMysql = fn (): bool => ! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true);

beforeEach(function () {
    config()->set('nexus_ai.driver', 'local');
});

/** Id de la empresa demo: el AUTO_INCREMENT de MySQL no se reinicia con el rollback entre pruebas. */
function demoAuroraId(): int
{
    return (int) DB::table('companies')->where('name', 'Distribuidora Aurora (Demo)')->value('id');
}

function runSync(?string $table = null): AiSyncRun
{
    return app(SyncService::class)->run('manual', null, $table);
}

test('cada fragmento queda marcado con su empresa, también en tablas que llegan a ella por llaves foráneas', function () {
    $this->seed(NexusAiDemoSeeder::class);

    $run = runSync();

    expect($run->status)->toBe(AiSyncRun::STATUS_COMPLETED)
        ->and($run->chunks_created)->toBeGreaterThan(100);

    expect(AiChunk::query()->distinct()->pluck('company_id')->sort()->values()->all())
        ->toBe(collect([1, demoAuroraId()])->sort()->values()->all());

    // attendances no tiene company_id: llega a la empresa por empleado → área.
    $attendanceCompany = DB::table('attendances as a')
        ->join('employees as e', 'e.id', '=', 'a.employee_id')
        ->join('areas as ar', 'ar.id', '=', 'e.area_id')
        ->pluck('ar.company_id', 'a.id');

    $chunks = AiChunk::query()->where('source_table', 'attendances')->where('kind', 'row')->get();
    expect($chunks)->toHaveCount($attendanceCompany->count());
    foreach ($chunks as $chunk) {
        expect($chunk->company_id)->toBe((int) $attendanceCompany[(int) $chunk->source_id]);
    }
})->skip($requiresMysql, 'Requiere MySQL/MariaDB (information_schema).');

test('la búsqueda de una empresa nunca devuelve datos de otra', function () {
    $this->seed(NexusAiDemoSeeder::class);
    runSync();
    $retriever = app(Retriever::class);

    // Aun sin umbral (devuelve todo lo que pueda), solo salen fragmentos de la empresa consultada.
    foreach ([1, demoAuroraId()] as $company) {
        $results = $retriever->retrieve($company, 'leads empleados asistencias', 500, -1.0);
        expect($results)->not->toBeEmpty();
        foreach ($results as $chunk) {
            expect($chunk->companyId)->toBe($company);
        }
    }

    // Roberto Quiroz es un lead de la empresa 2.
    $fromCompany1 = $retriever->retrieve(1, 'Roberto Quiroz', 500, -1.0);
    expect(collect($fromCompany1)->contains(fn ($c) => str_contains($c->content, 'Roberto Quiroz')))->toBeFalse();

    $fromCompany2 = $retriever->retrieve(demoAuroraId(), 'Roberto Quiroz', 3, 0.1);
    expect(collect($fromCompany2)->contains(fn ($c) => str_contains($c->content, 'Roberto Quiroz')))->toBeTrue();
})->skip($requiresMysql, 'Requiere MySQL/MariaDB (information_schema).');

test('contraseñas, tokens y datos personales sensibles nunca se guardan', function () {
    $this->seed(NexusAiDemoSeeder::class);
    DB::table('employees')->where('code', 'EMP-N01')->update(['curp' => 'ABCD123456HDFXYZ01', 'ssn' => '12345678901']);
    runSync();

    $all = AiChunk::query()->pluck('content')->implode("\n");

    expect($all)->not->toContain('$2y$')
        ->and($all)->not->toContain('passwd')
        ->and($all)->not->toContain('ABCD123456HDFXYZ01')
        ->and($all)->not->toContain('12345678901');
})->skip($requiresMysql, 'Requiere MySQL/MariaDB (information_schema).');

test('los empleados se nombran con su nombre y no con el de su área', function () {
    $this->seed(NexusAiDemoSeeder::class);
    runSync();

    $attendance = AiChunk::query()->where('source_table', 'attendances')->where('company_id', 1)->where('kind', 'row')->value('content');

    expect($attendance)->toMatch('/employee_id: (Ana Torres|Luis Mendoza|Sofía Ramírez) \(id \d+\)/');
})->skip($requiresMysql, 'Requiere MySQL/MariaDB (information_schema).');

test('el resumen permite contar: incluye totales y también el cero cuando no hay registros', function () {
    $this->seed(NexusAiDemoSeeder::class);
    runSync();

    $leads = AiChunk::query()->where(['company_id' => 1, 'source_table' => 'leads', 'kind' => 'summary'])->value('content');
    $customers = AiChunk::query()->where(['company_id' => 1, 'source_table' => 'customers', 'kind' => 'summary'])->value('content');

    expect($leads)->toContain('Total de registros: 6.')->toContain('priority: alta → 3');
    expect($customers)->toContain('total: 0');
})->skip($requiresMysql, 'Requiere MySQL/MariaDB (information_schema).');

test('la recarga solo vuelve a procesar lo que cambió, agrega lo nuevo y elimina lo borrado', function () {
    $this->seed(NexusAiDemoSeeder::class);
    runSync();

    // Sin cambios: no se recalcula nada.
    $same = runSync();
    expect($same->chunks_created)->toBe(0)->and($same->chunks_updated)->toBe(0)->and($same->chunks_deleted)->toBe(0)
        ->and($same->chunks_unchanged)->toBeGreaterThan(100);

    // Cambia un dato: se actualiza solo ese fragmento.
    DB::table('leads')->where('code', 'LD-N01')->update(['full_name' => 'Carlos Beltrán Reyes']);
    $changed = runSync();
    expect($changed->chunks_updated)->toBe(1)->and($changed->chunks_created)->toBe(0);
    $leadId = (string) DB::table('leads')->where('code', 'LD-N01')->value('id');
    expect(AiChunk::query()->where(['source_table' => 'leads', 'source_id' => $leadId, 'kind' => 'row'])->value('content'))
        ->toContain('Carlos Beltrán Reyes');

    // Agrega un registro: se crea su fragmento y se actualiza el resumen.
    DB::table('leads')->insert([
        'company_id' => 1, 'lead_source_id' => 1, 'lead_stage_id' => 1,
        'responsible_user_id' => (int) DB::table('users')->where('company_id', 1)->value('id'),
        'code' => 'LD-N99', 'full_name' => 'Cliente Nuevo SA', 'priority' => 'baja',
    ]);
    $added = runSync();
    expect($added->chunks_created)->toBe(1)->and($added->chunks_updated)->toBe(1);

    // Cambia un dato de otra empresa: no toca a la empresa 1.
    DB::table('leads')->where('code', 'LD-A01')->update(['priority' => 'baja']);
    runSync();
    $company2Lead = (string) DB::table('leads')->where('code', 'LD-A01')->value('id');
    expect(AiChunk::query()->where(['source_table' => 'leads', 'source_id' => $company2Lead])->first()->company_id)->toBe(demoAuroraId());

    // Elimina (borrado lógico y físico): sus fragmentos desaparecen.
    DB::table('leads')->where('code', 'LD-N99')->update(['deleted_at' => now()]);
    DB::table('leads')->where('code', 'LD-N02')->delete();
    $removed = runSync();
    expect($removed->chunks_deleted)->toBe(2);
    expect(AiChunk::query()->where('source_table', 'leads')->where('kind', 'row')->where('company_id', 1)->count())->toBe(5);
})->skip($requiresMysql, 'Requiere MySQL/MariaDB (information_schema).');

test('aprende solo una tabla y una columna nuevas, sin cambiar código', function () {
    // El DDL confirma la transacción de la prueba, por eso se limpia todo al final.
    Schema::dropIfExists('probe_notes');
    Schema::create('probe_notes', function ($table) {
        $table->integer('id', autoIncrement: true);
        $table->integer('company_id');
        $table->string('note', 255);
    });

    try {
        DB::table('probe_notes')->insert(['company_id' => 1, 'note' => 'Reunión con proveedor de empaques']);

        $first = runSync('probe_notes');
        expect($first->status)->toBe(AiSyncRun::STATUS_COMPLETED)
            ->and($first->details['schema']['snapshot'])->toHaveKey('probe_notes');
        expect(AiChunk::query()->where('source_table', 'probe_notes')->value('content'))
            ->toContain('Reunión con proveedor de empaques');
        expect(AiChunk::query()->where('source_table', 'probe_notes')->value('company_id'))->toBe(1);

        // Cambio estructural: columna nueva. Se detecta y el contenido ya la incluye.
        Schema::table('probe_notes', fn ($table) => $table->string('estado', 30)->nullable());
        DB::table('probe_notes')->update(['estado' => 'pendiente']);

        $second = runSync('probe_notes');
        expect($second->details['schema_changes']['has_changes'])->toBeTrue()
            ->and($second->details['schema_changes']['changed_tables']['probe_notes']['added'])->toBe(['estado:varchar(30)']);
        expect(AiChunk::query()->where('source_table', 'probe_notes')->value('content'))->toContain('estado: pendiente');

        // Si la tabla desaparece, la siguiente carga completa borra su conocimiento.
        Schema::drop('probe_notes');
        runSync();
        expect(AiChunk::query()->where('source_table', 'probe_notes')->count())->toBe(0);
    } finally {
        Schema::dropIfExists('probe_notes');
        AiChunk::query()->delete();
        AiSyncRun::query()->delete();
    }
})->skip($requiresMysql, 'Requiere MySQL/MariaDB (information_schema).');

test('sin proveedor de embeddings configurado la carga falla con un mensaje claro y no deja datos a medias', function () {
    config()->set('nexus_ai.driver', 'gemini');
    config()->set('nexus_ai.gemini.keys', []);

    $run = runSync();

    expect($run->status)->toBe(AiSyncRun::STATUS_FAILED)
        ->and($run->error)->toContain('GEMINI_API_KEYS');
    expect(AiChunk::query()->count())->toBe(0);
})->skip($requiresMysql, 'Requiere MySQL/MariaDB (information_schema).');
