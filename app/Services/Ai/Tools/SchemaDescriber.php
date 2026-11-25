<?php

namespace App\Services\Ai\Tools;

use App\Services\Ai\Documents\ColumnPolicy;
use App\Services\Ai\Schema\ColumnDefinition;
use App\Services\Ai\Schema\SchemaCatalog;

/**
 * Describe en pocas líneas las tablas y columnas consultables, para que el
 * modelo sepa qué puede pedirle a «consultar_datos». Se genera del esquema real
 * en cada carga, así que una tabla o columna nueva aparece sola.
 */
final class SchemaDescriber
{
    public function __construct(
        private readonly SchemaCatalog $catalog,
        private readonly ColumnPolicy $policy,
    ) {}

    public function describe(): string
    {
        $lines = [];

        foreach ($this->catalog->indexable() as $table) {
            $columns = [];
            foreach ($table->columns as $column) {
                if (! $this->policy->isQueryable($column)) {
                    continue;
                }

                $foreign = $table->foreignKeys[$column->name] ?? null;
                $columns[] = $column->name.($foreign !== null ? '→'.$foreign['table'] : '').' ('.$this->type($column).')';
            }

            $lines[] = '- '.$table->name.': '.implode(', ', $columns);
        }

        return implode("\n", $lines);
    }

    private function type(ColumnDefinition $column): string
    {
        return match (true) {
            $column->isBoolean() => 'sí/no',
            $column->isDate() => 'fecha',
            $column->isNumber() => 'número',
            default => 'texto',
        };
    }
}
