<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Reporte PDF generado por Nexus.
 *
 * @property string $id
 * @property int $company_id
 * @property int|null $user_id
 * @property string $title
 * @property string $path
 * @property int $size_bytes
 * @property array<string, mixed>|null $meta
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class AiReport extends Model
{
    use HasUuids;

    /** Nombre de la tabla. */
    public const TABLE = 'ai_reports';

    public const ID = 'id';

    public const COMPANY_ID = 'company_id';

    public const USER_ID = 'user_id';

    public const TITLE = 'title';

    public const PATH = 'path';

    public const SIZE_BYTES = 'size_bytes';

    public const META = 'meta';

    public const CREATED_AT = 'created_at';

    public const UPDATED_AT = 'updated_at';

    protected $table = self::TABLE;

    protected $guarded = [];

    /**
     * Tarjeta del reporte tal como la muestra el chat: descarga y vista previa.
     *
     * @return array{type: string, id: string, title: string, url: string, preview_url: string, thumb_url: string, size: int}
     */
    public function toArtifact(): array
    {
        return [
            'type' => 'report',
            'id' => $this->id,
            'title' => $this->title,
            'url' => route('ia.reportes.descargar', $this->id, false),
            'preview_url' => route('ia.reportes.ver', $this->id, false),
            'thumb_url' => route('ia.reportes.miniatura', $this->id, false),
            'size' => $this->size_bytes,
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['meta' => 'array'];
    }
}
