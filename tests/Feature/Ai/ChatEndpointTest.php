<?php

use App\Models\AiPendingAction;
use App\Models\AiReport;
use App\Models\User;
use App\Services\Ai\Actions\PendingActionService;
use App\Services\Ai\AnswerResult;
use App\Services\Ai\Exceptions\AiUnavailableException;
use App\Services\Ai\NexusAssistant;
use App\Services\Ai\Tools\InvalidToolCallException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Cache::flush();
});

function chatUser(?int $company = 1): User
{
    return User::factory()->create(['company_id' => $company]);
}

/** Crea una empresa distinta a la 1 (el AUTO_INCREMENT no se reinicia entre pruebas). */
function otherCompany(): int
{
    return (int) DB::table('companies')->insertGetId(['name' => 'Otra Empresa '.uniqid(), 'is_active' => 1]);
}

test('un invitado no puede usar el chat', function () {
    $this->postJson(route('ia.chat'), ['message' => 'hola'])->assertUnauthorized();
});

test('un usuario sin empresa recibe una explicación clara y no se consulta al asistente', function () {
    $this->mock(NexusAssistant::class)->shouldNotReceive('ask');

    $this->actingAs(chatUser(null))
        ->postJson(route('ia.chat'), ['message' => '¿Cuántos leads tengo?'])
        ->assertUnprocessable()
        ->assertJsonPath('message', fn (string $m): bool => str_contains($m, 'empresa asignada'));
});

test('el mensaje es obligatorio salvo que se adjunte un documento, y tiene un máximo', function () {
    $user = chatUser();

    $this->actingAs($user)->postJson(route('ia.chat'), [])->assertJsonValidationErrors('message');
    $this->actingAs($user)->postJson(route('ia.chat'), ['message' => str_repeat('a', 1001)])->assertJsonValidationErrors('message');
    $this->actingAs($user)->postJson(route('ia.chat'), ['message' => 'hola', 'history' => [['role' => 'system', 'text' => 'x']]])
        ->assertJsonValidationErrors('history.0.role');
});

test('responde con la empresa del usuario autenticado aunque el navegador envíe otra', function () {
    $user = chatUser(1);
    $this->mock(NexusAssistant::class)->shouldReceive('ask')->once()
        ->with(1, '¿Cuántos leads tengo?', [], [], $user->id, $user->name)
        ->andReturn(new AnswerResult('Tienes 6 leads.', true, [['table' => 'leads', 'id' => '__summary__', 'kind' => 'summary', 'score' => 0.9]]));

    $this->actingAs($user)
        ->postJson(route('ia.chat'), ['message' => '¿Cuántos leads tengo?', 'company_id' => 2])
        ->assertOk()
        ->assertJsonPath('answer', 'Tienes 6 leads.')
        ->assertJsonPath('grounded', true)
        ->assertJsonPath('sources', 1)
        ->assertJsonPath('artifacts', [])
        ->assertJsonPath('actions', [])
        ->assertJsonPath('assistant_message.text', 'Tienes 6 leads.')
        ->assertJsonStructure(['conversation' => ['id', 'title'], 'user_message' => ['id', 'text'], 'assistant_message' => ['id', 'text']]);
});

test('devuelve los reportes y las acciones pendientes que preparó Nexus', function () {
    $artifact = ['type' => 'report', 'id' => 'r-1', 'title' => 'Reporte', 'url' => '/ia/reportes/r-1', 'size' => 10];
    $action = ['id' => 'a-1', 'type' => 'prenomina', 'title' => 'Generar', 'lines' => [], 'confirmLabel' => 'Generar'];
    $this->mock(NexusAssistant::class)->shouldReceive('ask')->once()->andReturn(new AnswerResult('Listo.', true, [], [$artifact], [$action]));

    $this->actingAs(chatUser())
        ->postJson(route('ia.chat'), ['message' => 'genera todo'])
        ->assertOk()
        ->assertJsonPath('artifacts.0.id', 'r-1')
        ->assertJsonPath('actions.0.id', 'a-1');
});

