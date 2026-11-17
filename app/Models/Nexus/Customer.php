<?php

namespace App\Models\Nexus;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo de la tabla `customers`.
 */
class Customer extends Model
{
    protected $connection = 'erp';

    /** Nombre de la tabla. */
    public const TABLE = 'customers';

    public const ID = 'id';

    public const COMPANY_ID = 'company_id';

    public const LEAD_ID = 'lead_id';

    public const LEAD_SOURCE_ID = 'lead_source_id';

    public const BUSINESS_TYPE_ID = 'business_type_id';

    public const RESPONSIBLE_USER_ID = 'responsible_user_id';

    public const CODE = 'code';

    public const LEGAL_NAME = 'legal_name';

    public const BUSINESS_NAME = 'business_name';

    public const RFC = 'rfc';

    public const EMAIL = 'email';

    public const PHONE = 'phone';

    public const STATUS = 'status';

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
