<?php

namespace App\Models\Nexus;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo de la tabla `users`.
 */
class User extends Model
{
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

    public $timestamps = true;
}
