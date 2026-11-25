<?php

namespace App\Services\Ai;

use App\Models\AiChunk;
use Illuminate\Support\Facades\DB;

/**
 * Almacén vectorial sobre MySQL/MariaDB. Cada fragmento lleva su empresa, y la
 * búsqueda siempre se limita a una empresa: no existe forma de buscar sin ella.
 */
class VectorStore
{
    /**
     * Reemplaza todos los fragmentos de un registro (o de un resumen) por los nuevos.
     *
     * @param  list<array{content: string, vector: list<float>}>  $chunks
     */
    public function replace(
        int $companyId,
        string $table,
        string $sourceId,
        string $kind,
        string $hash,
        string $model,
        array $chunks,
    ): void {
        DB::transaction(function () use ($companyId, $table, $sourceId, $kind, $hash, $model, $chunks): void {
            AiChunk::query()
                ->where(AiChunk::COMPANY_ID, $companyId)
                ->where(AiChunk::SOURCE_TABLE, $table)
                ->where(AiChunk::SOURCE_ID, $sourceId)
                ->where(AiChunk::KIND, $kind)
                ->delete();

            $now = now();
            $rows = [];
            foreach ($chunks as $index => $chunk) {
                $rows[] = [
                    AiChunk::COMPANY_ID => $companyId,
                    AiChunk::SOURCE_TABLE => $table,
                    AiChunk::SOURCE_ID => $sourceId,
                    AiChunk::KIND => $kind,
                    AiChunk::CHUNK_INDEX => $index,
                    AiChunk::CONTENT => $chunk['content'],
                    AiChunk::CONTENT_HASH => $hash,
                    AiChunk::EMBEDDING => VectorCodec::pack($chunk['vector']),
                    AiChunk::EMBEDDING_MODEL => $model,
                    AiChunk::DIMENSIONS => count($chunk['vector']),
                    AiChunk::CREATED_AT => $now,
                    AiChunk::UPDATED_AT => $now,
                ];
            }

            if ($rows !== []) {
                AiChunk::query()->insert($rows);
            }
        });
    }

    /**
     * @param  list<int>  $ids
     */
    public function deleteIds(array $ids): int
    {
        $deleted = 0;
        foreach (array_chunk($ids, 500) as $batch) {
            $deleted += AiChunk::query()->whereIn(AiChunk::ID, $batch)->delete();
        }

        return $deleted;
    }

    /**
     * Elimina todos los fragmentos de las tablas que ya no se indexan.
     *
     * @param  list<string>  $keepTables
     */
    public function deleteTablesNotIn(array $keepTables): int
    {
        return AiChunk::query()->whereNotIn(AiChunk::SOURCE_TABLE, $keepTables)->delete();
    }

    /**
     * Busca los fragmentos más parecidos dentro de UNA empresa.
     *
     * @param  list<float>  $queryVector  Vector normalizado de la pregunta.
     * @return list<RetrievedChunk>
     */
    public function search(int $companyId, array $queryVector, int $limit, float $minScore, string $model): array
    {
        $scores = [];

        $candidates = AiChunk::query()
            ->where(AiChunk::COMPANY_ID, $companyId)
            ->where(AiChunk::EMBEDDING_MODEL, $model)
            ->where(AiChunk::DIMENSIONS, count($queryVector))
            ->select([AiChunk::ID, AiChunk::EMBEDDING])
            ->lazyById(500);

        foreach ($candidates as $candidate) {
            $score = VectorCodec::dot($queryVector, VectorCodec::unpack($candidate->embedding));
            if ($score >= $minScore) {
                $scores[$candidate->id] = $score;
            }
        }

        if ($scores === []) {
            return [];
        }

        arsort($scores);
        $top = array_slice($scores, 0, max(1, $limit), true);

        $chunks = AiChunk::query()
            ->where(AiChunk::COMPANY_ID, $companyId)
            ->whereIn(AiChunk::ID, array_keys($top))
            ->get()
            ->keyBy(AiChunk::ID);

        $results = [];
        foreach ($top as $id => $score) {
            $chunk = $chunks->get($id);
            // Doble verificación del aislamiento: nunca se devuelve otra empresa.
            if ($chunk === null || (int) $chunk->company_id !== $companyId) {
                continue;
            }

            $results[] = new RetrievedChunk(
                id: (int) $chunk->id,
                companyId: (int) $chunk->company_id,
                table: $chunk->source_table,
                sourceId: $chunk->source_id,
                kind: $chunk->kind,
                content: $chunk->content,
                score: $score,
                updatedAt: $chunk->updated_at?->toIso8601String(),
            );
        }

        return $results;
    }
}
