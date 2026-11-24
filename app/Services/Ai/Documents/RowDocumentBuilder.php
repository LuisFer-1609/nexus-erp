<?php

namespace App\Services\Ai\Documents;

use App\Services\Ai\Schema\ColumnDefinition;
use App\Services\Ai\Schema\TableDefinition;

/**
 * Convierte un registro de cualquier tabla en texto legible para el asistente.
 * Es genérico: funciona con cualquier tabla y columna sin código específico.
 */
final class RowDocumentBuilder
{
    public function __construct(
        private readonly LabelResolver $labels,
        private readonly ColumnPolicy $policy,
    ) {}

    /**
     * @param  array<string, mixed>  $row
     */
    public function build(TableDefinition $table, array $row): RowDocument
    {
        $lines = [];

        foreach ($table->columns as $column) {
            if ($this->policy->isHidden($column)) {
                continue;
            }

            $value = $row[$column->name] ?? null;
            if ($value === null || $value === '') {
                continue;
            }

            $lines[] = $column->name.': '.$this->format($table, $column, $value);
        }

        $id = $table->primaryKey !== null ? (string) ($row[$table->primaryKey] ?? '') : '';

        return new RowDocument('['.$table->name.' #'.$id.']', $lines);
    }

    private function format(TableDefinition $table, ColumnDefinition $column, mixed $value): string
    {
        $text = is_scalar($value) ? (string) $value : (string) json_encode($value);

        if ($column->isBoolean()) {
            return $text === '1' ? 'sí' : 'no';
        }

        $foreign = $table->foreignKeys[$column->name] ?? null;
        if ($foreign !== null) {
            $label = $this->labels->label($foreign['table'], $text);

            return $label !== null ? $label.' (id '.$text.')' : $text;
        }

        return $text;
    }
}
