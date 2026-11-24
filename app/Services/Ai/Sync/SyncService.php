<?php

namespace App\Services\Ai\Sync;

use App\Models\AiChunk;
use App\Models\AiSyncRun;
use App\Services\Ai\Contracts\EmbeddingClient;
use App\Services\Ai\Documents\Chunker;
use App\Services\Ai\Documents\ColumnPolicy;
use App\Services\Ai\Documents\LabelResolver;
use App\Services\Ai\Documents\RowDocument;
use App\Services\Ai\Documents\RowDocumentBuilder;
use App\Services\Ai\Documents\TableStats;
use App\Services\Ai\Documents\TenantRowReader;
use App\Services\Ai\Exceptions\AiUnavailableException;
use App\Services\Ai\Exceptions\SyncAlreadyRunningException;
use App\Services\Ai\Schema\SchemaCatalog;
use App\Services\Ai\Schema\TableDefinition;
use App\Services\Ai\VectorStore;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

/**
 * Carga los datos de la plataforma al almacén vectorial.
 *
 * Es autónoma: descubre el esquema en cada ejecución, así que tablas y columnas
 * nuevas se incorporan sin tocar código. Es incremental: compara un hash del
 * contenido de cada registro y solo vuelve a generar embeddings de lo que
 * cambió; lo que ya no existe se elimina; y deja registrado qué cambió en la
 * estructura de la base de datos desde la carga anterior.
 */
class SyncService
{
    public function __construct(
        private readonly EmbeddingClient $embeddings,
        private readonly VectorStore $store,
    ) {}

    /**
     * @throws SyncAlreadyRunningException
     */
    public function run(string $trigger = 'manual', ?int $companyId = null, ?string $onlyTable = null): AiSyncRun
    {
        $lock = Cache::lock('nexus_ai:sync', 3600);
        if (! $lock->get()) {
            throw new SyncAlreadyRunningException('Ya hay una carga en curso.');
        }

        $run = AiSyncRun::query()->create([
            AiSyncRun::STATUS => AiSyncRun::STATUS_RUNNING,
            AiSyncRun::TRIGGER => $trigger,
            AiSyncRun::COMPANY_ID => $companyId,
            AiSyncRun::SCOPE_TABLE => $onlyTable,
            AiSyncRun::TABLES_INDEXED => 0,
            AiSyncRun::ROWS_SCANNED => 0,
            AiSyncRun::CHUNKS_CREATED => 0,
            AiSyncRun::CHUNKS_UPDATED => 0,
            AiSyncRun::CHUNKS_UNCHANGED => 0,
            AiSyncRun::CHUNKS_DELETED => 0,
            AiSyncRun::STARTED_AT => now(),
        ]);

        try {
            $this->execute($run, $companyId, $onlyTable);
        } catch (Throwable $e) {
            if (! $e instanceof AiUnavailableException && ! $e instanceof InvalidArgumentException) {
                report($e);
            }
            $run->status = AiSyncRun::STATUS_FAILED;
            $run->error = mb_substr($e->getMessage(), 0, 1000);
        } finally {
            $run->finished_at = now();
            $run->save();
            $lock->release();
        }

        return $run;
    }

    private function execute(AiSyncRun $run, ?int $companyFilter, ?string $onlyTable): void
    {
        if (! $this->embeddings->isConfigured()) {
            throw new AiUnavailableException('No hay llaves de Gemini configuradas. Agrega GEMINI_API_KEYS en el .env.');
        }

        $catalog = SchemaCatalog::build();
        $indexable = $catalog->indexable();

        if ($onlyTable !== null) {
            if (! isset($indexable[$onlyTable])) {
                $reason = $catalog->table($onlyTable)->reason ?? 'la tabla no existe';
                throw new InvalidArgumentException("La tabla {$onlyTable} no se puede indexar: {$reason}.");
            }
            $targets = [$onlyTable => $indexable[$onlyTable]];
        } else {
            $targets = $indexable;
        }

        $snapshot = $catalog->snapshot();
        $details = [
            'schema' => ['snapshot' => $snapshot],
            'schema_changes' => $this->diffSchema($this->previousSnapshot(), $snapshot),
            'tables' => [],
            'errors' => [],
        ];
        $run->schema_fingerprint = $catalog->fingerprint();

        $companyIds = $this->companyIds($catalog, $companyFilter);

        $policy = new ColumnPolicy;
        $labels = new LabelResolver($catalog, $policy);
        $builder = new RowDocumentBuilder($labels, $policy);
        $chunker = Chunker::fromConfig();

        $abort = false;
        foreach ($targets as $name => $table) {
            try {
                $state = $this->syncTable($table, $companyIds, $companyFilter, $labels, $builder, $policy, $chunker);

                $details['tables'][$name] = $state->summary();
                $run->tables_indexed++;
                $run->rows_scanned += $state->rows;
                $run->chunks_created += $state->created;
                $run->chunks_updated += $state->updated;
                $run->chunks_unchanged += $state->unchanged;
                $run->chunks_deleted += $state->deleted;
            } catch (AiUnavailableException $e) {
                // Sin proveedor de IA no tiene caso seguir con las demás tablas.
                $details['errors'][] = ['table' => $name, 'message' => mb_substr($e->getMessage(), 0, 300)];
                $abort = true;
            } catch (Throwable $e) {
                report($e);
                $details['errors'][] = ['table' => $name, 'message' => mb_substr($e->getMessage(), 0, 300)];
            }

            $run->details = $details;
            $run->save();

            if ($abort) {
                break;
            }
        }

        // Tablas que dejaron de ser indexables (o ya no existen): se limpian sus fragmentos.
        if (! $abort && $onlyTable === null) {
            $run->chunks_deleted += $this->store->deleteTablesNotIn(array_keys($indexable));
        }

        $run->details = $details;

        if ($details['errors'] === []) {
            $run->status = AiSyncRun::STATUS_COMPLETED;
        } else {
            $run->status = $run->tables_indexed > 0
                ? AiSyncRun::STATUS_COMPLETED_WITH_ERRORS
                : AiSyncRun::STATUS_FAILED;
            $run->error = $details['errors'][0]['message'];
        }
    }

