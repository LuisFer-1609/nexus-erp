<?php

namespace App\Services\Ai\Tools;

use App\Services\Ai\Documents\ColumnPolicy;
use App\Services\Ai\Documents\LabelResolver;
use App\Services\Ai\Documents\TenantRowReader;
use App\Services\Ai\Schema\ColumnDefinition;
use App\Services\Ai\Schema\SchemaCatalog;
use App\Services\Ai\Schema\TableDefinition;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\DB;

/**
 * Ejecuta consultas descritas por el modelo (tabla, filtros, agrupaciones y
 * totales) de forma segura:
 *  - El modelo nunca escribe SQL: solo describe la consulta en una estructura.
 *  - Tablas y columnas se validan contra el esquema real; las columnas
 *    sensibles (contraseñas, tokens, CURP…) no se pueden consultar.
 *  - La consulta parte de la base con el filtro de EMPRESA ya aplicado, así
 *    que es imposible leer datos de otra empresa.
 *  - Los valores van como parámetros, nunca concatenados.
 */
final class DataQueryService
{
    public const MAX_ROWS = 200;

    private const DEFAULT_ROWS = 50;

    /** @var array<string, string> */
    private const COMPARE = [
        '=' => '=', 'eq' => '=', 'igual' => '=',
        '!=' => '<>', '<>' => '<>', 'ne' => '<>', 'distinto' => '<>',
        '>' => '>', 'gt' => '>', 'mayor' => '>',
        '>=' => '>=', 'gte' => '>=', 'mayor_igual' => '>=',
        '<' => '<', 'lt' => '<', 'menor' => '<',
        '<=' => '<=', 'lte' => '<=', 'menor_igual' => '<=',
    ];

    private const AGGREGATES = ['count', 'count_distinct', 'sum', 'avg', 'min', 'max'];

    private const BUCKETS = ['day' => '%Y-%m-%d', 'month' => '%Y-%m', 'year' => '%Y'];

    public function __construct(
        private readonly SchemaCatalog $catalog,
        private readonly LabelResolver $labels,
        private readonly ColumnPolicy $policy,
        private readonly TenantRowReader $reader,
    ) {}

    /**
     * @param  array<string, mixed>  $spec
     * @return array{table: string, columns: list<string>, rows: list<list<mixed>>, truncated: bool}
     *
     * @throws InvalidToolCallException
     */
    public function run(int $companyId, array $spec): array
    {
        $table = $this->table($spec['table'] ?? null);
        $query = $this->reader->baseQuery($table, $companyId);

        $this->applyFilters($query, $table, $spec['filters'] ?? []);

        $limit = min(self::MAX_ROWS, max(1, (int) ($spec['limit'] ?? self::DEFAULT_ROWS)));
        $groupBy = $this->parseGroupBy($table, $spec['group_by'] ?? []);
        $aggregates = $this->parseAggregates($table, $spec['aggregates'] ?? []);

        return $groupBy !== [] || $aggregates !== []
            ? $this->aggregated($query, $table, $groupBy, $aggregates, $spec['order_by'] ?? [], $limit)
            : $this->listing($query, $table, $spec['columns'] ?? [], $spec['order_by'] ?? [], $limit);
    }

    /**
     * ÚNICO punto del motor donde se crea SQL "crudo". Recibe solo nombres de
     * columna ya validados contra el esquema real y escapados por el grammar
     * (más funciones fijas como count/sum); nunca texto escrito por el modelo.
     *
     * @return Expression<literal-string>
     */
    private function expression(string $sql): Expression
    {
        // @phpstan-ignore-next-line argument.type
        return new Expression($sql);
    }

    private function table(mixed $name): TableDefinition
    {
        $indexable = $this->catalog->indexable();
        $name = is_string($name) ? $name : '';

        if (! isset($indexable[$name])) {
            throw new InvalidToolCallException(
                "La tabla «{$name}» no está disponible. Tablas disponibles: ".implode(', ', array_keys($indexable)).'.',
            );
        }

        return $indexable[$name];
    }

