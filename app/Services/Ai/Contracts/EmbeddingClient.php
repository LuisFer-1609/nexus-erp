<?php

namespace App\Services\Ai\Contracts;

interface EmbeddingClient
{
    /** Indica si el proveedor puede operar (por ejemplo, si hay llaves configuradas). */
    public function isConfigured(): bool;

    /** Nombre del modelo; se guarda en cada fragmento para detectar cambios de modelo. */
    public function model(): string;

    public function dimensions(): int;

    /**
     * Vectores normalizados de textos que se van a guardar.
     *
     * @param  list<string>  $texts
     * @return list<list<float>>
     */
    public function embedDocuments(array $texts): array;

    /**
     * Vector normalizado de una pregunta.
     *
     * @return list<float>
     */
    public function embedQuery(string $text): array;
}
