<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Conversación de un usuario con Nexus.
 *
 * @property string $id
 * @property int $company_id
 * @property int $user_id
 * @property string $title
 * @property CarbonInterface|null $last_message_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
class NexusConversation extends Model
{
    use HasUuids;

    public const TABLE = 'nexus_chats';

    public const ID = 'id';

    public const COMPANY_ID = 'company_id';

    public const USER_ID = 'user_id';

    public const TITLE = 'title';

    public const LAST_MESSAGE_AT = 'last_message_at';

    protected $table = self::TABLE;

    protected $guarded = [];

    /**
     * @return HasMany<NexusMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(NexusMessage::class, NexusMessage::CONVERSATION_ID);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['last_message_at' => 'datetime'];
    }
}