    private function column(TableDefinition $table, mixed $name): ColumnDefinition
    {
        $column = is_string($name) ? $table->column($name) : null;

        if ($column === null || ! $this->policy->isQueryable($column)) {
            $available = array_map(
                static fn (ColumnDefinition $c): string => $c->name,
                array_values(array_filter($table->columns, fn (ColumnDefinition $c): bool => $this->policy->isQueryable($c))),
            );

            throw new InvalidToolCallException(
                'La columna «'.(is_string($name) ? $name : '?')."» no existe en {$table->name} o no se puede consultar. Columnas disponibles: ".implode(', ', $available).'.',
            );
        }

        return $column;
    }

    private function applyFilters(Builder $query, TableDefinition $table, mixed $filters): void
    {
        if (! is_array($filters)) {
            return;
        }

        foreach (array_slice($filters, 0, 12) as $filter) {
            if (! is_array($filter)) {
                continue;
            }

            $column = $this->column($table, $filter['column'] ?? null);
            $expression = 't0.'.$column->name;
            $operator = strtolower(trim((string) ($filter['op'] ?? '=')));

            if (isset(self::COMPARE[$operator])) {
                $query->where($expression, self::COMPARE[$operator], $this->scalar($filter['value'] ?? null, $operator));

                continue;
            }

            match ($operator) {
                'contiene', 'like' => $query->where(
                    $expression,
                    'like',
                    '%'.addcslashes($this->scalar($filter['value'] ?? null, $operator), '%_\\').'%',
                ),
                'in', 'en' => $query->whereIn($expression, $this->list($filter['values'] ?? null, $operator, 100)),
                'entre', 'between' => $query->whereBetween($expression, $this->between($filter['values'] ?? null)),
                'es_nulo', 'is_null' => $query->whereNull($expression),
                'no_es_nulo', 'not_null' => $query->whereNotNull($expression),
                'etiqueta_contiene', 'label_like' => $this->labelFilter($query, $table, $column, $this->scalar($filter['value'] ?? null, $operator)),
                default => throw new InvalidToolCallException(
                    "Operador «{$operator}» no válido. Usa: =, !=, >, >=, <, <=, contiene, in, entre, es_nulo, no_es_nulo, etiqueta_contiene.",
                ),
            };
        }
    }

    /**
     * Filtra una llave foránea por el NOMBRE de lo que referencia (p. ej. el
     * empleado "Ana"), sin que el modelo tenga que conocer los ids.
     */
    private function labelFilter(Builder $query, TableDefinition $table, ColumnDefinition $column, string $text): void
    {
        $foreign = $table->foreignKeys[$column->name] ?? null;
        if ($foreign === null) {
            throw new InvalidToolCallException("«etiqueta_contiene» solo sirve con columnas que apuntan a otra tabla (como employee_id o lead_stage_id); «{$column->name}» no lo hace.");
        }

        $ids = [];
        foreach ($this->labels->labels($foreign['table']) as $id => $label) {
            if (mb_stripos($label, $text) !== false) {
                $ids[] = $id;
            }
        }

        $ids === []
            ? $query->whereRaw('1 = 0')
            : $query->whereIn('t0.'.$column->name, array_slice($ids, 0, 500));
    }

    private function scalar(mixed $value, string $operator): string
    {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (! is_scalar($value) || (string) $value === '') {
            throw new InvalidToolCallException("El operador «{$operator}» necesita un «value».");
        }

        return (string) $value;
    }

