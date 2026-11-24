<?php

namespace App\Services\Ai\Schema;

final class TableDefinition
{
    /**
     * @param  list<ColumnDefinition>  $columns
     * @param  array<string, array{table: string, column: string}>  $foreignKeys  Por columna local.
     * @param  list<string>  $uniqueColumns  Columnas con índice único de una sola columna.
     */
    public function __construct(
        public readonly string $name,
        public readonly array $columns,
        public readonly ?string $primaryKey,
        public readonly array $foreignKeys,
        public readonly array $uniqueColumns,
        public readonly ?TenantPath $tenantPath,
        public readonly bool $indexable,
        public readonly ?string $reason,
    ) {}

    public function column(string $name): ?ColumnDefinition
    {
        foreach ($this->columns as $column) {
            if ($column->name === $name) {
                return $column;
            }
        }

        return null;
    }

    public function hasColumn(string $name): bool
    {
        return $this->column($name) !== null;
    }
}
