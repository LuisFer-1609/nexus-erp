<?php

namespace App\Services\Ai;

use App\Services\Ai\Contracts\EmbeddingClient;
use Illuminate\Support\Str;

/**
 * Embeddings léxicos locales (bolsa de palabras con hashing). No usa red ni
 * llaves y no entiende sinónimos, así que solo sirve para desarrollo y
 * pruebas. En producción se usa el proveedor de Google.
 */
final class LocalEmbeddingClient implements EmbeddingClient
{
    public function __construct(private readonly int $dimensions = 256) {}

    public function isConfigured(): bool
    {
        return true;
    }

    public function model(): string
    {
        return 'local-hash-v1';
    }

    public function dimensions(): int
    {
        return $this->dimensions;
    }

    public function embedDocuments(array $texts): array
    {
        return array_map(fn (string $text): array => $this->vectorize($text), $texts);
    }

    public function embedQuery(string $text): array
    {
        return $this->vectorize($text);
    }

    /**
     * @return list<float>
     */
    private function vectorize(string $text): array
    {
        $vector = array_fill(0, $this->dimensions, 0.0);
        $tokens = preg_split('/[^a-z0-9]+/', Str::lower(Str::ascii($text)), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        foreach ($tokens as $token) {
            if (strlen($token) < 2) {
                continue;
            }

            $hash = crc32($token);
            $index = $hash % $this->dimensions;
            $vector[$index] += (($hash >> 16) & 1) === 1 ? 1.0 : -1.0;
        }

        return VectorCodec::normalize($vector);
    }
}
