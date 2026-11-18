<?php

namespace App\Models\Nexus;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo de la tabla `tasks`.
 */
class Task extends Model
{
    /** Nombre de la tabla. */
    public const TABLE = 'tasks';

    public const ID = 'id';

    public const LEAD_ID = 'lead_id';

    public const CUSTOMER_ID = 'customer_id';

    public const ASSIGNED_USER_ID = 'assigned_user_id';

    public const TITLE = 'title';

    public const DESCRIPTION = 'description';

    public const DUE_AT = 'due_at';

    public const STATUS = 'status';

    public const COMPLETED_AT = 'completed_at';

    public const CREATED_AT = 'created_at';

    public const CREATED_USER_ID = 'created_user_id';

    public const UPDATED_AT = 'updated_at';

    public const UPDATED_USER_ID = 'updated_user_id';

    public const DELETED_AT = 'deleted_at';

    public const DELETED_USER_ID = 'deleted_user_id';

    protected $table = self::TABLE;

    protected $primaryKey = self::ID;

    public $timestamps = true;
}
