<?php

namespace App\Services\Ai;

/**
 * Respuesta de Nexus. `grounded` indica si se apoyó en datos recuperados de la
 * empresa; cuando es false, la respuesta es un mensaje fijo y no se usó el modelo.
 */
final class AnswerResult
{
    /**
     * @param  list<array{table: string, id: string, kind: string, score: float}>  $sources
     * @param  list<array<string, mixed>>  $artifacts  Archivos generados (reportes PDF).
     * @param  list<array<string, mixed>>  $actions  Acciones que esperan confirmación del usuario.
     */
    public function __construct(
        public readonly string $answer,
        public readonly bool $grounded,
        public readonly array $sources = [],
        public readonly array $artifacts = [],
        public readonly array $actions = [],
    ) {}
}
