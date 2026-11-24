<?php

namespace App\Services\Ai;

use App\Services\Ai\Contracts\EmbeddingClient;

/**
 * Embeddings con la API de Google. Los vectores se normalizan aquí porque al
 * reducir dimensiones Google no garantiza longitud 1.
 */
final class GeminiEmbeddingClient implements EmbeddingClient
{
    /** Textos por llamada (el límite de Google es 100). */
    private const BATCH = 50;

    public function __construct(private readonly GeminiClient $client) {}

    public function isConfigured(): bool
    {
        return $this->client->isConfigured();
    }

    public function model(): string
    {
        return (string) config('nexus_ai.gemini.embedding_model');
    }

    public function dimensions(): int
    {
        return (int) config('nexus_ai.gemini.dimensions');
    }

    public function embedDocuments(array $texts): array
    {
        $vectors = [];

        foreach (array_chunk($texts, self::BATCH) as $batch) {
            foreach ($this->client->embed($batch, 'RETRIEVAL_DOCUMENT') as $vector) {
                $vectors[] = VectorCodec::normalize($vector);
            }
        }

        return $vectors;
    }

    public function embedQuery(string $text): array
    {
        $vectors = $this->client->embed([$text], 'RETRIEVAL_QUERY');

        return VectorCodec::normalize($vectors[0]);
    }
}