    /**
     * @return list<string>
     */
    private function list(mixed $values, string $operator, int $max): array
    {
        if (! is_array($values) || $values === []) {
            throw new InvalidToolCallException("El operador «{$operator}» necesita «values» con una lista.");
        }

        return array_map(fn (mixed $v): string => $this->scalar($v, $operator), array_slice(array_values($values), 0, $max));
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function between(mixed $values): array
    {
        $list = $this->list($values, 'entre', 2);
        if (count($list) !== 2) {
            throw new InvalidToolCallException('«entre» necesita exactamente dos valores en «values»: [desde, hasta].');
        }

        return [$list[0], $list[1]];
    }

    /**
     * @return list<array{expr: string, alias: string, column: ColumnDefinition, bucketed: bool}>
     */
    private function parseGroupBy(TableDefinition $table, mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        $grammar = DB::getQueryGrammar();
        $parsed = [];

        foreach (array_slice($items, 0, 3) as $item) {
            $name = is_array($item) ? ($item['column'] ?? null) : $item;
            $column = $this->column($table, $name);
            $bucket = is_array($item) ? strtolower((string) ($item['bucket'] ?? '')) : '';

            if ($bucket !== '') {
                if (! isset(self::BUCKETS[$bucket]) || ! $column->isDate()) {
                    throw new InvalidToolCallException('«bucket» solo admite day, month o year y solo con columnas de fecha.');
                }

                $parsed[] = [
                    'expr' => "DATE_FORMAT({$grammar->wrap('t0.'.$column->name)}, '".self::BUCKETS[$bucket]."')",
                    'alias' => $column->name.'_'.$bucket,
                    'column' => $column,
                    'bucketed' => true,
                ];

                continue;
            }

            $parsed[] = ['expr' => $grammar->wrap('t0.'.$column->name), 'alias' => $column->name, 'column' => $column, 'bucketed' => false];
        }

        return $parsed;
    }

    /**
     * @return list<array{sql: string, alias: string}>
     */
    private function parseAggregates(TableDefinition $table, mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        $grammar = DB::getQueryGrammar();
        $parsed = [];

        foreach (array_slice($items, 0, 6) as $item) {
            if (! is_array($item)) {
                continue;
            }

            $function = strtolower((string) ($item['fn'] ?? ''));
            if (! in_array($function, self::AGGREGATES, true)) {
                throw new InvalidToolCallException('Función de agregación no válida. Usa: '.implode(', ', self::AGGREGATES).'.');
            }

            $columnName = (string) ($item['column'] ?? '*');

            if ($columnName === '*') {
                if ($function !== 'count') {
                    throw new InvalidToolCallException("«{$function}» necesita una columna; solo count admite «*».");
                }
                $sql = 'count(*)';
                $default = 'total';
            } else {
                $column = $this->column($table, $columnName);
                if (in_array($function, ['sum', 'avg'], true) && ! $column->isNumber()) {
                    throw new InvalidToolCallException("«{$function}» solo se puede usar con columnas numéricas; «{$columnName}» no lo es.");
                }
                $wrapped = $grammar->wrap('t0.'.$column->name);
                $sql = $function === 'count_distinct' ? "count(distinct {$wrapped})" : "{$function}({$wrapped})";
                $default = $function.'_'.$column->name;
            }

            $alias = preg_replace('/[^a-z0-9_]/', '', strtolower((string) ($item['alias'] ?? ''))) ?: $default;
            $parsed[] = ['sql' => $sql, 'alias' => mb_substr($alias, 0, 40)];
        }

        return $parsed;
    }

    /**
     * @param  list<array{expr: string, alias: string, column: ColumnDefinition, bucketed: bool}>  $groupBy
     * @param  list<array{sql: string, alias: string}>  $aggregates
     * @return array{table: string, columns: list<string>, rows: list<list<mixed>>, truncated: bool}
     */
    private function aggregated(Builder $query, TableDefinition $table, array $groupBy, array $aggregates, mixed $orderBy, int $limit): array
    {
        $grammar = DB::getQueryGrammar();

        if ($aggregates === []) {
            $aggregates = [['sql' => 'count(*)', 'alias' => 'total']];
        }

        $query->select([]);
        $aliases = [];

        foreach ($groupBy as $group) {
            $query->addSelect($this->expression($group['expr'].' as '.$grammar->wrap($group['alias'])));
            $query->groupBy($this->expression($grammar->wrap($group['alias'])));
            $aliases[] = $group['alias'];
        }
        foreach ($aggregates as $aggregate) {
            $query->addSelect($this->expression($aggregate['sql'].' as '.$grammar->wrap($aggregate['alias'])));
            $aliases[] = $aggregate['alias'];
        }

        $ordered = false;
        if (is_array($orderBy)) {
            foreach (array_slice($orderBy, 0, 3) as $order) {
                $name = is_array($order) ? (string) ($order['column'] ?? '') : (string) $order;
                if (! in_array($name, $aliases, true)) {
                    throw new InvalidToolCallException('«order_by» debe usar una columna del resultado: '.implode(', ', $aliases).'.');
                }
                $direction = is_array($order) && strtolower((string) ($order['direction'] ?? '')) === 'desc' ? 'desc' : 'asc';
                $query->orderBy($this->expression($grammar->wrap($name)), $direction);
                $ordered = true;
            }
        }

        if (! $ordered) {
            // Por defecto: las fechas en orden cronológico y, si no, lo más grande primero.
            $firstGroup = $groupBy[0] ?? null;
            if ($firstGroup !== null && ($firstGroup['bucketed'] || $firstGroup['column']->isDate())) {
                $query->orderBy($this->expression($grammar->wrap($firstGroup['alias'])), 'asc');
            } else {
                $query->orderBy($this->expression($grammar->wrap($aggregates[0]['alias'])), 'desc');
            }
        }

        $rows = $query->limit($limit + 1)->get()->map(static fn (object $row): array => (array) $row)->all();
        $truncated = count($rows) > $limit;

        $output = [];
        foreach (array_slice($rows, 0, $limit) as $row) {
            $line = [];
            foreach ($groupBy as $group) {
                $line[] = $this->display($table, $group['column'], $row[$group['alias']] ?? null, ! $group['bucketed']);
            }
            foreach ($aggregates as $aggregate) {
                $line[] = $this->number($row[$aggregate['alias']] ?? null);
            }
            $output[] = $line;
        }

        return ['table' => $table->name, 'columns' => $aliases, 'rows' => $output, 'truncated' => $truncated];
    }

    /**
     * @return array{table: string, columns: list<string>, rows: list<list<mixed>>, truncated: bool}
     */
    private function listing(Builder $query, TableDefinition $table, mixed $requested, mixed $orderBy, int $limit): array
    {
        $grammar = DB::getQueryGrammar();

        $columns = [];
        if (is_array($requested) && $requested !== []) {
            foreach (array_slice($requested, 0, 15) as $name) {
                $columns[] = $this->column($table, $name);
            }
        } else {
            foreach ($table->columns as $column) {
                if (! $column->isPrimary && $this->policy->isQueryable($column) && count($columns) < 8) {
                    $columns[] = $column;
                }
            }
        }

        $query->select([]);
        foreach ($columns as $column) {
            $query->addSelect($this->expression($grammar->wrap('t0.'.$column->name).' as '.$grammar->wrap($column->name)));
        }

        $ordered = false;
        if (is_array($orderBy)) {
            foreach (array_slice($orderBy, 0, 3) as $order) {
                $column = $this->column($table, is_array($order) ? ($order['column'] ?? null) : $order);
                $direction = is_array($order) && strtolower((string) ($order['direction'] ?? '')) === 'desc' ? 'desc' : 'asc';
                $query->orderBy('t0.'.$column->name, $direction);
                $ordered = true;
            }
        }
        if (! $ordered && $table->primaryKey !== null) {
            $query->orderBy('t0.'.$table->primaryKey);
        }

        $rows = $query->limit($limit + 1)->get()->map(static fn (object $row): array => (array) $row)->all();
        $truncated = count($rows) > $limit;

        $output = [];
        foreach (array_slice($rows, 0, $limit) as $row) {
            $line = [];
            foreach ($columns as $column) {
                $line[] = $this->display($table, $column, $row[$column->name] ?? null, true);
            }
            $output[] = $line;
        }

        return [
            'table' => $table->name,
            'columns' => array_map(static fn (ColumnDefinition $c): string => $c->name, $columns),
            'rows' => $output,
            'truncated' => $truncated,
        ];
    }

    /** Valor legible: nombres en lugar de ids de llaves foráneas y sí/no en booleanos. */
    private function display(TableDefinition $table, ColumnDefinition $column, mixed $value, bool $resolveLabels): mixed
    {
        if ($value === null) {
            return null;
        }

        if ($column->isBoolean()) {
            return (string) $value === '1' ? 'sí' : 'no';
        }

        $foreign = $table->foreignKeys[$column->name] ?? null;
        if ($resolveLabels && $foreign !== null) {
            return $this->labels->label($foreign['table'], (string) $value) ?? (string) $value;
        }

        return $this->number($value);
    }

    private function number(mixed $value): mixed
    {
        if (is_string($value) && preg_match('/^-?\d+\.\d+$/', $value) === 1) {
            return rtrim(rtrim($value, '0'), '.');
        }

        return $value;
    }
}
