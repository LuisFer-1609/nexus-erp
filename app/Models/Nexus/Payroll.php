<?php

namespace App\Models\Nexus;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo de la tabla `payrolls`.
 */
class Payroll extends Model
{
    protected $connection = 'erp';

    /** Nombre de la tabla. */
    public const TABLE = 'payrolls';

    public const ID = 'id';

    public const PAYROLL_PERIOD_ID = 'payroll_period_id';

    public const EMPLOYEE_ID = 'employee_id';

    public const DAYS_WORKED = 'days_worked';

    public const LATE_COUNT = 'late_count';

    public const ABSENCE_COUNT = 'absence_count';

    public const OVERTIME_HOURS = 'overtime_hours';

    public const TOTAL_PERCEPTIONS = 'total_perceptions';

    public const TOTAL_DEDUCTIONS = 'total_deductions';

    public const NET_AMOUNT = 'net_amount';

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
