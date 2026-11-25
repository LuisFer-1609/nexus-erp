<?php

namespace App\Models\Nexus;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Modelo de la tabla `areas`.
 */
class Area extends Model
{
    use SoftDeletes;
    /** Nombre de la tabla. */
    public const TABLE = 'areas';

    public const ID = 'id';

    public const COMPANY_ID = 'company_id';

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

    protected $fillable = [
        self::COMPANY_ID,
        self::CODE,
        self::NAME,
        self::CREATED_USER_ID,
        self::UPDATED_USER_ID,
        self::DELETED_USER_ID,
    ];

    public $timestamps = true;

    /** @return HasMany<Position, $this> */
    public function positions(): HasMany
    {
        return $this->hasMany(Position::class, Position::AREA_ID);
    }
}
