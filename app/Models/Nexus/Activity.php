<?php

namespace App\Models\Nexus;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo de la tabla `activities`.
 */
class Activity extends Model
{
    /** Nombre de la tabla. */
    public const TABLE = 'activities';

    public const ID = 'id';

    public const LEAD_ID = 'lead_id';

    public const CUSTOMER_ID = 'customer_id';

    public const USER_ID = 'user_id';

    public const ACTIVITY_TYPE = 'activity_type';

    public const DESCRIPTION = 'description';

    public const OCCURRED_AT = 'occurred_at';

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
