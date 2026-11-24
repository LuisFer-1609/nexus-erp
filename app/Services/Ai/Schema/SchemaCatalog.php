<?php

namespace App\Services\Ai\Schema;

use App\Services\Ai\Exceptions\AiUnavailableException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Descubre el esquema de la base de datos en cada ejecución. No hay listas de
 * tablas escritas a mano: una tabla nueva, o una columna nueva, se incorpora
 * sola en la siguiente carga.
 *
 * Una tabla se indexa si se puede determinar a qué empresa pertenece cada fila,
 * ya sea por una columna `company_id` propia o siguiendo llaves foráneas.
 * Los catálogos globales (sin empresa) no se indexan por sí mismos: solo se
 * usan para mostrar nombres legibles en los demás registros.
 */
final class SchemaCatalog
{
    /**
     * @param  array<string, TableDefinition>  $tables
     */
    private function __construct(private readonly array $tables) {}

    public static function build(): self
    {
        $driver = DB::connection()->getDriverName();
        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            throw new AiUnavailableException("La carga del asistente requiere MySQL o MariaDB (driver actual: {$driver}).");
        }

        $database = DB::connection()->getDatabaseName();

        $columnRows = DB::select(
            "select c.table_name as t, c.column_name as c, lower(c.data_type) as dt,
                    lower(c.column_type) as ct, c.column_key as ck
             from information_schema.columns c
             join information_schema.tables tb
               on tb.table_schema = c.table_schema and tb.table_name = c.table_name
             where c.table_schema = ? and tb.table_type = 'BASE TABLE'
             order by c.table_name, c.ordinal_position",
            [$database],
        );

        $fkRows = DB::select(
            'select table_name as t, column_name as c, referenced_table_name as rt, referenced_column_name as rc
             from information_schema.key_column_usage
             where table_schema = ? and referenced_table_name is not null
             order by table_name, constraint_name, ordinal_position',
            [$database],
        );

        $uniqueRows = DB::select(
            'select table_name as t, index_name as i, column_name as c
             from information_schema.statistics
             where table_schema = ? and non_unique = 0',
            [$database],
        );

        /** @var array<string, array<string, list<string>>> $uniqueByIndex */
        $uniqueByIndex = [];
        foreach ($uniqueRows as $row) {
            $uniqueByIndex[(string) $row->t][(string) $row->i][] = (string) $row->c;
        }

        /** @var array<string, list<ColumnDefinition>> $columnsByTable */
        $columnsByTable = [];
        /** @var array<string, list<string>> $primaryByTable */
        $primaryByTable = [];
        foreach ($columnRows as $row) {
            $table = (string) $row->t;
            $isPrimary = $row->ck === 'PRI';
            $columnsByTable[$table][] = new ColumnDefinition((string) $row->c, (string) $row->dt, (string) $row->ct, $isPrimary);
            if ($isPrimary) {
                $primaryByTable[$table][] = (string) $row->c;
            }
        }

        /** @var array<string, array<string, array{table: string, column: string}>> $fksByTable */
        $fksByTable = [];
        foreach ($fkRows as $row) {
            $fksByTable[(string) $row->t][(string) $row->c] = ['table' => (string) $row->rt, 'column' => (string) $row->rc];
        }

        $tenantTable = (string) config('nexus_ai.sync.tenant_table');
        $tenantColumn = (string) config('nexus_ai.sync.tenant_column');
        $maxDepth = (int) config('nexus_ai.sync.max_fk_depth');
        /** @var list<string> $excluded */
        $excluded = (array) config('nexus_ai.sync.exclude_tables');

