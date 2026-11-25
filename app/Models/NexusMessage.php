<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Mensaje de una conversación con Nexus (del usuario o del asistente).
 *
 * @property int $id
 * @property string $conversation_id
 * @property string $role
 * @property string $body
 * @property int|null $reply_to_id
 * @property array<string, mixed>|null $meta
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
class NexusMessage extends Model
{
    public const TABLE = 'nexus_chat_messages';

    public const ID = 'id';

    public const CONVERSATION_ID = 'conversation_id';

    public const ROLE = 'role';

    public const BODY = 'body';

    public const REPLY_TO_ID = 'reply_to_id';

    public const META = 'meta';

    public const ROLE_USER = 'user';

    public const ROLE_ASSISTANT = 'assistant';

    protected $table = self::TABLE;

    protected $guarded = [];

    /**
     * @return BelongsTo<NexusConversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(NexusConversation::class, self::CONVERSATION_ID);
    }

    /**
     * @return BelongsTo<NexusMessage, $this>
     */
    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(self::class, self::REPLY_TO_ID);
    }

    /**
     * @return HasMany<NexusAttachment, $this>
     */
    public function attachments(): HasMany
    {
        // Los ids son UUID ordenados: ordenar por id respeta el orden en que se subieron.
        return $this->hasMany(NexusAttachment::class, NexusAttachment::MESSAGE_ID)->orderBy(NexusAttachment::ID);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['meta' => 'array'];
    }
}
