<?php

namespace App\Console\Commands;

use App\Models\AiSyncRun;
use App\Services\Ai\Contracts\EmbeddingClient;
use App\Services\Ai\Exceptions\SyncAlreadyRunningException;
use App\Services\Ai\Sync\SyncService;
use Illuminate\Console\Command;

class NexusAiSync extends Command
{
    protected $signature = 'nexus:ai-sync
        {--company= : Cargar solo los datos de esta empresa (id)}
        {--table= : Cargar solo esta tabla}
        {--scheduled : Ejecución programada: si no hay llaves configuradas, se omite sin error}';

    protected $description = 'Carga y actualiza los datos que Nexus IA consulta (incremental: solo lo que cambió).';

    public function handle(SyncService $sync, EmbeddingClient $embeddings): int
    {
        $scheduled = (bool) $this->option('scheduled');

        if ($scheduled && ! $embeddings->isConfigured()) {
            $this->components->warn('Carga omitida: no hay llaves de Gemini configuradas (GEMINI_API_KEYS).');

            return self::SUCCESS;
        }

        $company = $this->option('company');
        if ($company !== null && ! ctype_digit((string) $company)) {
            $this->components->error('--company debe ser el id numérico de una empresa.');

            return self::INVALID;
        }

        $table = $this->option('table');

        try {
            $run = $sync->run(
                $scheduled ? 'scheduled' : 'manual',
                $company !== null ? (int) $company : null,
                is_string($table) && $table !== '' ? $table : null,
            );
        } catch (SyncAlreadyRunningException) {
            $this->components->warn('Ya hay una carga en curso; se omite esta ejecución.');

            return self::SUCCESS;
        }

        $this->report($run);

        return $run->status === AiSyncRun::STATUS_FAILED ? self::FAILURE : self::SUCCESS;
    }

    private function report(AiSyncRun $run): void
    {
        $this->components->twoColumnDetail('Estado', $run->status);
        $this->components->twoColumnDetail('Tablas cargadas', (string) $run->tables_indexed);
        $this->components->twoColumnDetail('Registros leídos', (string) $run->rows_scanned);
        $this->components->twoColumnDetail('Fragmentos nuevos', (string) $run->chunks_created);
        $this->components->twoColumnDetail('Fragmentos actualizados', (string) $run->chunks_updated);
        $this->components->twoColumnDetail('Fragmentos sin cambios', (string) $run->chunks_unchanged);
        $this->components->twoColumnDetail('Fragmentos eliminados', (string) $run->chunks_deleted);

        $changes = $run->details['schema_changes'] ?? null;
        if (is_array($changes) && ($changes['has_changes'] ?? false)) {
            $this->components->info('Cambios detectados en la estructura de la base de datos desde la carga anterior:');
            foreach ((array) ($changes['added_tables'] ?? []) as $table) {
                $this->line("  + tabla nueva: {$table}");
            }
            foreach ((array) ($changes['removed_tables'] ?? []) as $table) {
                $this->line("  - tabla eliminada: {$table}");
            }
            foreach ((array) ($changes['changed_tables'] ?? []) as $table => $diff) {
                $this->line("  ~ {$table}: +".count((array) ($diff['added'] ?? [])).' / -'.count((array) ($diff['removed'] ?? [])).' columnas');
            }
        }

        foreach ((array) ($run->details['errors'] ?? []) as $error) {
            $this->components->error(($error['table'] ?? '').': '.($error['message'] ?? ''));
        }

        if ($run->status === AiSyncRun::STATUS_FAILED && $run->error !== null) {
            $this->components->error($run->error);
        }
    }
}
