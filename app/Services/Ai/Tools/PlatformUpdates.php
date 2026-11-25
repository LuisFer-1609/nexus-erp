<?php

namespace App\Services\Ai\Tools;

use Illuminate\Support\Facades\Cache;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Novedades de la plataforma, para responder "¿qué hay de nuevo?" sin que nadie
 * tenga que escribir un aviso. Usa CHANGELOG.md si existe y, si no, el historial
 * de cambios del repositorio (solo mejoras y correcciones).
 */
class PlatformUpdates
{
    /** @var array<string, string> */
    private const TYPES = [
        'feat' => 'Nueva función',
        'fix' => 'Corrección',
        'perf' => 'Mejora de rendimiento',
    ];

    /**
     * @return list<array{fecha: string, tipo: string, cambio: string}>
     */
    public function latest(int $limit = 12): array
    {
        $limit = min(30, max(1, $limit));

        /** @var list<array{fecha: string, tipo: string, cambio: string}> $updates */
        $updates = Cache::remember('nexus_ai:platform_updates:'.$limit, 600, function () use ($limit): array {
            $fromFile = $this->fromChangelog($limit);

            return $fromFile !== [] ? $fromFile : $this->fromGit($limit);
        });

        return $updates;
    }

    /**
     * @return list<array{fecha: string, tipo: string, cambio: string}>
     */
    private function fromChangelog(int $limit): array
    {
        $path = base_path('CHANGELOG.md');
        if (! is_file($path)) {
            return [];
        }

        $updates = [];
        $heading = '';

        foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            if (str_starts_with($line, '## ')) {
                $heading = trim(substr($line, 3));
            } elseif (preg_match('/^[-*]\s+(.+)$/', $line, $match) === 1 && $heading !== '') {
                $updates[] = ['fecha' => $heading, 'tipo' => 'Novedad', 'cambio' => trim($match[1])];
            }

            if (count($updates) >= $limit) {
                break;
            }
        }

        return $updates;
    }

    /**
     * @return list<array{fecha: string, tipo: string, cambio: string}>
     */
    private function fromGit(int $limit): array
    {
        if (! is_dir(base_path('.git'))) {
            return [];
        }

        try {
            $process = new Process(['git', '-C', base_path(), 'log', '--no-merges', '--date=short', '--pretty=format:%ad|%s', '-n', '200']);
            $process->setTimeout(5);
            $process->run();
        } catch (Throwable) {
            return [];
        }

        if (! $process->isSuccessful()) {
            return [];
        }

        $updates = [];
        foreach (explode("\n", trim($process->getOutput())) as $line) {
            [$date, $subject] = array_pad(explode('|', $line, 2), 2, '');

            if (preg_match('/^(\w+)(?:\(([^)]*)\))?:\s*(.+)$/u', $subject, $match) !== 1 || ! isset(self::TYPES[$match[1]])) {
                continue;
            }

            $updates[] = ['fecha' => $date, 'tipo' => self::TYPES[$match[1]], 'cambio' => trim($match[3])];

            if (count($updates) >= $limit) {
                break;
            }
        }

        return $updates;
    }
}
