<?php

namespace App\Models\Nexus;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo de la tabla `employees`.
 */
class Employee extends Model
{
    /** Nombre de la tabla. */
    public const TABLE = 'employees';

    public const ID = 'id';

    public const ID_USER = 'id_user';

    public const NATIONALITY_ID = 'nationality_id';

    public const PAYROLL_FREQUENCY_ID = 'payroll_frequency_id';

    public const AREA_ID = 'area_id';

    public const POSITION_ID = 'position_id';

    public const CODE = 'code';

    public const SEX = 'sex';

    public const CURP = 'curp';

    public const SSN = 'ssn';

    public const HIRE_DATE = 'hire_date';

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
