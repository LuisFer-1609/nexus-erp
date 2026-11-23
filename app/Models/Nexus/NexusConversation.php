<?php

namespace App\Models\Nexus;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo de la tabla `nexus_conversations`.
 */
class NexusConversation extends Model
{
    /** Nombre de la tabla. */
    public const TABLE = 'nexus_conversations';

    public const ID = 'id';

    public const USER_ID = 'user_id';

    public const TITLE = 'title';

    public const LAST_MESSAGE_AT = 'last_message_at';

    public const CREATED_AT = 'created_at';

    public const UPDATED_AT = 'updated_at';

    public const CREATED_USER_ID = 'created_user_id';

    public const UPDATED_USER_ID = 'updated_user_id';

    public const DELETED_AT = 'deleted_at';

    public const DELETED_USER_ID = 'deleted_user_id';

    protected $table = self::TABLE;

    protected $primaryKey = self::ID;

    public $timestamps = true;
}
