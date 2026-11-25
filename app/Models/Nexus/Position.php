<?php

namespace App\Models\Nexus;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Modelo de la tabla `positions`.
 */
class Position extends Model
{
    use SoftDeletes;
    /** Nombre de la tabla. */
    public const TABLE = 'positions';

    public const ID = 'id';

    public const COMPANY_ID = 'company_id';

    public const AREA_ID = 'area_id';

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
        self::AREA_ID,
        self::CODE,
        self::NAME,
        self::CREATED_USER_ID,
        self::UPDATED_USER_ID,
        self::DELETED_USER_ID,
    ];

    public $timestamps = true;

    /** @return BelongsTo<Area, $this> */
    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class, self::AREA_ID);
    }
}
