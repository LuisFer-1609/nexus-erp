<?php

namespace App\Jobs;

use App\Services\Ai\Exceptions\SyncAlreadyRunningException;
use App\Services\Ai\Sync\SyncService;
use Illuminate\Foundation\Bus\Dispatchable;

/**
 * Recarga los datos de Nexus IA. Se despacha desde la vista de estado.
 */
class SyncAiData
{
    use Dispatchable;

    public function __construct(
        public readonly string $trigger = 'manual',
        public readonly ?int $companyId = null,
    ) {}

    public function handle(SyncService $sync): void
    {
        try {
            $sync->run($this->trigger, $this->companyId);
        } catch (SyncAlreadyRunningException) {
            // Ya hay una carga en curso: no hace falta otra.
        }
    }
}
