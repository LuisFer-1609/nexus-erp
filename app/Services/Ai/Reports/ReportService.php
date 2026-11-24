<?php

namespace App\Services\Ai\Reports;

use App\Models\AiReport;
use Carbon\CarbonImmutable;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Genera reportes PDF con la plantilla de Nexus (resources/views/pdf/report.blade.php).
 * Todos los reportes comparten el mismo diseño: solo cambian el título, el
 * resumen y las secciones.
 */
class ReportService
{
    /** Secciones máximas y filas máximas por sección, para que el PDF siga siendo manejable. */
    public const MAX_SECTIONS = 8;

    public const MAX_ROWS_PER_SECTION = 200;

    public function __construct(private readonly ChartRenderer $charts) {}

    /**
     * @param  array{title: string, subtitle?: string|null, summary?: string|null, sections: list<array{heading: string, description?: string|null, columns: list<string>, rows: list<list<mixed>>, note?: string|null, chart?: array{type: string, labels: list<string>, values: list<float>}|null}>}  $content
     */
    public function create(int $companyId, ?int $userId, string $author, array $content): AiReport
    {
        $company = (string) (DB::table('companies')->where('id', $companyId)->value('name') ?? 'Empresa');

        $sections = [];
        $widest = 0;
        foreach (array_slice($content['sections'], 0, self::MAX_SECTIONS) as $section) {
            $columns = array_map(fn (string $c): string => $this->headerLabel($c), $section['columns']);
            $rows = array_map(
                fn (array $row): array => array_map(
                    fn (mixed $cell): mixed => $this->cellValue($cell),
                    $row,
                ),
                array_slice($section['rows'], 0, self::MAX_ROWS_PER_SECTION),
            );

            $chart = $section['chart'] ?? null;

            $sections[] = [
                'heading' => $section['heading'],
                'description' => $section['description'] ?? null,
                'columns' => $columns,
                'rows' => $rows,
                'numeric' => $this->numericColumns($columns, $rows),
                'note' => $section['note'] ?? null,
                'chart' => $chart === null ? null : 'data:image/png;base64,'.base64_encode($this->charts->render(
                    $chart['type'],
                    // Sin título: el encabezado de la sección ya lo dice.
                    '',
                    $this->chartLabels($chart['labels']),
                    $chart['values'],
                )),
            ];
            $widest = max($widest, count($columns));
        }

        $html = view('pdf.report', [
            'title' => $content['title'],
            'subtitle' => $content['subtitle'] ?? null,
            'summary' => $content['summary'] ?? null,
            'company' => $company,
            'author' => $author !== '' ? $author : 'Nexus',
            'generatedAt' => now()->setTimezone((string) config('nexus_ai.report_timezone'))->format('d/m/Y H:i'),
            'sections' => $sections,
        ])->render();

        $bytes = $this->render($html, landscape: $widest > 7);

        $id = (string) Str::uuid();
        $path = "reports/{$companyId}/{$id}.pdf";
        Storage::disk('local')->put($path, $bytes);

        return AiReport::query()->create([
            AiReport::ID => $id,
            AiReport::COMPANY_ID => $companyId,
            AiReport::USER_ID => $userId,
            AiReport::TITLE => mb_substr($content['title'], 0, 200),
            AiReport::PATH => $path,
            AiReport::SIZE_BYTES => strlen($bytes),
            AiReport::META => [
                'sections' => count($sections),
                'rows' => array_sum(array_map(static fn (array $s): int => count($s['rows']), $sections)),
            ],
        ]);
    }

    private function render(string $html, bool $landscape): string
    {
        $options = new Options;
        $options->set('defaultFont', 'DejaVu Sans');
        // Nunca se cargan recursos externos ni se ejecuta PHP dentro del PDF.
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);
        $options->set('chroot', resource_path('views'));
        $options->set('tempDir', storage_path('app/private'));

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', $landscape ? 'landscape' : 'portrait');
        $dompdf->render();

        $canvas = $dompdf->getCanvas();
        $font = $dompdf->getFontMetrics()->getFont('DejaVu Sans', 'normal');
        // Alineado al margen derecho y debajo de la línea del pie (no encima de ella).
        $label = 'Página {PAGE_NUM} de {PAGE_COUNT}';
        $width = $dompdf->getFontMetrics()->getTextWidth('Página 99 de 99', $font, 8);
        $canvas->page_text($canvas->get_width() - 40 - $width, $canvas->get_height() - 26, $label, $font, 8, [0.39, 0.45, 0.55]);

        return (string) $dompdf->output();
    }

    /** «lead_stage_id» → «Lead stage», «total» → «Total». */
    private function headerLabel(string $column): string
    {
        $label = str_replace('_', ' ', (string) preg_replace('/_id$/', '', $column));

        return mb_strtoupper(mb_substr($label, 0, 1)).mb_substr($label, 1);
    }

    /**
     * Una columna es numérica (alineada a la derecha) si todos sus valores lo son.
     *
     * @param  list<string>  $columns
     * @param  list<list<mixed>>  $rows
     * @return list<bool>
     */
    /**
     * Las fechas AAAA-MM-DD se muestran como día de la semana en español («lunes 23 nov.»),
     * para que la gráfica se entienda sin leer el formato técnico. El resto no cambia.
     *
     * @param  list<mixed>  $labels
     * @return list<string>
     */
    public function chartLabels(array $labels): array
    {
        return array_map(static function (mixed $label): string {
            $text = (string) $label;

            return self::weekdayDate($text, 'dddd D MMM') ?? $text;
        }, $labels);
    }

    /**
     * Valor de una celda de la tabla. Las fechas («2026-11-13» o «2026-11-13 09:17:00»)
     * llevan el nombre del día: «viernes 13 nov. 2026», con la hora cuando la traen.
     */
    public function cellValue(mixed $cell): mixed
    {
        if (is_string($cell) && preg_match('/^(\d{4}-\d{2}-\d{2})(?: (\d{2}:\d{2})(?::\d{2})?)?$/', $cell, $m) === 1) {
            $date = self::weekdayDate($m[1], 'dddd D MMM YYYY');

            return $date === null ? $cell : $date.(isset($m[2]) ? ' '.$m[2] : '');
        }

        return is_scalar($cell) || $cell === null ? $cell : json_encode($cell);
    }

    /** «2026-11-23» → texto en español con el formato dado; null si no es una fecha válida. */
    private static function weekdayDate(string $text, string $format): ?string
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $text) !== 1) {
            return null;
        }

        $date = CarbonImmutable::createFromFormat('!Y-m-d', $text);
        if (! $date instanceof CarbonImmutable || $date->format('Y-m-d') !== $text) {
            return null;
        }

        $spanish = $date->locale('es');

        return $spanish instanceof CarbonImmutable ? $spanish->isoFormat($format) : null;
    }

    private function numericColumns(array $columns, array $rows): array
    {
        $numeric = [];
        foreach (array_keys($columns) as $index) {
            $values = array_filter(array_column($rows, $index), static fn (mixed $v): bool => $v !== null && $v !== '');
            $numeric[] = $values !== [] && count(array_filter($values, 'is_numeric')) === count($values);
        }

        return $numeric;
    }
}
