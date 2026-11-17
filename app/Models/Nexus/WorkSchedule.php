<?php

namespace App\Models\Nexus;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo de la tabla `work_schedules`.
 */
class WorkSchedule extends Model
{
    protected $connection = 'erp';

    /** Nombre de la tabla. */
    public const TABLE = 'work_schedules';

    public const ID = 'id';

    public const EMPLOYEE_ID = 'employee_id';

    public const WEEKDAY = 'weekday';

    public const CHECK_IN_TIME = 'check_in_time';

    public const CHECK_OUT_TIME = 'check_out_time';

    public const IS_REST_DAY = 'is_rest_day';

    public const VALID_FROM = 'valid_from';

    public const VALID_TO = 'valid_to';

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