    /**
     * @param  list<int>  $companyIds
     */
    private function syncTable(
        TableDefinition $table,
        array $companyIds,
        ?int $companyFilter,
        LabelResolver $labels,
        RowDocumentBuilder $builder,
        ColumnPolicy $policy,
        Chunker $chunker,
    ): TableSyncState {
        $state = new TableSyncState;
        $state->existing = $this->existingChunks($table->name, $companyFilter);

        $reader = new TenantRowReader((int) config('nexus_ai.sync.row_page_size'));
        $maxDistinct = (int) config('nexus_ai.sync.max_distinct_values');
        $batchSize = max(1, (int) config('nexus_ai.sync.embedding_batch_size'));
        $primary = (string) $table->primaryKey;

        /** @var array<int, TableStats> $stats */
        $stats = [];
        /** @var array<int, array<string, true>> $seen */
        $seen = [];

        foreach ($reader->rows($table, $companyFilter) as $row) {
            $company = (int) $row['__company_id'];
            $sourceId = (string) $row[$primary];

            $state->rows++;
            $stats[$company] ??= new TableStats($table, $policy, $maxDistinct);
            $stats[$company]->add($row);
            $seen[$company][$sourceId] = true;

            $this->queue($state, $chunker, $company, $sourceId, AiChunk::KIND_ROW, $builder->build($table, $row));

            if ($state->pendingTexts >= $batchSize) {
                $this->flush($state, $table->name);
            }
        }

        // Un resumen por empresa y tabla (también cuando no hay registros: así "0" es un dato real).
        foreach ($companyIds as $company) {
            $summary = ($stats[$company] ?? new TableStats($table, $policy, $maxDistinct))->render($labels);
            $this->queue($state, $chunker, $company, AiChunk::SUMMARY_SOURCE_ID, AiChunk::KIND_SUMMARY, $summary);
        }

        $this->flush($state, $table->name);

        // Registros que ya no existen (o cuyas empresas ya no están): se eliminan sus fragmentos.
        /** @var list<int> $obsolete */
        $obsolete = [];
        foreach ($state->existing as $company => $sources) {
            foreach ($sources as $sourceId => $kinds) {
                foreach ($kinds as $kind => $info) {
                    $gone = $kind === AiChunk::KIND_ROW
                        ? ! isset($seen[$company][$sourceId])
                        : ! in_array($company, $companyIds, true);

                    if ($gone) {
                        array_push($obsolete, ...$info['ids']);
                    }
                }
            }
        }
        $state->deleted += $this->store->deleteIds($obsolete);

        return $state;
    }

    /**
     * Pone en cola un documento solo si es nuevo o si su contenido cambió.
     */
    private function queue(TableSyncState $state, Chunker $chunker, int $company, string $sourceId, string $kind, RowDocument $document): void
    {
        $hash = $this->hashOf($chunker, $document);
        $current = $state->existing[$company][$sourceId][$kind] ?? null;

        if ($current !== null && $current['hash'] === $hash) {
            $state->unchanged += count($current['ids']);

            return;
        }

        $chunks = $chunker->split($document);

        $state->pending[] = [
            'company' => $company,
            'source_id' => $sourceId,
            'kind' => $kind,
            'hash' => $hash,
            'chunks' => $chunks,
            'is_update' => $current !== null,
        ];
        $state->pendingTexts += count($chunks);
    }

    /**
     * Genera los embeddings pendientes y los guarda.
     */
    private function flush(TableSyncState $state, string $table): void
    {
        if ($state->pending === []) {
            return;
        }

        $texts = [];
        foreach ($state->pending as $item) {
            foreach ($item['chunks'] as $chunk) {
                $texts[] = $chunk;
            }
        }

        $vectors = $this->embeddings->embedDocuments($texts);
        if (count($vectors) !== count($texts)) {
            throw new AiUnavailableException('El proveedor devolvió una cantidad inesperada de embeddings.');
        }

        $offset = 0;
        foreach ($state->pending as $item) {
            $items = [];
            foreach ($item['chunks'] as $index => $content) {
                $items[] = ['content' => $content, 'vector' => $vectors[$offset + $index]];
            }
            $offset += count($item['chunks']);

            $this->store->replace(
                $item['company'],
                $table,
                $item['source_id'],
                $item['kind'],
                $item['hash'],
                $this->embeddings->model(),
                $items,
            );

            if ($item['is_update']) {
                $state->updated += count($items);
            } else {
                $state->created += count($items);
            }
        }

        $state->pending = [];
        $state->pendingTexts = 0;
    }

