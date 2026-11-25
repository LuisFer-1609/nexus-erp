<?php

namespace App\Services\Ai\Conversations;

use App\Models\AiPendingAction;
use App\Models\NexusAttachment;
use App\Models\NexusConversation;
use App\Models\NexusMessage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Historial del chat de Nexus. Toda consulta se limita al usuario dueño y a su
 * empresa: una conversación nunca es visible para otra persona.
 */
class ConversationService
{
    private const DISK = 'local';

    /** Máximo de conversaciones que devuelve el listado. */
    private const LIST_LIMIT = 100;

    /** Cuántos adjuntos recientes se revisan como candidatos para dar contexto al modelo. */
    private const CONTEXT_SCAN_LIMIT = 10;

    public function find(User $user, string $id): ?NexusConversation
    {
        if (! Str::isUuid($id) || $user->company_id === null) {
            return null;
        }

        return NexusConversation::query()
            ->whereKey($id)
            ->where(NexusConversation::USER_ID, $user->id)
            ->where(NexusConversation::COMPANY_ID, $user->company_id)
            ->first();
    }

    public function findMessage(NexusConversation $conversation, int $messageId): ?NexusMessage
    {
        return $conversation->messages()->whereKey($messageId)->first();
    }

    /**
     * @return list<array{id: string, title: string, last_message_at: string|null, preview: string}>
     */
    public function listFor(User $user, ?string $search = null): array
    {
        $query = NexusConversation::query()
            ->where(NexusConversation::USER_ID, $user->id)
            ->where(NexusConversation::COMPANY_ID, $user->company_id)
            ->orderByDesc(NexusConversation::LAST_MESSAGE_AT)
            ->limit(self::LIST_LIMIT);

        $term = $search === null ? '' : trim($search);
        if ($term !== '') {
            $like = '%'.addcslashes($term, '%_\\').'%';
            $query->where(fn ($q) => $q
                ->where(NexusConversation::TITLE, 'like', $like)
                ->orWhereHas('messages', fn ($m) => $m->where(NexusMessage::BODY, 'like', $like)));
        }

        $conversations = $query->get();
        if ($conversations->isEmpty()) {
            return [];
        }

        $latest = NexusMessage::query()
            ->whereIn(NexusMessage::ID, NexusMessage::query()
                ->selectRaw('MAX(id)')
                ->whereIn(NexusMessage::CONVERSATION_ID, $conversations->pluck('id'))
                ->groupBy(NexusMessage::CONVERSATION_ID))
            ->get()
            ->keyBy(NexusMessage::CONVERSATION_ID);

        return $conversations->map(static fn (NexusConversation $c): array => [
            'id' => $c->id,
            'title' => $c->title,
            'last_message_at' => $c->last_message_at?->toIso8601String(),
            'preview' => self::excerpt($latest->get($c->id)?->body ?? '', 90),
        ])->values()->all();
    }

    /**
     * Turnos previos para dar contexto al modelo (sin contar el mensaje en curso).
     *
     * @return list<array{role: string, text: string}>
     */
    public function history(NexusConversation $conversation, int $turns): array
    {
        $messages = $conversation->messages()
            ->with('attachments')
            ->orderByDesc(NexusMessage::ID)
            ->limit($turns)
            ->get()
            ->reverse();

        $history = [];
        foreach ($messages as $message) {
            $text = trim($message->body);
            if ($text === '' && $message->attachments->isNotEmpty()) {
                $text = '[adjuntó: '.$message->attachments->pluck('name')->implode(', ').']';
            }
            if ($text !== '') {
                $history[] = ['role' => $message->role, 'text' => $text];
            }
        }

        return $history;
    }

    public function create(User $user, string $title): NexusConversation
    {
        return NexusConversation::query()->create([
            NexusConversation::COMPANY_ID => $user->company_id,
            NexusConversation::USER_ID => $user->id,
            NexusConversation::TITLE => Str::limit(trim($title) !== '' ? trim($title) : 'Conversación nueva', 60, '…'),
            NexusConversation::LAST_MESSAGE_AT => now(),
        ]);
    }

