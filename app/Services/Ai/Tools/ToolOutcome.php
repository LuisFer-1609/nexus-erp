<?php

namespace App\Services\Ai\Tools;

/**
 * Resultado de ejecutar una herramienta.
 */
final class ToolOutcome
{
    /**
     * @param  array<string, mixed>  $response  Lo que se le devuelve al modelo.
     * @param  string|null  $final  Si existe, es la respuesta final y se corta la conversación con el modelo.
     * @param  list<array<string, mixed>>  $artifacts  Archivos generados (reportes) que se muestran en el chat.
     * @param  list<array<string, mixed>>  $actions  Acciones que esperan confirmación del usuario.
     */
    public function __construct(
        public readonly array $response,
        public readonly ?string $final = null,
        public readonly array $artifacts = [],
        public readonly array $actions = [],
    ) {}
}
