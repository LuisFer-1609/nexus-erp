<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Una ejecución de la carga de datos del asistente.
 *
 * @property int $id
 * @property string $status
 * @property string $trigger
 * @property int|null $company_id
 * @property string|null $scope_table
 * @property string|null $schema_fingerprint
 * @property int $tables_indexed
 * @property int $rows_scanned
 * @property int $chunks_created
 * @property int $chunks_updated
 * @property int $chunks_unchanged
 * @property int $chunks_deleted
 * @property array<string, mixed>|null $details
 * @property string|null $error
 * @property CarbonInterface|null $started_at
 * @property CarbonInterface|null $finished_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class AiSyncRun extends Model
{
    /** Nombre de la tabla. */
    public const TABLE = 'ai_sync_runs';

    public const ID = 'id';

    public const STATUS = 'status';

    public const TRIGGER = 'trigger';

    public const COMPANY_ID = 'company_id';

    public const SCOPE_TABLE = 'scope_table';

    public const SCHEMA_FINGERPRINT = 'schema_fingerprint';

    public const TABLES_INDEXED = 'tables_indexed';

    public const ROWS_SCANNED = 'rows_scanned';

    public const CHUNKS_CREATED = 'chunks_created';

    public const CHUNKS_UPDATED = 'chunks_updated';

    public const CHUNKS_UNCHANGED = 'chunks_unchanged';

    public const CHUNKS_DELETED = 'chunks_deleted';

    public const DETAILS = 'details';

    public const ERROR = 'error';

    public const STARTED_AT = 'started_at';

    public const FINISHED_AT = 'finished_at';

    public const CREATED_AT = 'created_at';

    public const UPDATED_AT = 'updated_at';

    public const STATUS_RUNNING = 'running';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_COMPLETED_WITH_ERRORS = 'completed_with_errors';

    public const STATUS_FAILED = 'failed';

    protected $table = self::TABLE;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'details' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
