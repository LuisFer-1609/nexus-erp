<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Archivo que el usuario adjuntó a un mensaje. Vive en el disco privado.
 *
 * @property string $id
 * @property int $message_id
 * @property string $name
 * @property string $mime
 * @property int $size_bytes
 * @property string $path
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
class NexusAttachment extends Model
{
    use HasUuids;

    public const TABLE = 'nexus_chat_attachments';

    public const ID = 'id';

    public const MESSAGE_ID = 'message_id';

    public const NAME = 'name';

    public const MIME = 'mime';

    public const SIZE_BYTES = 'size_bytes';

    public const PATH = 'path';

    protected $table = self::TABLE;

    protected $guarded = [];

    /**
     * @return BelongsTo<NexusMessage, $this>
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(NexusMessage::class, self::MESSAGE_ID);
    }
}