    /**
     * @return array<int, array<string, array<string, array{hash: string, ids: list<int>}>>>
     */
    private function existingChunks(string $table, ?int $companyFilter): array
    {
        $query = AiChunk::query()
            ->where(AiChunk::SOURCE_TABLE, $table)
            ->select([AiChunk::ID, AiChunk::COMPANY_ID, AiChunk::SOURCE_ID, AiChunk::KIND, AiChunk::CONTENT_HASH]);

        if ($companyFilter !== null) {
            $query->where(AiChunk::COMPANY_ID, $companyFilter);
        }

        $existing = [];
        foreach ($query->lazyById(1000) as $chunk) {
            $company = (int) $chunk->company_id;
            $entry = $existing[$company][$chunk->source_id][$chunk->kind] ?? ['hash' => $chunk->content_hash, 'ids' => []];
            $entry['ids'][] = (int) $chunk->id;
            $existing[$company][$chunk->source_id][$chunk->kind] = $entry;
        }

        return $existing;
    }

    /**
     * Empresas que existen en este momento (o solo la indicada).
     *
     * @return list<int>
     */
    private function companyIds(SchemaCatalog $catalog, ?int $companyFilter): array
    {
        $tenantName = (string) config('nexus_ai.sync.tenant_table');
        $tenant = $catalog->table($tenantName);
        if ($tenant === null || $tenant->primaryKey === null) {
            return [];
        }

        $ids = DB::table($tenantName)->orderBy($tenant->primaryKey)->pluck($tenant->primaryKey)
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        if ($companyFilter === null) {
            return array_values($ids);
        }

        if (! in_array($companyFilter, $ids, true)) {
            throw new InvalidArgumentException("La empresa {$companyFilter} no existe.");
        }

        return [$companyFilter];
    }

    private function hashOf(Chunker $chunker, RowDocument $document): string
    {
        return hash('sha256', implode('|', [
            $this->embeddings->model(),
            $this->embeddings->dimensions(),
            $chunker->maxChars(),
            $document->text(),
        ]));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function previousSnapshot(): ?array
    {
        $previous = AiSyncRun::query()
            ->whereIn(AiSyncRun::STATUS, [AiSyncRun::STATUS_COMPLETED, AiSyncRun::STATUS_COMPLETED_WITH_ERRORS])
            ->whereNotNull(AiSyncRun::DETAILS)
            ->orderByDesc(AiSyncRun::ID)
            ->first();

        $snapshot = $previous?->details['schema']['snapshot'] ?? null;

        return is_array($snapshot) ? $snapshot : null;
    }

    /**
     * Compara el esquema actual con el de la carga anterior.
     *
     * @param  array<string, mixed>|null  $old
     * @param  array<string, array{columns: list<string>, indexable: bool, reason: string|null, tenant: string|null}>  $new
     * @return array{first_run: bool, has_changes: bool, added_tables: list<string>, removed_tables: list<string>, changed_tables: array<string, array{added: list<string>, removed: list<string>}>, newly_indexable: list<string>}
     */
    private function diffSchema(?array $old, array $new): array
    {
        if ($old === null) {
            return [
                'first_run' => true,
                'has_changes' => false,
                'added_tables' => [],
                'removed_tables' => [],
                'changed_tables' => [],
                'newly_indexable' => [],
            ];
        }

        $added = array_values(array_diff(array_keys($new), array_keys($old)));
        $removed = array_values(array_diff(array_keys($old), array_keys($new)));

        $changed = [];
        $newlyIndexable = [];
        foreach ($new as $table => $definition) {
            if (! isset($old[$table]) || ! is_array($old[$table])) {
                continue;
            }

            $oldColumns = is_array($old[$table]['columns'] ?? null) ? array_map('strval', $old[$table]['columns']) : [];
            $addedColumns = array_values(array_diff($definition['columns'], $oldColumns));
            $removedColumns = array_values(array_diff($oldColumns, $definition['columns']));

            if ($addedColumns !== [] || $removedColumns !== []) {
                $changed[$table] = ['added' => $addedColumns, 'removed' => $removedColumns];
            }

            if ($definition['indexable'] && ! ($old[$table]['indexable'] ?? false)) {
                $newlyIndexable[] = $table;
            }
        }

        return [
            'first_run' => false,
            'has_changes' => $added !== [] || $removed !== [] || $changed !== [] || $newlyIndexable !== [],
            'added_tables' => $added,
            'removed_tables' => $removed,
            'changed_tables' => $changed,
            'newly_indexable' => $newlyIndexable,
        ];
    }
}
