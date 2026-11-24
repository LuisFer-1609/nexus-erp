<?php

namespace App\Http\Controllers\ia;

use App\Http\Controllers\Controller;
use App\Models\NexusMessage;
use App\Models\User;
use App\Services\Ai\Conversations\ConversationService;
use App\Services\Ai\Exceptions\AiUnavailableException;
use App\Services\Ai\GeminiKeyPool;
use App\Services\Ai\NexusAssistant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Chat con Nexus. La empresa se toma SIEMPRE del usuario autenticado: el
 * navegador no puede indicar de qué empresa quiere datos.
 *
 * Cada pregunta y su respuesta quedan guardadas en una conversación del usuario.
 */
class IaChatController extends Controller
{
    /** Turnos previos que se envían al modelo como contexto de la conversación. */
    private const MAX_HISTORY_TURNS = 6;

    /** Tamaño máximo de los adjuntos de una pregunta (límite de envío en línea de la API). */
    private const MAX_ATTACHMENT_BYTES = 15 * 1024 * 1024;

    /** @var list<string> */
    /** Documentos de la conversación (citados o recientes) que se envían además de los de esta pregunta. */
    private const MAX_CONTEXT_ATTACHMENTS = 3;

    private const ATTACHMENT_MIMES = [
        'application/pdf', 'image/png', 'image/jpeg', 'image/webp',
        'text/plain', 'text/csv', 'text/markdown',
    ];

    public function __invoke(Request $request, NexusAssistant $assistant, GeminiKeyPool $pool, ConversationService $conversations): JsonResponse
    {
        $data = $request->validate([
            'message' => ['required_without:files', 'nullable', 'string', 'max:1000'],
            'conversation_id' => ['nullable', 'uuid'],
            'reply_to' => ['nullable', 'integer'],
            'files' => ['sometimes', 'array', 'max:3'],
            'files.*' => ['file', 'max:10240', 'mimetypes:'.implode(',', self::ATTACHMENT_MIMES)],
        ]);

        $user = $request->user();
        $companyId = $user instanceof User ? $user->company_id : null;

        if (! $user instanceof User || $companyId === null) {
            return response()->json([
                'message' => 'Tu cuenta aún no tiene una empresa asignada, así que Nexus no sabe qué datos consultar. Pide al administrador que la asigne.',
            ], 422);
        }

        $files = $this->uploadedFiles($request);
        if (array_sum(array_map(static fn (UploadedFile $f): int => (int) $f->getSize(), $files)) > self::MAX_ATTACHMENT_BYTES) {
            return response()->json(['message' => 'Los documentos adjuntos pesan demasiado. El máximo es 15 MB en total.'], 422);
        }

        $text = trim((string) ($data['message'] ?? ''));

        $conversation = null;
        if (! empty($data['conversation_id'])) {
            $conversation = $conversations->find($user, (string) $data['conversation_id']);
            abort_if($conversation === null, 404);
        }

        // El contexto se arma ANTES de guardar la pregunta actual.
        $history = $conversation === null ? [] : $conversations->history($conversation, self::MAX_HISTORY_TURNS);
        $reply = $conversation !== null && isset($data['reply_to'])
            ? $conversations->findMessage($conversation, (int) $data['reply_to'])
            : null;

        // Los documentos de la conversación (citados o recientes) también llegan al modelo,
        // dentro del mismo límite de tamaño que los adjuntos de esta pregunta.
        $currentBytes = array_sum(array_map(static fn (UploadedFile $f): int => (int) $f->getSize(), $files));
        $contextFiles = $conversation === null
            ? []
            : $conversations->attachmentsForContext(
                $conversation,
                $reply,
                self::MAX_CONTEXT_ATTACHMENTS,
                self::MAX_ATTACHMENT_BYTES - $currentBytes,
            );

        $isNew = $conversation === null;
        $conversation ??= $conversations->create($user, $text !== '' ? $text : 'Documento adjunto');
        $userMessage = $conversations->addUserMessage($conversation, $text, $files, $reply);

        $completed = false;

        try {
            $result = $assistant->ask(
                (int) $companyId,
                $this->questionFor($text, $reply),
                $history,
                [...$contextFiles, ...$this->attachmentsForModel($files)],
                $user->id,
                $user->name,
            );

            $assistantMessage = $conversations->addAssistantMessage($conversation, $result->answer, $result->artifacts, $result->actions);
            $completed = true;
        } catch (AiUnavailableException $e) {
            // El detalle técnico va al log; al usuario solo un mensaje claro.
            Log::warning('Nexus IA no disponible: '.$e->getMessage());

            return response()->json([
                'message' => $pool->count() === 0 && config('nexus_ai.driver') === 'gemini'
                    ? 'Nexus aún no está configurado: falta agregar las llaves de Gemini en el servidor.'
                    : 'Nexus no pudo responder en este momento. Intenta de nuevo en unos segundos.',
            ], 503);
        } finally {
            // Una pregunta sin respuesta no se queda en el historial.
            if (! $completed) {
                try {
                    $conversations->discard($conversation, $userMessage, $isNew);
                } catch (Throwable $e) {
                    report($e);
                }
            }
        }

        return response()->json([
            'answer' => $result->answer,
            'grounded' => $result->grounded,
            'sources' => count($result->sources),
            'artifacts' => $result->artifacts,
            'actions' => $result->actions,
            'conversation' => [
                'id' => $conversation->id,
                'title' => $conversation->title,
                'last_message_at' => $conversation->last_message_at?->toIso8601String(),
            ],
            'user_message' => $conversations->payload($userMessage),
            'assistant_message' => $conversations->payload($assistantMessage, array_fill_keys(
                array_column($result->actions, 'id'),
                'pending',
            )),
        ]);
    }

    /**
     * Cuando el usuario responde a un mensaje anterior, el modelo recibe esa cita
     * junto con la pregunta para entender a qué se refiere.
     */
    private function questionFor(string $text, ?NexusMessage $reply): string
    {
        if ($reply === null || trim($reply->body) === '') {
            return $text;
        }

        $author = $reply->role === NexusMessage::ROLE_ASSISTANT ? 'Nexus' : 'el usuario';
        $quoted = ConversationService::excerpt($reply->body, 600);

        return "[El usuario responde a un mensaje anterior de {$author}: «{$quoted}»]\n".$text;
    }

    /**
     * @return list<UploadedFile>
     */
    private function uploadedFiles(Request $request): array
    {
        return array_values(array_filter(
            (array) $request->file('files', []),
            static fn (mixed $f): bool => $f instanceof UploadedFile,
        ));
    }

    /**
     * Adjuntos en base64 para enviarlos al modelo.
     *
     * @param  list<UploadedFile>  $files
     * @return list<array{mime: string, data: string}>
     */
    private function attachmentsForModel(array $files): array
    {
        return array_map(static function (UploadedFile $file): array {
            $mime = (string) ($file->getMimeType() ?? 'application/octet-stream');

            return [
                // El modelo lee el Markdown como texto plano.
                'mime' => $mime === 'text/markdown' ? 'text/plain' : $mime,
                'data' => base64_encode((string) file_get_contents($file->getRealPath())),
            ];
        }, $files);
    }
}
