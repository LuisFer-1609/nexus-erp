<?php

namespace App\Models\Nexus;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo de la tabla `lead_stages`.
 */
class LeadStage extends Model
{
    /** Nombre de la tabla. */
    public const TABLE = 'lead_stages';

    public const ID = 'id';

    public const CODE = 'code';

    public const NAME = 'name';

    public const POSITION = 'position';

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
