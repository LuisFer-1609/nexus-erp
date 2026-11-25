<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Acción propuesta por Nexus que espera confirmación del usuario.
 *
 * @property string $id
 * @property int $company_id
 * @property int|null $user_id
 * @property string $type
 * @property string $title
 * @property array<string, mixed> $payload
 * @property string $status
 * @property array<string, mixed>|null $result
 * @property CarbonInterface $expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class AiPendingAction extends Model
{
    use HasUuids;

    /** Nombre de la tabla. */
    public const TABLE = 'ai_pending_actions';

    public const ID = 'id';

    public const COMPANY_ID = 'company_id';

    public const USER_ID = 'user_id';

    public const TYPE = 'type';

    public const TITLE = 'title';

    public const PAYLOAD = 'payload';

    public const STATUS = 'status';

    public const RESULT = 'result';

    public const EXPIRES_AT = 'expires_at';

    public const CREATED_AT = 'created_at';

    public const UPDATED_AT = 'updated_at';

    public const STATUS_PENDING = 'pending';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_FAILED = 'failed';

    public const TYPE_PRENOMINA = 'prenomina';

    protected $table = self::TABLE;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'result' => 'array',
            'expires_at' => 'datetime',
        ];
    }
}
