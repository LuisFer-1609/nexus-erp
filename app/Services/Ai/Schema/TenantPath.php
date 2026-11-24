<?php

namespace App\Services\Ai\Schema;

/**
 * Cómo llegar desde una tabla hasta la empresa a la que pertenece cada fila.
 *
 * Sin pasos, la empresa está en una columna de la propia tabla. Con pasos, se
 * siguen llaves foráneas (JOIN) hasta una tabla que sí la tiene.
 */
final class TenantPath
{
    /**
     * @param  list<array{from_column: string, ref_table: string, ref_column: string}>  $steps
     */
    public function __construct(
        public readonly array $steps,
        public readonly string $column,
    ) {}

    /** Índice del alias de la tabla que contiene la columna de empresa (0 = la propia tabla). */
    public function aliasIndex(): int
    {
        return count($this->steps);
    }

    public function describe(string $table): string
    {
        $parts = [$table];
        foreach ($this->steps as $step) {
            $parts[] = $step['ref_table'];
        }

        return implode(' → ', $parts).' ('.$this->column.')';
    }
}
