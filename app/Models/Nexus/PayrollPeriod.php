<?php

namespace App\Models\Nexus;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo de la tabla `payroll_periods`.
 */
class PayrollPeriod extends Model
{
    /** Nombre de la tabla. */
    public const TABLE = 'payroll_periods';

    public const ID = 'id';

    public const COMPANY_ID = 'company_id';

    public const PAYROLL_FREQUENCY_ID = 'payroll_frequency_id';

    public const START_DATE = 'start_date';

    public const END_DATE = 'end_date';

    public const PAYMENT_DATE = 'payment_date';

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
