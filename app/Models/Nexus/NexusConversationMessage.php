<?php

namespace App\Models\Nexus;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo de la tabla `nexus_conversation_messages`.
 */
class NexusConversationMessage extends Model
{
    /** Nombre de la tabla. */
    public const TABLE = 'nexus_conversation_messages';

    public const ID = 'id';

    public const CONVERSATION_ID = 'conversation_id';

    public const ROLE = 'role';

    public const CONTENT = 'content';

    public const CREATED_AT = 'created_at';

    public const UPDATED_AT = 'updated_at';

    protected $table = self::TABLE;

    protected $primaryKey = self::ID;

    public $timestamps = true;
}
