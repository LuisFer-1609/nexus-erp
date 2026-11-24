<?php

namespace App\Models\Nexus;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo de la tabla `work_schedules`.
 *
 * @property int $id
 * @property int $employee_id
 * @property int $weekday
 * @property string|null $check_in_time
 * @property string|null $check_out_time
 * @property bool $is_rest_day
 * @property-read Employee $employee
 */
class WorkSchedule extends Model
{
    /** Nombre de la tabla. */
    public const TABLE = 'work_schedules';

    public const ID = 'id';

    public const EMPLOYEE_ID = 'employee_id';

    public const WEEKDAY = 'weekday';

    public const CHECK_IN_TIME = 'check_in_time';

    public const CHECK_OUT_TIME = 'check_out_time';

    public const IS_REST_DAY = 'is_rest_day';

    public const VALID_FROM = 'valid_from';

    public const VALID_TO = 'valid_to';

    public const CREATED_AT = 'created_at';

    public const CREATED_USER_ID = 'created_user_id';

    public const UPDATED_AT = 'updated_at';

    public const UPDATED_USER_ID = 'updated_user_id';

    public const DELETED_AT = 'deleted_at';

    public const DELETED_USER_ID = 'deleted_user_id';

    protected $table = self::TABLE;

    protected $primaryKey = self::ID;

    protected $fillable = ['employee_id', 'weekday', 'check_in_time', 'check_out_time', 'is_rest_day', 'valid_from', 'valid_to'];

    protected function casts(): array
    {
        return ['weekday' => 'integer', 'is_rest_day' => 'boolean', 'valid_from' => 'date', 'valid_to' => 'date'];
    }

    public $timestamps = true;

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, self::EMPLOYEE_ID);
    }
}
