<?php

namespace App\Services\Ai;

/**
 * Fragmento recuperado en una búsqueda, con su similitud.
 */
final class RetrievedChunk
{
    public function __construct(
        public readonly int $id,
        public readonly int $companyId,
        public readonly string $table,
        public readonly string $sourceId,
        public readonly string $kind,
        public readonly string $content,
        public readonly float $score,
        public readonly ?string $updatedAt,
    ) {}
}
