<?php

namespace App\Models\Nexus;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo de la tabla `attendances`.
 */
class Attendance extends Model
{
    protected $connection = 'erp';

    /** Nombre de la tabla. */
    public const TABLE = 'attendances';

    public const ID = 'id';

    public const EMPLOYEE_ID = 'employee_id';

    public const ATTENDANCE_DATE = 'attendance_date';

    public const EXPECTED_CHECK_IN = 'expected_check_in';

    public const EXPECTED_CHECK_OUT = 'expected_check_out';

    public const CHECK_IN = 'check_in';

    public const CHECK_OUT = 'check_out';

    public const LATE_MINUTES = 'late_minutes';

    public const OVERTIME_HOURS = 'overtime_hours';

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
