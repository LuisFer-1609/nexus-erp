<?php

namespace App\Services\Ai\Documents;

use App\Services\Ai\Schema\SchemaCatalog;
use App\Services\Ai\Schema\TableDefinition;
use Illuminate\Support\Facades\DB;

/**
 * Convierte el valor de una llave foránea en un nombre legible (por ejemplo,
 * lead_stage_id = 2 → «Contactado»), para que el asistente lea "Contactado" y
 * no un número. Descubre la columna de nombre de cada tabla por sí solo.
 */
final class LabelResolver
{
    /** @var array<string, array<string, string>> */
    private array $maps = [];

    public function __construct(
        private readonly SchemaCatalog $catalog,
        private readonly ColumnPolicy $policy,
    ) {}

    public function label(string $table, string|int|null $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $this->map($table, 0)[(string) $value] ?? null;
    }

    /**
     * Todos los nombres legibles de una tabla (id => nombre).
     *
     * @return array<string, string>
     */
    public function labels(string $table): array
    {
        return $this->map($table, 0);
    }

    /**
     * @return array<string, string>
     */
    private function map(string $table, int $depth): array
    {
        if (isset($this->maps[$table])) {
            return $this->maps[$table];
        }

        // Evita ciclos entre tablas que se refieren entre sí.
        $this->maps[$table] = [];

        $definition = $this->catalog->table($table);
        if ($definition === null || $definition->primaryKey === null) {
            return [];
        }

        $primary = $definition->primaryKey;
        $strong = $this->strongColumns($definition);

        if ($strong !== []) {
            return $this->maps[$table] = $this->loadFromColumns($table, $primary, $strong);
        }

        if ($depth < 2) {
            foreach ($this->rankedForeignKeys($definition) as [$column, $target]) {
                $referenced = $this->map($target['table'], $depth + 1);
                if ($referenced === []) {
                    continue;
                }

                $map = [];
                foreach ($this->selectRows($table, [$primary, $column]) as $row) {
                    $label = $referenced[(string) ($row[$column] ?? '')] ?? null;
                    if ($label !== null) {
                        $map[(string) $row[$primary]] = $label;
                    }
                }

                return $this->maps[$table] = $map;
            }
        }

        $fallback = $this->existingColumns($definition, (array) config('nexus_ai.sync.label_fallback_columns'));

        return $this->maps[$table] = $fallback === []
            ? []
            : $this->loadFromColumns($table, $primary, [$fallback[0]]);
    }

    /**
     * Llaves foráneas ordenadas por qué tanto "identifican" a la fila:
     *  1. Las únicas (relación 1 a 1): el registro es una extensión de ese otro
     *     (p. ej. un empleado "es" su usuario).
     *  2. Las que apuntan a una persona (tabla con nombre y apellido).
     *  3. El resto, por nombre de columna.
     *
     * @return list<array{0: string, 1: array{table: string, column: string}}>
     */
    private function rankedForeignKeys(TableDefinition $definition): array
    {
        $ranked = [];
        foreach ($definition->foreignKeys as $column => $target) {
            $referenced = $this->catalog->table($target['table']);
            $rank = match (true) {
                in_array($column, $definition->uniqueColumns, true) => 0,
                $referenced !== null && $referenced->hasColumn('name') && $referenced->hasColumn('last_name') => 1,
                default => 2,
            };
            $ranked[] = [$rank, $column, $target];
        }

        usort($ranked, static fn (array $a, array $b): int => [$a[0], $a[1]] <=> [$b[0], $b[1]]);

        return array_map(static fn (array $item): array => [$item[1], $item[2]], $ranked);
    }

    /**
     * @param  list<string>  $columns
     * @return array<string, string>
     */
    private function loadFromColumns(string $table, string $primary, array $columns): array
    {
        $map = [];
        foreach ($this->selectRows($table, [$primary, ...$columns]) as $row) {
            $parts = [];
            foreach ($columns as $column) {
                $part = trim((string) ($row[$column] ?? ''));
                if ($part !== '') {
                    $parts[] = $part;
                }
            }
            if ($parts !== []) {
                $map[(string) $row[$primary]] = mb_substr(implode(' ', $parts), 0, 100);
            }
        }

        return $map;
    }

    /**
     * @param  list<string>  $columns
     * @return list<array<string, mixed>>
     */
    private function selectRows(string $table, array $columns): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = DB::table($table)->select($columns)->get()
            ->map(static fn (object $row): array => (array) $row)
            ->values()
            ->all();

        return $rows;
    }

    /**
     * @return list<string>
     */
    private function strongColumns(TableDefinition $definition): array
    {
        if ($this->usable($definition, 'name') && $this->usable($definition, 'last_name')) {
            return ['name', 'last_name'];
        }

        return array_slice($this->existingColumns($definition, (array) config('nexus_ai.sync.label_columns')), 0, 1);
    }

    /**
     * @param  array<mixed>  $candidates
     * @return list<string>
     */
    private function existingColumns(TableDefinition $definition, array $candidates): array
    {
        $found = [];
        foreach ($candidates as $candidate) {
            if (is_string($candidate) && $this->usable($definition, $candidate)) {
                $found[] = $candidate;
            }
        }

        return $found;
    }

    private function usable(TableDefinition $definition, string $column): bool
    {
        $definitionColumn = $definition->column($column);

        return $definitionColumn !== null && ! $this->policy->isSensitive($definitionColumn);
    }
}