        $tables = [];
        foreach ($columnsByTable as $name => $columns) {
            $primary = $primaryByTable[$name] ?? [];
            $primaryKey = count($primary) === 1 ? $primary[0] : null;
            $fks = $fksByTable[$name] ?? [];

            $uniqueColumns = [];
            foreach ($uniqueByIndex[$name] ?? [] as $indexColumns) {
                if (count($indexColumns) === 1) {
                    $uniqueColumns[] = $indexColumns[0];
                }
            }

            $reason = null;
            $path = null;

            if (Str::is($excluded, $name)) {
                $reason = 'excluida por configuración';
            } elseif (preg_match('/^\w+$/', $name) !== 1) {
                $reason = 'nombre de tabla no soportado';
            } elseif ($primaryKey === null) {
                $reason = 'sin clave primaria simple';
            } else {
                $path = self::findTenantPath($name, $columnsByTable, $fksByTable, $tenantTable, $tenantColumn, $maxDepth);
                if ($path === null) {
                    $reason = 'sin empresa asociada (catálogo global)';
                }
            }

            $tables[$name] = new TableDefinition(
                name: $name,
                columns: $columns,
                primaryKey: $primaryKey,
                foreignKeys: $fks,
                uniqueColumns: $uniqueColumns,
                tenantPath: $path,
                indexable: $reason === null,
                reason: $reason,
            );
        }

        ksort($tables);

        return new self($tables);
    }

    public function table(string $name): ?TableDefinition
    {
        return $this->tables[$name] ?? null;
    }

    /**
     * @return array<string, TableDefinition>
     */
    public function tables(): array
    {
        return $this->tables;
    }

    /**
     * @return array<string, TableDefinition>
     */
    public function indexable(): array
    {
        return array_filter($this->tables, static fn (TableDefinition $t): bool => $t->indexable);
    }

    /**
     * Resumen del esquema (sin las tablas excluidas por configuración) para
     * detectar y describir cambios estructurales entre cargas.
     *
     * @return array<string, array{columns: list<string>, indexable: bool, reason: string|null, tenant: string|null}>
     */
    public function snapshot(): array
    {
        $snapshot = [];
        foreach ($this->tables as $name => $table) {
            if ($table->reason === 'excluida por configuración') {
                continue;
            }
            $snapshot[$name] = [
                'columns' => array_map(static fn (ColumnDefinition $c): string => $c->name.':'.$c->columnType, $table->columns),
                'indexable' => $table->indexable,
                'reason' => $table->reason,
                'tenant' => $table->tenantPath?->describe($name),
            ];
        }

        return $snapshot;
    }

    public function fingerprint(): string
    {
        return hash('sha256', (string) json_encode($this->snapshot()));
    }

    /**
     * Busca, con una búsqueda en anchura sobre las llaves foráneas, el camino
     * más corto desde la tabla hasta una columna que identifique la empresa.
     *
     * @param  array<string, list<ColumnDefinition>>  $columnsByTable
     * @param  array<string, array<string, array{table: string, column: string}>>  $fksByTable
     */
    private static function findTenantPath(
        string $start,
        array $columnsByTable,
        array $fksByTable,
        string $tenantTable,
        string $tenantColumn,
        int $maxDepth,
    ): ?TenantPath {
        // La propia tabla de empresas: su clave es la empresa.
        if ($start === $tenantTable) {
            return new TenantPath([], 'id');
        }

        /** @var list<array{table: string, steps: list<array{from_column: string, ref_table: string, ref_column: string}>}> $queue */
        $queue = [['table' => $start, 'steps' => []]];
        $visited = [$start => true];

        while ($queue !== []) {
            $state = array_shift($queue);
            $table = $state['table'];
            $steps = $state['steps'];

            foreach ($columnsByTable[$table] ?? [] as $column) {
                if ($column->name === $tenantColumn) {
                    return new TenantPath($steps, $tenantColumn);
                }
            }

            $fks = $fksByTable[$table] ?? [];
            ksort($fks);

            // Una llave directa a la tabla de empresas basta: su valor ya es la empresa.
            foreach ($fks as $column => $target) {
                if ($target['table'] === $tenantTable) {
                    return new TenantPath($steps, $column);
                }
            }

            if (count($steps) >= $maxDepth) {
                continue;
            }

            foreach ($fks as $column => $target) {
                if (isset($visited[$target['table']]) || ! isset($columnsByTable[$target['table']])) {
                    continue;
                }
                $visited[$target['table']] = true;
                $queue[] = [
                    'table' => $target['table'],
                    'steps' => [...$steps, ['from_column' => $column, 'ref_table' => $target['table'], 'ref_column' => $target['column']]],
                ];
            }
        }

        return null;
    }
}
