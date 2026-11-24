<?php

namespace App\Models\Nexus;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modelo de la tabla `employees`.
 *
 * @property int $id
 * @property int $id_user
 * @property string $code
 * @property string $status
 * @property-read User $user
 * @property-read Collection<int, WorkSchedule> $workSchedules
 * @property-read Collection<int, Attendance> $attendances
 */
class Employee extends Model
{
    /** Nombre de la tabla. */
    public const TABLE = 'employees';

    public const ID = 'id';

    public const ID_USER = 'id_user';

    public const NATIONALITY_ID = 'nationality_id';

    public const PAYROLL_FREQUENCY_ID = 'payroll_frequency_id';

    public const AREA_ID = 'area_id';

    public const POSITION_ID = 'position_id';

    public const CODE = 'code';

    public const SEX = 'sex';

    public const CURP = 'curp';

    public const SSN = 'ssn';

    public const HIRE_DATE = 'hire_date';

    public const STATUS = 'status';

    public const CREATED_AT = 'created_at';

    public const CREATED_USER_ID = 'created_user_id';

    public const UPDATED_AT = 'updated_at';

    public const UPDATED_USER_ID = 'updated_user_id';

    public const DELETED_AT = 'deleted_at';

    public const DELETED_USER_ID = 'deleted_user_id';

    protected $table = self::TABLE;

    protected $primaryKey = self::ID;

    protected $fillable = [
        'id_user', 'nationality_id', 'payroll_frequency_id', 'area_id', 'position_id',
        'code', 'sex', 'curp', 'ssn', 'hire_date', 'status',
    ];

    protected function casts(): array
    {
        return ['hire_date' => 'date'];
    }

    public $timestamps = true;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, self::ID_USER);
    }

    /** @return HasMany<WorkSchedule, $this> */
    public function workSchedules(): HasMany
    {
        return $this->hasMany(WorkSchedule::class, WorkSchedule::EMPLOYEE_ID);
    }

    /** @return HasMany<Attendance, $this> */
    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class, Attendance::EMPLOYEE_ID);
    }
}
