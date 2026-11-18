<?php

namespace App\Models\Nexus;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo de la tabla `companies`.
 */
class Company extends Model
{
    /** Nombre de la tabla. */
    public const TABLE = 'companies';

    public const ID = 'id';

    public const NAME = 'name';

    public const BUSINESS_NAME = 'business_name';

    public const RFC = 'rfc';

    public const TYPE_OF_BUSINESS = 'type_of_business';

    public const MAIL = 'mail';

    public const PHONE = 'phone';

    public const ADDRESS = 'address';

    public const IS_ACTIVE = 'is_active';

    protected $table = self::TABLE;

    protected $primaryKey = self::ID;

    public $timestamps = false;
}
