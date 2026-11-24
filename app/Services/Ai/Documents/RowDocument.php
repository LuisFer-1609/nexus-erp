<?php

namespace App\Services\Ai\Documents;

/**
 * Texto de un registro (o de un resumen) listo para dividirse en fragmentos.
 */
final class RowDocument
{
    /**
     * @param  list<string>  $lines
     */
    public function __construct(
        public readonly string $header,
        public readonly array $lines,
    ) {}

    public function text(): string
    {
        return implode("\n", [$this->header, ...$this->lines]);
    }
}
