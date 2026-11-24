<?php

namespace App\Models\Nexus;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo de la tabla `attendances`.
 */
class Attendance extends Model
{
    /** Nombre de la tabla. */
    public const TABLE = 'attendances';

    public const ID = 'id';

    public const EMPLOYEE_ID = 'employee_id';

    public const ATTENDANCE_DATE = 'attendance_date';

    public const EXPECTED_CHECK_IN = 'expected_check_in';

    public const EXPECTED_CHECK_OUT = 'expected_check_out';

    public const CHECK_IN = 'check_in';

    public const CHECK_OUT = 'check_out';

    public const LATE_MINUTES = 'late_minutes';

    public const OVERTIME_HOURS = 'overtime_hours';

    public const STATUS = 'status';

    public const CREATED_AT = 'created_at';

    public const CREATED_USER_ID = 'created_user_id';

    public const UPDATED_AT = 'updated_at';

    public const UPDATED_USER_ID = 'updated_user_id';

    public const DELETED_AT = 'deleted_at';

    public const DELETED_USER_ID = 'deleted_user_id';

    protected $table = self::TABLE;

    protected $primaryKey = self::ID;

    protected $fillable = ['employee_id', 'attendance_date', 'expected_check_in', 'expected_check_out', 'check_in', 'check_out', 'late_minutes', 'overtime_hours', 'status', 'created_user_id', 'updated_user_id'];

    protected function casts(): array
    {
        return ['attendance_date' => 'date', 'late_minutes' => 'integer', 'overtime_hours' => 'decimal:2'];
    }

    public $timestamps = true;

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, self::EMPLOYEE_ID);
    }
}
