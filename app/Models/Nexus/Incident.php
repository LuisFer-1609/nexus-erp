<?php

namespace App\Models\Nexus;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo de la tabla `incidents`.
 */
class Incident extends Model
{
    /** Nombre de la tabla. */
    public const TABLE = 'incidents';

    public const ID = 'id';

    public const EMPLOYEE_ID = 'employee_id';

    public const INCIDENT_TYPE_ID = 'incident_type_id';

    public const START_DATE = 'start_date';

    public const END_DATE = 'end_date';

    public const NOTES = 'notes';

    public const STATUS = 'status';

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
