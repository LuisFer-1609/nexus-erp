<?php

namespace App\Models\Nexus;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo de la tabla `payroll_concepts`.
 */
class PayrollConcept extends Model
{
    protected $connection = 'erp';

    /** Nombre de la tabla. */
    public const TABLE = 'payroll_concepts';

    public const ID = 'id';

    public const COMPANY_ID = 'company_id';

    public const PAYROLL_CONCEPT_TYPE_ID = 'payroll_concept_type_id';

    public const CODE = 'code';

    public const NAME = 'name';

    public const IS_ACTIVE = 'is_active';

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