    /**
     * @param  list<UploadedFile>  $files
     */
    public function addUserMessage(NexusConversation $conversation, string $text, array $files, ?NexusMessage $reply): NexusMessage
    {
        return DB::transaction(function () use ($conversation, $text, $files, $reply): NexusMessage {
            $message = $conversation->messages()->create([
                NexusMessage::ROLE => NexusMessage::ROLE_USER,
                NexusMessage::BODY => $text,
                NexusMessage::REPLY_TO_ID => $reply?->id,
            ]);

            foreach ($files as $file) {
                $id = (string) Str::orderedUuid();
                $extension = preg_replace('/[^a-z0-9]/i', '', (string) $file->guessExtension()) ?: 'bin';
                $path = $file->storeAs(
                    'nexus-attachments/'.$conversation->company_id.'/'.$conversation->id,
                    $id.'.'.$extension,
                    self::DISK,
                );

                NexusAttachment::query()->create([
                    NexusAttachment::ID => $id,
                    NexusAttachment::MESSAGE_ID => $message->id,
                    NexusAttachment::NAME => self::safeName($file->getClientOriginalName()),
                    NexusAttachment::MIME => (string) ($file->getMimeType() ?? 'application/octet-stream'),
                    NexusAttachment::SIZE_BYTES => (int) $file->getSize(),
                    NexusAttachment::PATH => (string) $path,
                ]);
            }

            $this->touch($conversation);

            return $message;
        });
    }

    /**
     * Documentos que el modelo debe ver junto con la pregunta actual: primero los del
     * mensaje citado y después los más recientes de la conversación. Se llama ANTES de
     * guardar la pregunta, así que sus propios adjuntos no se cuentan aquí.
     *
     * @return list<array{mime: string, data: string}>
     */
    public function attachmentsForContext(NexusConversation $conversation, ?NexusMessage $reply, int $maxFiles, int $maxBytes): array
    {
        if ($maxFiles < 1 || $maxBytes < 1) {
            return [];
        }

        $recent = NexusAttachment::query()
            ->whereIn(NexusAttachment::MESSAGE_ID, $conversation->messages()->select(NexusMessage::ID))
            ->orderByDesc(NexusAttachment::ID)
            ->limit(self::CONTEXT_SCAN_LIMIT)
            ->get();

        $quoted = $reply === null ? collect() : $reply->attachments;
        $candidates = $quoted->concat($recent);

        $context = [];
        $seen = [];
        $bytes = 0;

        foreach ($candidates as $attachment) {
            if (count($context) >= $maxFiles) {
                break;
            }

            if (isset($seen[$attachment->id])) {
                continue;
            }
            $seen[$attachment->id] = true;

            $size = (int) $attachment->size_bytes;
            if ($bytes + $size > $maxBytes || ! Storage::disk(self::DISK)->exists($attachment->path)) {
                continue;
            }

            $bytes += $size;
            $mime = (string) $attachment->mime;

            $context[] = [
                // El modelo lee el Markdown como texto plano.
                'mime' => $mime === 'text/markdown' ? 'text/plain' : $mime,
                'data' => base64_encode((string) Storage::disk(self::DISK)->get($attachment->path)),
            ];
        }

        return $context;
    }

    /**
     * @param  list<array<string, mixed>>  $artifacts
     * @param  list<array<string, mixed>>  $actions
     */
    public function addAssistantMessage(NexusConversation $conversation, string $text, array $artifacts = [], array $actions = []): NexusMessage
    {
        $meta = array_filter(['artifacts' => $artifacts, 'actions' => $actions]);

        $message = $conversation->messages()->create([
            NexusMessage::ROLE => NexusMessage::ROLE_ASSISTANT,
            NexusMessage::BODY => $text,
            NexusMessage::META => $meta === [] ? null : $meta,
        ]);

        $this->touch($conversation);

        return $message;
    }

    /**
     * Borra un mensaje y sus archivos. Si la conversación se queda vacía, también se elimina.
     *
     * @return bool true cuando la conversación completa se eliminó.
     */
    public function deleteMessage(NexusConversation $conversation, NexusMessage $message): bool
    {
        $this->deleteFiles($message->attachments()->pluck(NexusAttachment::PATH)->all());
        $message->delete();

        if (! $conversation->messages()->exists()) {
            $conversation->delete();

            return true;
        }

        $last = $conversation->messages()->orderByDesc(NexusMessage::ID)->first();
        $conversation->forceFill([NexusConversation::LAST_MESSAGE_AT => $last?->created_at])->save();

        return false;
    }

    public function deleteConversation(NexusConversation $conversation): void
    {
        $paths = NexusAttachment::query()
            ->whereIn(NexusAttachment::MESSAGE_ID, $conversation->messages()->select(NexusMessage::ID))
            ->pluck(NexusAttachment::PATH)
            ->all();

        $this->deleteFiles($paths);
        $conversation->delete();
    }

