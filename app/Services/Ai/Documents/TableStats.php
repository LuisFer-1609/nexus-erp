<?php

namespace App\Services\Ai\Documents;

use App\Services\Ai\Schema\ColumnDefinition;
use App\Services\Ai\Schema\TableDefinition;

/**
 * Acumula estadísticas de una tabla para una empresa mientras se recorren sus
 * filas y las convierte en un resumen. Los fragmentos sueltos no permiten
 * responder "¿cuántos…?" o "¿cuál es el total…?"; el resumen sí.
 *
 * Es genérico: decide qué resumir según el tipo de cada columna.
 */
final class TableStats
{
    /** Columnas de texto cuyo nombre sugiere valores libres (no categorías). */
    private const FREE_TEXT_HINT = '/(name|email|mail|phone|code|rfc|note|address|desc|title|token|url|path|file|picture|comment)/i';

    private int $count = 0;

    /** @var array<string, array<string, int>|null> null = demasiados valores distintos. */
    private array $categories = [];

    /** @var array<string, array{sum: float, min: float, max: float, n: int}> */
    private array $numbers = [];

    /** @var array<string, array{min: string, max: string, months: array<string, int>}> */
    private array $dates = [];

    /** @var list<ColumnDefinition> */
    private array $categoryColumns = [];

    /** @var list<ColumnDefinition> */
    private array $numberColumns = [];

    /** @var list<ColumnDefinition> */
    private array $dateColumns = [];

    public function __construct(
        private readonly TableDefinition $table,
        ColumnPolicy $policy,
        private readonly int $maxDistinct = 25,
    ) {
        $tenantColumn = (string) config('nexus_ai.sync.tenant_column');

        foreach ($table->columns as $column) {
            // La columna de empresa es siempre la misma dentro de un resumen: no aporta.
            if ($policy->isHidden($column) || $column->name === $tenantColumn) {
                continue;
            }

            $isForeign = isset($table->foreignKeys[$column->name]);

            if ($column->isBoolean() || $isForeign) {
                $this->categoryColumns[] = $column;
            } elseif ($column->isText() && ($column->length() ?? 0) > 0 && $column->length() <= 40 && preg_match(self::FREE_TEXT_HINT, $column->name) !== 1) {
                $this->categoryColumns[] = $column;
            } elseif ($column->isDate()) {
                $this->dateColumns[] = $column;
            } elseif ($column->isNumber() && $column->dataType !== 'tinyint' && ! str_ends_with($column->name, '_id')) {
                $this->numberColumns[] = $column;
            }
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function add(array $row): void
    {
        $this->count++;

        foreach ($this->categoryColumns as $column) {
            $value = $row[$column->name] ?? null;
            $overflowed = array_key_exists($column->name, $this->categories) && $this->categories[$column->name] === null;
            if ($value === null || $overflowed) {
                continue;
            }

            $key = (string) $value;
            $this->categories[$column->name][$key] = ($this->categories[$column->name][$key] ?? 0) + 1;

            if (count($this->categories[$column->name]) > $this->maxDistinct) {
                $this->categories[$column->name] = null;
            }
        }

        foreach ($this->numberColumns as $column) {
            $value = $row[$column->name] ?? null;
            if (! is_numeric($value)) {
                continue;
            }

            $number = (float) $value;
            $stat = $this->numbers[$column->name] ?? ['sum' => 0.0, 'min' => $number, 'max' => $number, 'n' => 0];
            $stat['sum'] += $number;
            $stat['min'] = min($stat['min'], $number);
            $stat['max'] = max($stat['max'], $number);
            $stat['n']++;
            $this->numbers[$column->name] = $stat;
        }

        foreach ($this->dateColumns as $column) {
            $value = $row[$column->name] ?? null;
            if (! is_string($value) || $value === '') {
                continue;
            }

            $stat = $this->dates[$column->name] ?? ['min' => $value, 'max' => $value, 'months' => []];
            $stat['min'] = min($stat['min'], $value);
            $stat['max'] = max($stat['max'], $value);
            $month = substr($value, 0, 7);
            $stat['months'][$month] = ($stat['months'][$month] ?? 0) + 1;
            $this->dates[$column->name] = $stat;
        }
    }

    public function render(LabelResolver $labels): RowDocument
    {
        $header = '[resumen de '.$this->table->name.']';

        if ($this->count === 0) {
            return new RowDocument($header, [
                'Resumen de la tabla '.$this->table->name.': esta empresa no tiene registros en esta tabla (total: 0).',
            ]);
        }

        $lines = [
            'Resumen calculado con todos los registros de esta empresa en la tabla '.$this->table->name.'.',
            'Total de registros: '.$this->count.'.',
        ];

        foreach ($this->categoryColumns as $column) {
            $counts = $this->categories[$column->name] ?? null;
            if ($counts === null || $counts === []) {
                continue;
            }

            arsort($counts);

            $names = [];
            foreach (array_keys($counts) as $value) {
                $names[(string) $value] = $this->categoryLabel($column, (string) $value, $labels);
            }
            $repeated = array_filter(array_count_values($names), static fn (int $times): bool => $times > 1);

            $parts = [];
            foreach ($counts as $value => $n) {
                $name = $names[(string) $value];
                // Si dos valores comparten nombre, se distinguen por su id.
                $parts[] = (isset($repeated[$name]) ? $name.' (id '.$value.')' : $name).' → '.$n;
            }
            $lines[] = 'Registros por '.$column->name.': '.implode('; ', $parts).'.';
        }

        foreach ($this->numberColumns as $column) {
            $stat = $this->numbers[$column->name] ?? null;
            if ($stat === null) {
                continue;
            }
            $lines[] = $column->name.': suma '.$this->number($stat['sum'])
                .', promedio '.$this->number($stat['sum'] / $stat['n'])
                .', mínimo '.$this->number($stat['min'])
                .', máximo '.$this->number($stat['max']).' ('.$stat['n'].' registros con valor).';
        }

        foreach ($this->dateColumns as $column) {
            $stat = $this->dates[$column->name] ?? null;
            if ($stat === null) {
                continue;
            }
            $lines[] = $column->name.': desde '.$stat['min'].' hasta '.$stat['max'].'.';

            ksort($stat['months']);
            $months = [];
            foreach (array_slice($stat['months'], -12, null, true) as $month => $n) {
                $months[] = $month.' → '.$n;
            }
            $lines[] = 'Registros por mes de '.$column->name.' (últimos 12 meses con datos): '.implode('; ', $months).'.';
        }

        return new RowDocument($header, $lines);
    }

    private function categoryLabel(ColumnDefinition $column, string $value, LabelResolver $labels): string
    {
        if ($column->isBoolean()) {
            return $value === '1' ? 'sí' : 'no';
        }

        $foreign = $this->table->foreignKeys[$column->name] ?? null;
        if ($foreign !== null) {
            return $labels->label($foreign['table'], $value) ?? 'id '.$value;
        }

        return $value;
    }

    private function number(float $value): string
    {
        if (floor($value) === $value) {
            return (string) (int) $value;
        }

        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
