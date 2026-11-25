<?php

namespace App\Services\Ai\Sync;

/**
 * Estado mutable de la carga de una tabla: lo que ya existe, lo pendiente de
 * vectorizar y los contadores.
 */
final class TableSyncState
{
    /**
     * Fragmentos que ya existen: empresa → id de origen → tipo → hash e ids.
     *
     * @var array<int, array<string, array<string, array{hash: string, ids: list<int>}>>>
     */
    public array $existing = [];

    /**
     * Registros pendientes de generar embeddings.
     *
     * @var list<array{company: int, source_id: string, kind: string, hash: string, chunks: list<string>, is_update: bool}>
     */
    public array $pending = [];

    public int $pendingTexts = 0;

    public int $rows = 0;

    public int $created = 0;

    public int $updated = 0;

    public int $unchanged = 0;

    public int $deleted = 0;

    /**
     * @return array{rows: int, created: int, updated: int, unchanged: int, deleted: int}
     */
    public function summary(): array
    {
        return [
            'rows' => $this->rows,
            'created' => $this->created,
            'updated' => $this->updated,
            'unchanged' => $this->unchanged,
            'deleted' => $this->deleted,
        ];
    }
}
