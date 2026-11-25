<?php

namespace App\Models\Nexus;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Modelo de la tabla `payroll_concepts`.
 */
class PayrollConcept extends Model
{
    use SoftDeletes;
    /** Nombre de la tabla. */
    public const TABLE = 'payroll_concepts';

    public const ID = 'id';

    public const COMPANY_ID = 'company_id';

    public const PAYROLL_CONCEPT_TYPE_ID = 'payroll_concept_type_id';

    public const CODE = 'code';

    public const NAME = 'name';

    public const IS_ACTIVE = 'is_active';

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
        self::PAYROLL_CONCEPT_TYPE_ID,
        self::CODE,
        self::NAME,
        self::IS_ACTIVE,
        self::CREATED_USER_ID,
        self::UPDATED_USER_ID,
        self::DELETED_USER_ID,
    ];

    protected function casts(): array
    {
        return [
            self::IS_ACTIVE => 'boolean',
        ];
    }

    public $timestamps = true;

    /** @return BelongsTo<PayrollConceptType, $this> */
    public function conceptType(): BelongsTo
    {
        return $this->belongsTo(PayrollConceptType::class, self::PAYROLL_CONCEPT_TYPE_ID);
    }
}
