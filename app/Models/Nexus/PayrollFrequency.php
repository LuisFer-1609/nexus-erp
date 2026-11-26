<?php

namespace App\Models\Nexus;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Modelo de la tabla `payroll_frequencies`.
 */
class PayrollFrequency extends Model
{
    use SoftDeletes;
    /** Nombre de la tabla. */
    public const TABLE = 'payroll_frequencies';

    public const ID = 'id';

    public const CODE = 'code';

    public const NAME = 'name';

    public const DAYS = 'days';

    public const CREATED_AT = 'created_at';

    public const CREATED_USER_ID = 'created_user_id';

    public const UPDATED_AT = 'updated_at';

    public const UPDATED_USER_ID = 'updated_user_id';

    public const DELETED_AT = 'deleted_at';

    public const DELETED_USER_ID = 'deleted_user_id';

    protected $table = self::TABLE;

    protected $primaryKey = self::ID;

    protected $fillable = [
        self::CODE,
        self::NAME,
        self::DAYS,
        self::CREATED_USER_ID,
        self::UPDATED_USER_ID,
        self::DELETED_USER_ID,
    ];

    protected function casts(): array
    {
        return [
            self::DAYS => 'integer',
        ];
    }

    public $timestamps = true;
}
