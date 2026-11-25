<?php

namespace App\Models\Nexus;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Modelo de la tabla `payroll_concept_types`.
 */
class PayrollConceptType extends Model
{
    use SoftDeletes;
    /** Nombre de la tabla. */
    public const TABLE = 'payroll_concept_types';

    public const ID = 'id';

    public const CODE = 'code';

    public const NAME = 'name';

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
