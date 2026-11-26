<?php

namespace App\Models\Nexus;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Modelo de la tabla `nationalities`.
 */
class Nationality extends Model
{
    use SoftDeletes;
    /** Nombre de la tabla. */
    public const TABLE = 'nationalities';

    public const ID = 'id';

    public const NATIONALITY = 'nationality';

    public const C_PAIS = 'c_pais';

    public const COUNTRY = 'country';

    public const CREATED_AT = 'created_at';

    public const CREATED_USER_ID = 'created_user_id';

    public const UPDATED_AT = 'updated_at';

    public const UPDATED_USER_ID = 'updated_user_id';

    public const DELETED_AT = 'deleted_at';

    public const DELETED_USER_ID = 'deleted_user_id';

    protected $table = self::TABLE;

    protected $primaryKey = self::ID;

    protected $fillable = [
        self::NATIONALITY,
        self::C_PAIS,
        self::COUNTRY,
        self::CREATED_USER_ID,
        self::UPDATED_USER_ID,
        self::DELETED_USER_ID,
    ];

    public $timestamps = true;
}
