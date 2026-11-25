<?php

namespace App\Console\Commands;

use App\Services\Ai\Exceptions\AiUnavailableException;
use App\Services\Ai\NexusAssistant;
use Illuminate\Console\Command;

class NexusAiAsk extends Command
{
    protected $signature = 'nexus:ai-ask
        {company : Id de la empresa cuyos datos se consultan}
        {question* : La pregunta}';

    protected $description = 'Hace una pregunta a Nexus con los datos de una empresa (útil para probar el asistente).';

    public function handle(NexusAssistant $assistant): int
    {
        $company = (string) $this->argument('company');
        if (! ctype_digit($company)) {
            $this->components->error('La empresa debe ser un id numérico.');

            return self::INVALID;
        }

        $question = implode(' ', (array) $this->argument('question'));

        try {
            $result = $assistant->ask((int) $company, $question);
        } catch (AiUnavailableException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->line($result->answer);

        if ($result->sources !== []) {
            $this->newLine();
            $this->components->twoColumnDetail('Fuentes consultadas', (string) count($result->sources));
            foreach ($result->sources as $source) {
                $this->line("  · {$source['table']} #{$source['id']} ({$source['kind']}) similitud {$source['score']}");
            }
        } elseif (! $result->grounded) {
            $this->newLine();
            $this->components->info('Sin datos suficientes de esa empresa: no se consultó al modelo.');
        }

        return self::SUCCESS;
    }
}