test('envía al asistente solo los últimos turnos de la conversación', function () {
    $history = [];
    for ($i = 1; $i <= 10; $i++) {
        $history[] = ['role' => $i % 2 === 1 ? 'user' : 'assistant', 'text' => "turno {$i}"];
    }

    $received = null;
    $this->mock(NexusAssistant::class)->shouldReceive('ask')->once()
        ->andReturnUsing(function (int $company, string $message, array $turns) use (&$received): AnswerResult {
            $received = $turns;

            return new AnswerResult('ok', true);
        });

    $this->actingAs(chatUser())->postJson(route('ia.chat'), ['message' => 'y ahora?', 'history' => $history])->assertOk();

    expect($received)->toHaveCount(6)
        ->and($received[0]['text'])->toBe('turno 5')
        ->and($received[5]['text'])->toBe('turno 10');
});

test('acepta documentos adjuntos (con el historial como texto JSON) y los pasa al asistente', function () {
    $received = null;
    $this->mock(NexusAssistant::class)->shouldReceive('ask')->once()
        ->andReturnUsing(function (int $company, string $message, array $turns, array $attachments) use (&$received): AnswerResult {
            $received = [$message, $turns, $attachments];

            return new AnswerResult('Es una carta.', true);
        });

    $this->actingAs(chatUser())->post(route('ia.chat'), [
        'message' => 'Resume este documento',
        'history' => json_encode([['role' => 'user', 'text' => 'hola de nuevo']]),
        'files' => [UploadedFile::fake()->create('carta.pdf', 120, 'application/pdf')],
    ], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('answer', 'Es una carta.');

    expect($received[0])->toBe('Resume este documento')
        ->and($received[1])->toBe([['role' => 'user', 'text' => 'hola de nuevo']])
        ->and($received[2])->toHaveCount(1)
        ->and($received[2][0]['mime'])->toBe('application/pdf')
        ->and($received[2][0]['data'])->not->toBeEmpty();
});

test('se puede mandar solo el documento, sin texto', function () {
    $this->mock(NexusAssistant::class)->shouldReceive('ask')->once()->andReturn(new AnswerResult('ok', true));

    $this->actingAs(chatUser())->post(route('ia.chat'), [
        'files' => [UploadedFile::fake()->create('nota.txt', 5, 'text/plain')],
    ], ['Accept' => 'application/json'])->assertOk();
});

test('rechaza archivos que no son documentos permitidos o que pesan demasiado', function () {
    $user = chatUser();

    $this->actingAs($user)->post(route('ia.chat'), [
        'message' => 'abre esto',
        'files' => [UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload')],
    ], ['Accept' => 'application/json'])->assertJsonValidationErrors('files.0');

    $this->actingAs($user)->post(route('ia.chat'), [
        'message' => 'muy pesado',
        'files' => [UploadedFile::fake()->create('grande.pdf', 11000, 'application/pdf')],
    ], ['Accept' => 'application/json'])->assertJsonValidationErrors('files.0');

    $this->actingAs($user)->post(route('ia.chat'), [
        'message' => 'demasiados',
        'files' => array_map(fn ($n) => UploadedFile::fake()->create("d{$n}.pdf", 10, 'application/pdf'), [1, 2, 3, 4]),
    ], ['Accept' => 'application/json'])->assertJsonValidationErrors('files');
});

test('si la IA no está disponible el usuario ve un mensaje claro sin detalles técnicos', function () {
    config()->set('nexus_ai.gemini.keys', ['key-a']);
    $this->mock(NexusAssistant::class)->shouldReceive('ask')->once()
        ->andThrow(new AiUnavailableException('cuota agotada: detalle interno'));

    $this->actingAs(chatUser())
        ->postJson(route('ia.chat'), ['message' => 'hola?'])
        ->assertStatus(503)
        ->assertJsonPath('message', 'Nexus no pudo responder en este momento. Intenta de nuevo en unos segundos.')
        ->assertDontSee('detalle interno');
});

test('sin llaves configuradas el mensaje indica que falta configurar Nexus', function () {
    config()->set('nexus_ai.driver', 'gemini');
    config()->set('nexus_ai.gemini.keys', []);
    $this->mock(NexusAssistant::class)->shouldReceive('ask')->once()
        ->andThrow(new AiUnavailableException('sin llaves'));

    $this->actingAs(chatUser())
        ->postJson(route('ia.chat'), ['message' => 'hola?'])
        ->assertStatus(503)
        ->assertJsonPath('message', fn (string $m): bool => str_contains($m, 'llaves de Gemini'));
});

test('el chat tiene un límite de mensajes por minuto para proteger la cuota de las llaves', function () {
    $this->mock(NexusAssistant::class)->shouldReceive('ask')->andReturn(new AnswerResult('ok', true));
    $user = chatUser();

    for ($i = 0; $i < 30; $i++) {
        $this->actingAs($user)->postJson(route('ia.chat'), ['message' => 'pregunta '.$i])->assertOk();
    }

    $this->actingAs($user)->postJson(route('ia.chat'), ['message' => 'una más'])->assertStatus(429);
});

test('un reporte solo lo puede descargar un usuario de la empresa que lo generó', function () {
    Storage::fake('local');
    Storage::disk('local')->put('reports/1/demo.pdf', '%PDF-1.4 demo');
    $report = AiReport::query()->create([
        'company_id' => 1, 'title' => 'Leads por etapa', 'path' => 'reports/1/demo.pdf', 'size_bytes' => 13,
    ]);

    $this->get(route('ia.reportes.descargar', $report->id))->assertRedirect();

    $this->actingAs(chatUser(1))
        ->get(route('ia.reportes.descargar', $report->id))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf')
        ->assertDownload('leads-por-etapa.pdf');

    $this->actingAs(chatUser(otherCompany()))->get(route('ia.reportes.descargar', $report->id))->assertNotFound();
    $this->actingAs(chatUser(null))->get(route('ia.reportes.descargar', $report->id))->assertNotFound();
});

test('solo quien recibió la propuesta puede confirmarla, y se ejecuta una sola vez', function () {
    $owner = chatUser(1);
    $action = AiPendingAction::query()->create([
        'company_id' => 1, 'user_id' => $owner->id, 'type' => 'prenomina', 'title' => 'Generar pre-nómina',
        'payload' => ['start' => '2026-11-01', 'end' => '2026-11-15', 'frequency' => 'quincenal'],
        'status' => 'pending', 'expires_at' => now()->addMinutes(30),
    ]);

    // Otro usuario, aunque sea de la misma empresa, no puede confirmarla.
    $this->actingAs(chatUser(1))->postJson(route('ia.acciones.confirmar', $action->id))->assertNotFound();
    $this->actingAs(chatUser(otherCompany()))->postJson(route('ia.acciones.confirmar', $action->id))->assertNotFound();

    $this->mock(PendingActionService::class)->shouldReceive('confirm')->once()
        ->andReturn(['message' => 'La pre-nómina quedó generada.', 'report' => null]);

    $this->actingAs($owner)->postJson(route('ia.acciones.confirmar', $action->id))
        ->assertOk()
        ->assertJsonPath('message', 'La pre-nómina quedó generada.')
        ->assertJsonPath('artifacts', []);
});

test('si la acción ya fue atendida o expiró, el usuario recibe una explicación', function () {
    $owner = chatUser(1);
    $action = AiPendingAction::query()->create([
        'company_id' => 1, 'user_id' => $owner->id, 'type' => 'prenomina', 'title' => 'Generar',
        'payload' => [], 'status' => 'confirmed', 'expires_at' => now()->addMinutes(30),
    ]);

    $this->mock(PendingActionService::class)->shouldReceive('confirm')->once()
        ->andThrow(new InvalidToolCallException('Esta acción ya fue atendida.'));

    $this->actingAs($owner)->postJson(route('ia.acciones.confirmar', $action->id))
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Esta acción ya fue atendida.');
});

test('el usuario puede cancelar una acción propuesta', function () {
    $owner = chatUser(1);
    $action = AiPendingAction::query()->create([
        'company_id' => 1, 'user_id' => $owner->id, 'type' => 'prenomina', 'title' => 'Generar',
        'payload' => [], 'status' => 'pending', 'expires_at' => now()->addMinutes(30),
    ]);

    $this->actingAs($owner)->postJson(route('ia.acciones.cancelar', $action->id))->assertOk();

    expect($action->fresh()->status)->toBe('cancelled');
});

test('la pantalla completa del asistente la ven solo usuarios autenticados', function () {
    $this->get('/crm/asistente')->assertRedirect();

    $this->withoutVite()->actingAs(chatUser())
        ->get('/crm/asistente')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('crm/Asistente'));
});

test('una pregunta que cita un mensaje con imagen envía esa imagen al asistente', function () {
    Storage::fake('local');

    $received = [];
    $this->mock(NexusAssistant::class)->shouldReceive('ask')->twice()
        ->andReturnUsing(function (int $company, string $message, array $turns, array $attachments) use (&$received): AnswerResult {
            $received[] = $attachments;

            return new AnswerResult('Dice: hola.', true);
        });

    $user = chatUser();

    // Primer turno: el usuario sube una imagen.
    $first = $this->actingAs($user)->post(route('ia.chat'), [
        'message' => 'mira esta imagen',
        'files' => [UploadedFile::fake()->image('nota.png')],
    ], ['Accept' => 'application/json'])->assertOk()->json();

    $imageMessageId = $first['user_message']['id'];
    $conversationId = $first['conversation']['id'];

    // Segundo turno: cita ese mensaje sin volver a subir nada.
    $this->actingAs($user)->post(route('ia.chat'), [
        'message' => 'dime qué dice exactamente aquí',
        'conversation_id' => $conversationId,
        'reply_to' => $imageMessageId,
    ], ['Accept' => 'application/json'])->assertOk();

    expect($received[0])->toHaveCount(1)
        ->and($received[1])->toHaveCount(1)
        ->and($received[1][0]['mime'])->toBe('image/png')
        ->and($received[1][0]['data'])->toBe($received[0][0]['data']);
});

test('sin cita, la pregunta también recibe los documentos recientes de la conversación, con límite', function () {
    Storage::fake('local');

    $received = [];
    $this->mock(NexusAssistant::class)->shouldReceive('ask')->times(5)
        ->andReturnUsing(function (int $company, string $message, array $turns, array $attachments) use (&$received): AnswerResult {
            $received[] = $attachments;

            return new AnswerResult('ok', true);
        });

    $user = chatUser();
    $conversationId = null;

    // Cuatro documentos en la conversación; solo se envían los tres más recientes.
    for ($i = 1; $i <= 4; $i++) {
        $response = $this->actingAs($user)->post(route('ia.chat'), [
            'message' => "doc {$i}",
            'conversation_id' => $conversationId,
            'files' => [UploadedFile::fake()->create("doc{$i}.pdf", 10, 'application/pdf')],
        ], ['Accept' => 'application/json'])->assertOk()->json();
        $conversationId = $response['conversation']['id'];
    }

    $this->actingAs($user)->post(route('ia.chat'), [
        'message' => 'resume todo',
        'conversation_id' => $conversationId,
    ], ['Accept' => 'application/json'])->assertOk();

    // La última llamada no trae adjuntos propios: solo el contexto, limitado a 3.
    expect($received[4])->toHaveCount(3);
});

test('borrar todo el historial elimina solo las conversaciones del usuario y sus archivos', function () {
    Storage::fake('local');
    $this->mock(NexusAssistant::class)->shouldReceive('ask')->andReturn(new AnswerResult('ok', true));

    $user = chatUser();
    $other = chatUser();

    $mine = $this->actingAs($user)->post(route('ia.chat'), [
        'message' => 'con archivo',
        'files' => [UploadedFile::fake()->create('nota.pdf', 5, 'application/pdf')],
    ], ['Accept' => 'application/json'])->assertOk()->json();

    $theirs = $this->actingAs($other)->post(route('ia.chat'), [
        'message' => 'de otro usuario',
    ], ['Accept' => 'application/json'])->assertOk()->json();

    $path = DB::table('nexus_chat_attachments')->value('path');
    expect($path)->not->toBeNull();

    $this->actingAs($user)->deleteJson(route('ia.conversaciones.destroyAll'))
        ->assertOk()->assertJsonPath('deleted', 1);

    expect(DB::table('nexus_chats')->where('id', $mine['conversation']['id'])->exists())->toBeFalse()
        ->and(DB::table('nexus_chats')->where('id', $theirs['conversation']['id'])->exists())->toBeTrue();
    Storage::disk('local')->assertMissing($path);
});
