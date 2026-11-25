<?php

namespace App\Models\Nexus;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Modelo de la tabla `users`.
 *
 * @property int $id
 * @property int $company_id
 * @property int $role_id
 * @property string|null $nickname
 * @property string $name
 * @property string|null $last_name
 * @property string|null $phone
 * @property bool $is_active
 * @property bool $is_admin
 * @property-read Role $role
 */
class User extends Model
{
    use SoftDeletes;
    /** Nombre de la tabla. */
    public const TABLE = 'users';

    public const ID = 'id';

    public const COMPANY_ID = 'company_id';

    public const ROLE_ID = 'role_id';

    public const NICKNAME = 'nickname';

    public const NAME = 'name';

    public const LAST_NAME = 'last_name';

    public const PHONE = 'phone';

    public const PASSWD = 'passwd';

    public const PICTURE = 'picture';

    public const IS_ACTIVE = 'is_active';

    public const IS_ADMIN = 'is_admin';

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
        self::ROLE_ID,
        self::NICKNAME,
        self::NAME,
        self::LAST_NAME,
        self::PHONE,
        self::PASSWD,
        self::PICTURE,
        self::IS_ACTIVE,
        self::IS_ADMIN,
        self::CREATED_USER_ID,
        self::UPDATED_USER_ID,
        self::DELETED_USER_ID,
    ];

    protected $hidden = [
        self::PASSWD,
    ];

    protected function casts(): array
    {
        return [
            self::IS_ACTIVE => 'boolean',
            self::IS_ADMIN => 'boolean',
        ];
    }

    public $timestamps = true;

    /** @return BelongsTo<Role, $this> */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, self::ROLE_ID);
    }
}
