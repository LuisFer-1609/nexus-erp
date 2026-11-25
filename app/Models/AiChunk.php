<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Fragmento de texto de la plataforma con su vector, marcado con su empresa.
 *
 * @property int $id
 * @property int $company_id
 * @property string $source_table
 * @property string $source_id
 * @property string $kind
 * @property int $chunk_index
 * @property string $content
 * @property string $content_hash
 * @property string $embedding
 * @property string $embedding_model
 * @property int $dimensions
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class AiChunk extends Model
{
    /** Nombre de la tabla. */
    public const TABLE = 'ai_chunks';

    public const ID = 'id';

    public const COMPANY_ID = 'company_id';

    public const SOURCE_TABLE = 'source_table';

    public const SOURCE_ID = 'source_id';

    public const KIND = 'kind';

    public const CHUNK_INDEX = 'chunk_index';

    public const CONTENT = 'content';

    public const CONTENT_HASH = 'content_hash';

    public const EMBEDDING = 'embedding';

    public const EMBEDDING_MODEL = 'embedding_model';

    public const DIMENSIONS = 'dimensions';

    public const CREATED_AT = 'created_at';

    public const UPDATED_AT = 'updated_at';

    /** Fragmento de un registro de una tabla. */
    public const KIND_ROW = 'row';

    /** Fragmento de resumen estadístico de una tabla para una empresa. */
    public const KIND_SUMMARY = 'summary';

    /** Identificador de origen usado por los resúmenes. */
    public const SUMMARY_SOURCE_ID = '__summary__';

    protected $table = self::TABLE;

    protected $guarded = [];
}
