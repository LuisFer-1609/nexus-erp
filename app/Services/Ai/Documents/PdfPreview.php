<?php

namespace App\Services\Ai\Documents;

use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

/**
 * Miniatura PNG de la primera página de un PDF, para mostrarla en el chat como
 * vista previa real. Usa pdftoppm (poppler-utils). Si no está instalado o el PDF
 * no se puede leer, devuelve null y el chat muestra una tarjeta de respaldo.
 *
 * Las miniaturas se guardan en caché privada, indexadas por el contenido del PDF.
 */
final class PdfPreview
{
    private const RESOLUTION = 120;

    private const TIMEOUT_SECONDS = 15;

    public function png(string $pdfBytes): ?string
    {
        $cache = $this->cachePath(hash('sha256', $pdfBytes));
        if (is_file($cache)) {
            return (string) file_get_contents($cache);
        }

        $dir = storage_path('app/private/previews');
        File::ensureDirectoryExists($dir);

        $base = $dir.'/tmp-'.bin2hex(random_bytes(8));
        $input = $base.'.pdf';
        $output = $base.'.png';

        try {
            file_put_contents($input, $pdfBytes);

            // Sin shell: los argumentos van como lista, así que el nombre del archivo no se interpreta.
            $process = new Process([
                'pdftoppm', '-png', '-r', (string) self::RESOLUTION,
                '-f', '1', '-l', '1', '-singlefile', $input, $base,
            ]);
            $process->setTimeout(self::TIMEOUT_SECONDS);
            $process->run();

            if (! $process->isSuccessful() || ! is_file($output)) {
                return null;
            }

            $png = (string) file_get_contents($output);
            if (! str_starts_with($png, "\x89PNG")) {
                return null;
            }

            file_put_contents($cache, $png);

            return $png;
        } catch (\Throwable) {
            // Sin pdftoppm instalado o PDF dañado: no es un error para el usuario.
            return null;
        } finally {
            @unlink($input);
            @unlink($output);
        }
    }

    private function cachePath(string $hash): string
    {
        return storage_path('app/private/previews/'.$hash.'.png');
    }
}
