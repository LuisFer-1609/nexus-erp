<?php

namespace App\Console\Commands;

use App\Models\AiChunk;
use App\Models\AiSyncRun;
use App\Services\Ai\GeminiKeyPool;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class NexusAiStatus extends Command
{
    protected $signature = 'nexus:ai-status';

    protected $description = 'Muestra el estado de la carga de datos de Nexus IA.';

    public function handle(GeminiKeyPool $pool): int
    {
        $version = DB::selectOne('select version() as v');

        $this->components->twoColumnDetail('Motor de base de datos', (string) ($version->v ?? 'desconocido'));
        $this->components->twoColumnDetail('Proveedor de embeddings', (string) config('nexus_ai.driver'));
        $this->components->twoColumnDetail('Modelo de respuestas', (string) config('nexus_ai.gemini.chat_model'));
        $this->components->twoColumnDetail('Modelo de embeddings', (string) config('nexus_ai.gemini.embedding_model'));
        $this->components->twoColumnDetail('Llaves en el pool', $pool->count().' ('.count($pool->available()).' disponibles)');

        $last = AiSyncRun::query()->orderByDesc(AiSyncRun::ID)->first();
        $this->components->twoColumnDetail(
            'Última carga',
            $last === null ? 'nunca' : $last->status.' · '.($last->finished_at?->diffForHumans() ?? 'en curso'),
        );

        $rows = AiChunk::query()
            ->selectRaw('company_id, count(*) as chunks, max(updated_at) as last_update')
            ->groupBy(AiChunk::COMPANY_ID)
            ->orderBy(AiChunk::COMPANY_ID)
            ->get();

        if ($rows->isEmpty()) {
            $this->components->warn('Aún no hay datos cargados. Ejecuta: php artisan nexus:ai-sync');

            return self::SUCCESS;
        }

        $this->table(
            ['Empresa', 'Fragmentos', 'Última actualización'],
            $rows->map(static fn (AiChunk $row): array => [
                $row->company_id,
                $row->getAttribute('chunks'),
                $row->getAttribute('last_update'),
            ])->all(),
        );

        return self::SUCCESS;
    }
}
