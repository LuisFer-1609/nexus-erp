<?php

namespace App\Services\Ai\Documents;

use App\Services\Ai\Schema\TableDefinition;
use Generator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Recorre las filas de cualquier tabla indexable, página por página, marcando
 * cada fila con su empresa (`__company_id`). Si la empresa no está en la
 * propia tabla, llega a ella siguiendo las llaves foráneas con JOIN.
 */
final class TenantRowReader
{
    public function __construct(private readonly int $pageSize = 500) {}

    /**
     * @return Generator<int, array<string, mixed>>
     */
    public function rows(TableDefinition $table, ?int $companyId = null): Generator
    {
        $primary = $table->primaryKey;
        if ($primary === null || $table->tenantPath === null) {
            return;
        }

        $last = null;

        do {
            $query = $this->baseQuery($table, $companyId);
            if ($last !== null) {
                $query->where('t0.'.$primary, '>', $last);
            }

            $rows = $query->orderBy('t0.'.$primary)->limit($this->pageSize)->get();

            foreach ($rows as $row) {
                $values = (array) $row;
                $last = $values[$primary];

                yield $values;
            }
        } while ($rows->count() === $this->pageSize);
    }

    /**
     * Consulta base de una tabla con el filtro de empresa ya aplicado (alias t0 =
     * la tabla; el resto de alias son los JOIN hacia la empresa). Toda consulta del
     * asistente parte de aquí, así que no existe forma de leer datos de otra empresa.
     */
    public function baseQuery(TableDefinition $table, ?int $companyId): Builder
    {
        $path = $table->tenantPath;
        assert($path !== null);

        $query = DB::table($table->name.' as t0')->select('t0.*');

        foreach ($path->steps as $index => $step) {
            $alias = 't'.($index + 1);
            $query->join(
                $step['ref_table'].' as '.$alias,
                $alias.'.'.$step['ref_column'],
                '=',
                't'.$index.'.'.$step['from_column'],
            );
        }

        $tenant = 't'.$path->aliasIndex().'.'.$path->column;
        $query->addSelect($tenant.' as __company_id')->whereNotNull($tenant);

        if ($table->hasColumn('deleted_at')) {
            $query->whereNull('t0.deleted_at');
        }

        if ($companyId !== null) {
            $query->where($tenant, $companyId);
        }

        return $query;
    }
}