    /**
     * Borra todo el historial de conversaciones del usuario en su empresa, con sus archivos.
     *
     * @return int conversaciones eliminadas
     */
    public function deleteAllFor(User $user): int
    {
        $owned = NexusConversation::query()
            ->where(NexusConversation::USER_ID, $user->id)
            ->where(NexusConversation::COMPANY_ID, $user->company_id);

        $ids = (clone $owned)->pluck(NexusConversation::ID)->all();
        if ($ids === []) {
            return 0;
        }

        $paths = NexusAttachment::query()
            ->whereIn(NexusAttachment::MESSAGE_ID, NexusMessage::query()
                ->whereIn(NexusMessage::CONVERSATION_ID, $ids)
                ->select(NexusMessage::ID))
            ->pluck(NexusAttachment::PATH)
            ->all();

        $this->deleteFiles($paths);

        return $owned->delete();
    }

    /**
     * Deshace una pregunta que no llegó a responderse (por ejemplo, si la IA no estuvo disponible).
     */
    public function discard(NexusConversation $conversation, NexusMessage $message, bool $conversationIsNew): void
    {
        if ($conversationIsNew) {
            $this->deleteConversation($conversation);

            return;
        }

        $this->deleteMessage($conversation, $message);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function messagesPayload(NexusConversation $conversation): array
    {
        /** @var Collection<int, NexusMessage> $messages */
        $messages = $conversation->messages()
            ->with(['attachments', 'replyTo'])
            ->orderBy(NexusMessage::ID)
            ->get();

        $actionIds = $messages
            ->flatMap(static fn (NexusMessage $m): array => array_column((array) ($m->meta['actions'] ?? []), 'id'))
            ->all();

        $statuses = $actionIds === [] ? [] : AiPendingAction::query()
            ->whereIn(AiPendingAction::ID, $actionIds)
            ->get()
            ->mapWithKeys(static fn (AiPendingAction $a): array => [$a->id => self::actionStatus($a)])
            ->all();

        return $messages->map(fn (NexusMessage $m): array => $this->payload($m, $statuses))->values()->all();
    }

    /**
     * @param  array<string, string>  $actionStatuses
     * @return array<string, mixed>
     */
    public function payload(NexusMessage $message, array $actionStatuses = []): array
    {
        $message->loadMissing(['attachments', 'replyTo.attachments']);

        $reply = $message->replyTo;

        return [
            'id' => $message->id,
            'role' => $message->role,
            'text' => $message->body,
            'created_at' => $message->created_at?->toIso8601String(),
            'reply_to' => $reply === null ? null : [
                'id' => $reply->id,
                'role' => $reply->role,
                'text' => self::excerpt($reply->body !== '' ? $reply->body : $this->attachmentLabel($reply), 160),
            ],
            'attachments' => $message->attachments->map(static fn (NexusAttachment $a): array => [
                'id' => $a->id,
                'name' => $a->name,
                'mime' => $a->mime,
                'size' => $a->size_bytes,
                'url' => route('ia.adjuntos.ver', $a->id, false),
                'thumb_url' => $a->mime === 'application/pdf' ? route('ia.adjuntos.miniatura', $a->id, false) : null,
            ])->values()->all(),
            'artifacts' => array_values((array) ($message->meta['artifacts'] ?? [])),
            'actions' => array_map(
                static fn (array $action): array => [...$action, 'status' => $actionStatuses[$action['id']] ?? 'cancelled'],
                array_values((array) ($message->meta['actions'] ?? [])),
            ),
        ];
    }

    public static function actionStatus(AiPendingAction $action): string
    {
        return match (true) {
            $action->status === AiPendingAction::STATUS_CONFIRMED => 'done',
            $action->status === AiPendingAction::STATUS_PENDING && $action->expires_at->isFuture() => 'pending',
            default => 'cancelled',
        };
    }

    private function attachmentLabel(NexusMessage $message): string
    {
        $names = $message->attachments->pluck('name');

        return $names->isEmpty() ? '' : '📎 '.$names->implode(', ');
    }

    private function touch(NexusConversation $conversation): void
    {
        $conversation->forceFill([NexusConversation::LAST_MESSAGE_AT => now()])->save();
    }

    /**
     * @param  list<mixed>  $paths
     */
    private function deleteFiles(array $paths): void
    {
        $paths = array_values(array_filter($paths, 'is_string'));

        if ($paths !== []) {
            Storage::disk(self::DISK)->delete($paths);
        }
    }

    public static function excerpt(string $text, int $limit): string
    {
        $flat = trim((string) preg_replace('/\s+/u', ' ', strip_tags($text)));

        return Str::limit($flat, $limit, '…');
    }

    private static function safeName(string $name): string
    {
        $clean = trim((string) preg_replace('/[\x00-\x1F\x7F\/\\\\]/u', '', $name));

        return mb_substr($clean !== '' ? $clean : 'archivo', 0, 200);
    }
}
