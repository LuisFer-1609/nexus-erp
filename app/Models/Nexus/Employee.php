<?php

namespace App\Models\Nexus;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Modelo de la tabla `employees`.
 *
 * @property int $id
 * @property int $id_user
 * @property int $nationality_id
 * @property int $payroll_frequency_id
 * @property int $area_id
 * @property int $position_id
 * @property string|null $code
 * @property string|null $sex
 * @property string|null $curp
 * @property string|null $ssn
 * @property \Illuminate\Support\Carbon|null $hire_date
 * @property string $status
 * @property-read User $user
 * @property-read Area $area
 * @property-read Position $position
 * @property-read Nationality $nationality
 * @property-read PayrollFrequency $payrollFrequency
 * @property-read Collection<int, WorkSchedule> $workSchedules
 * @property-read Collection<int, Attendance> $attendances
 * @property-read Collection<int, Incident> $incidents
 */
class Employee extends Model
{
    use SoftDeletes;
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
        'created_user_id', 'updated_user_id', 'deleted_user_id',
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

    /** @return BelongsTo<Area, $this> */
    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class, self::AREA_ID);
    }

    /** @return BelongsTo<Position, $this> */
    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class, self::POSITION_ID);
    }

    /** @return BelongsTo<Nationality, $this> */
    public function nationality(): BelongsTo
    {
        return $this->belongsTo(Nationality::class, self::NATIONALITY_ID);
    }

    /** @return BelongsTo<PayrollFrequency, $this> */
    public function payrollFrequency(): BelongsTo
    {
        return $this->belongsTo(PayrollFrequency::class, self::PAYROLL_FREQUENCY_ID);
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

    /** @return HasMany<Incident, $this> */
    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class, Incident::EMPLOYEE_ID);
    }
}
