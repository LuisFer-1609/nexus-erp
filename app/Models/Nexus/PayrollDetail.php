<?php

namespace App\Models\Nexus;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo de la tabla `payroll_details`.
 */
class PayrollDetail extends Model
{
    /** Nombre de la tabla. */
    public const TABLE = 'payroll_details';

    public const ID = 'id';

    public const PAYROLL_ID = 'payroll_id';

    public const PAYROLL_CONCEPT_ID = 'payroll_concept_id';

    public const AMOUNT = 'amount';

    public const IS_MANUAL_ADJUSTMENT = 'is_manual_adjustment';

    public const NOTES = 'notes';

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
