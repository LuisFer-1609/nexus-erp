<?php

namespace App\Services\Ai;

use App\Services\Ai\Contracts\EmbeddingClient;
use Illuminate\Support\Facades\Cache;

/**
 * Recupera los fragmentos relevantes para una pregunta, solo de la empresa
 * indicada. El vector de la pregunta se guarda unos minutos en caché para que
 * repetir una pregunta no cueste otra llamada a la API.
 */
class Retriever
{
    public function __construct(
        private readonly EmbeddingClient $embeddings,
        private readonly VectorStore $store,
    ) {}

    /**
     * @return list<RetrievedChunk>
     */
    public function retrieve(int $companyId, string $question, ?int $limit = null, ?float $minScore = null): array
    {
        return $this->store->search(
            $companyId,
            $this->queryVector($question),
            $limit ?? (int) config('nexus_ai.retrieval.top_k'),
            $minScore ?? (float) config('nexus_ai.retrieval.min_score'),
            $this->embeddings->model(),
        );
    }

    /**
     * @return list<float>
     */
    private function queryVector(string $question): array
    {
        $normalized = mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $question)));
        $key = 'nexus_ai:query:'.hash('sha256', $this->embeddings->model().'|'.$this->embeddings->dimensions().'|'.$normalized);

        /** @var list<float> $vector */
        $vector = Cache::remember(
            $key,
            (int) config('nexus_ai.retrieval.query_cache_seconds'),
            fn (): array => $this->embeddings->embedQuery($normalized),
        );

        return $vector;
    }
}
