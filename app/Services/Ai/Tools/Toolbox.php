<?php

namespace App\Services\Ai\Tools;

use App\Models\AiPendingAction;
use App\Services\Ai\Actions\PendingActionService;
use App\Services\Ai\Documents\ColumnPolicy;
use App\Services\Ai\Documents\LabelResolver;
use App\Services\Ai\Documents\TenantRowReader;
use App\Services\Ai\Exceptions\AiUnavailableException;
use App\Services\Ai\NexusAssistant;
use App\Services\Ai\Payroll\PreNominaService;
use App\Services\Ai\Reports\ChartRenderer;
use App\Services\Ai\Reports\ReportService;
use App\Services\Ai\Schema\SchemaCatalog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Herramientas que Nexus puede usar durante una conversación. Todas operan
 * dentro de la empresa del usuario. Las que modifican datos (pre-nómina) solo
 * proponen una acción: se ejecuta cuando el usuario la confirma.
 */
class Toolbox
{
    private ?DataQueryService $data = null;

    private ?SchemaDescriber $describer = null;

    public function __construct(
        private readonly int $companyId,
        private readonly ?int $userId,
        private readonly string $userName,
        private readonly PendingActionService $actions,
        private readonly ReportService $reports,
        private readonly PreNominaService $payroll,
        private readonly PlatformUpdates $updates,
    ) {}

    /**
     * Resumen de tablas y columnas consultables para el prompt del sistema.
     * Cadena vacía si la base de datos no permite descubrir el esquema.
     */
    public function schemaDescription(): string
    {
        try {
            return Cache::remember('nexus_ai:schema_text', 300, function (): string {
                $this->boot();

                return (string) $this->describer?->describe();
            });
        } catch (Throwable $e) {
            Log::info('Nexus IA: no se pudo describir el esquema: '.$e->getMessage());

            return '';
        }
    }

    /**
     * Declaración de funciones en el formato de la API de Gemini.
     *
     * @return list<array<string, mixed>>
     */
    public function declarations(): array
    {
        $query = ['type' => 'OBJECT', 'properties' => $this->queryProperties(), 'required' => ['tabla']];

        return [[
            'functionDeclarations' => [
                [
                    'name' => 'consultar_datos',
                    'description' => 'Consulta datos reales de la empresa del usuario (siempre limitados a su empresa). Úsala para conteos, totales, promedios, listados, filtros por fecha, estado o nombre y agrupaciones. No escribas SQL: describe la consulta. Si no conoces los valores exactos de una columna de texto, primero agrupa por esa columna para verlos.',
                    'parameters' => $query,
                ],
                [
                    'name' => 'generar_reporte_pdf',
                    'description' => 'Genera un reporte PDF con la plantilla de Nexus. Cada sección lleva su propia consulta y el sistema pone los datos reales: tú NO copies cifras. El PDF aparece como tarjeta de descarga en el chat.',
                    'parameters' => [
                        'type' => 'OBJECT',
                        'properties' => [
                            'titulo' => ['type' => 'STRING', 'description' => 'Título del reporte.'],
                            'subtitulo' => ['type' => 'STRING', 'description' => 'Periodo o alcance, por ejemplo «Noviembre de 2026». Opcional.'],
                            'secciones' => [
                                'type' => 'ARRAY',
                                'description' => 'Secciones del reporte (máximo 8).',
                                'items' => [
                                    'type' => 'OBJECT',
                                    'properties' => [
                                        'titulo' => ['type' => 'STRING', 'description' => 'Encabezado de la sección.'],
                                        'descripcion' => ['type' => 'STRING', 'description' => 'Qué muestra la sección, sin cifras. Opcional.'],
                                        'encabezados' => ['type' => 'ARRAY', 'items' => ['type' => 'STRING'], 'description' => 'Encabezados de las columnas EN ESPAÑOL, en el mismo orden y cantidad que las columnas del resultado (primero las de agrupación y luego las agregaciones). Opcional pero recomendado.'],
                                        'consulta' => ['type' => 'OBJECT', 'properties' => $this->queryProperties(), 'required' => ['tabla']],
                                        'grafica' => [
                                            'type' => 'OBJECT',
                                            'description' => 'Gráfica opcional de la sección. Úsala cuando comparar categorías o ver una tendencia aporte claridad. Los valores los calcula el sistema a partir de las columnas indicadas.',
                                            'properties' => [
                                                'tipo' => ['type' => 'STRING', 'description' => 'barras (comparar), lineas (tendencia en el tiempo), pastel o dona (proporciones de un total).'],
                                                'columna_etiqueta' => ['type' => 'INTEGER', 'description' => 'Índice (desde 0) de la columna con las categorías o nombres.'],
                                                'columna_valor' => ['type' => 'INTEGER', 'description' => 'Índice (desde 0) de la columna numérica a graficar.'],
                                            ],
                                            'required' => ['tipo', 'columna_etiqueta', 'columna_valor'],
                                        ],
                                    ],
                                    'required' => ['titulo', 'consulta'],
                                ],
                            ],
                        ],
                        'required' => ['titulo', 'secciones'],
                    ],
                ],
                [
                    'name' => 'novedades_plataforma',
                    'description' => 'Devuelve las novedades y actualizaciones recientes de la plataforma. Úsala cuando pregunten qué hay de nuevo, qué cambió o qué actualizaciones hay. Explícalas en lenguaje sencillo para un usuario de negocio.',
                    'parameters' => ['type' => 'OBJECT', 'properties' => ['limite' => ['type' => 'INTEGER', 'description' => 'Cuántas novedades (por defecto 12).']]],
                ],
                [
                    'name' => 'preparar_prenomina',
                    'description' => 'Calcula una vista previa de la pre-nómina de un periodo con las asistencias y deja lista la acción para generarla. NO genera nada todavía: el usuario debe confirmarla con un botón. Después de usarla, resume la vista previa y pídele que confirme.',
                    'parameters' => [
                        'type' => 'OBJECT',
                        'properties' => [
                            'inicio' => ['type' => 'STRING', 'description' => 'Fecha de inicio AAAA-MM-DD.'],
                            'fin' => ['type' => 'STRING', 'description' => 'Fecha de fin AAAA-MM-DD.'],
                            'frecuencia' => ['type' => 'STRING', 'description' => 'semanal, quincenal o mensual. Si se omite se deduce de la duración.'],
                        ],
                        'required' => ['inicio', 'fin'],
                    ],
                ],
                [
                    'name' => 'pedir_aclaracion',
                    'description' => 'Úsala cuando falte un dato esencial para ayudar (por ejemplo, de qué periodo quiere el reporte). Formula UNA pregunta breve.',
                    'parameters' => ['type' => 'OBJECT', 'properties' => ['pregunta' => ['type' => 'STRING', 'description' => 'La pregunta para el usuario.']], 'required' => ['pregunta']],
                ],
                [
                    'name' => 'sin_datos',
                    'description' => 'Úsala cuando la pregunta no se pueda responder con los datos de la empresa ni con las demás herramientas.',
                    'parameters' => ['type' => 'OBJECT', 'properties' => ['motivo' => ['type' => 'STRING', 'description' => 'Por qué no hay información.']]],
                ],
            ],
        ]];
    }

    /**
     * @param  array<string, mixed>  $args
     */
    public function execute(string $name, array $args): ToolOutcome
    {
        try {
            return match ($name) {
                'consultar_datos' => $this->consultarDatos($args),
                'generar_reporte_pdf' => $this->generarReporte($args),
                'novedades_plataforma' => $this->novedades($args),
                'preparar_prenomina' => $this->prepararPrenomina($args),
                'pedir_aclaracion' => new ToolOutcome(['ok' => true], final: trim((string) ($args['pregunta'] ?? '')) ?: NexusAssistant::NO_INFO),
                'sin_datos' => new ToolOutcome(['ok' => true], final: NexusAssistant::NO_INFO),
                default => new ToolOutcome(['error' => "Herramienta desconocida: {$name}."]),
            };
        } catch (InvalidToolCallException $e) {
            return new ToolOutcome(['error' => $e->getMessage()]);
        } catch (AiUnavailableException $e) {
            Log::info('Nexus IA: herramienta no disponible: '.$e->getMessage());

            return new ToolOutcome(['error' => 'La consulta de datos no está disponible en este momento.']);
        }
    }

    /**
     * @param  array<string, mixed>  $args
     */
    private function consultarDatos(array $args): ToolOutcome
    {
        $result = $this->data()->run($this->companyId, $this->toSpec($args));

        return new ToolOutcome([
            'tabla' => $result['table'],
            'columnas' => $result['columns'],
            'filas' => $result['rows'],
            'total_filas' => count($result['rows']),
            'truncado' => $result['truncated'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $args
     */
    private function generarReporte(array $args): ToolOutcome
    {
        $title = trim((string) ($args['titulo'] ?? ''));
        $rawSections = $args['secciones'] ?? [];

        if ($title === '' || ! is_array($rawSections) || $rawSections === []) {
            throw new InvalidToolCallException('El reporte necesita «titulo» y al menos una sección con su «consulta».');
        }

        $sections = [];
        foreach (array_slice(array_values($rawSections), 0, ReportService::MAX_SECTIONS) as $index => $section) {
            $query = is_array($section) ? ($section['consulta'] ?? null) : null;
            if (! is_array($query)) {
                throw new InvalidToolCallException('La sección '.($index + 1).' necesita su «consulta».');
            }

            $result = $this->data()->run($this->companyId, $this->toSpec($query));

            // Encabezados en español propuestos por el modelo, solo si encajan con las columnas reales.
            $headers = is_array($section['encabezados'] ?? null)
                ? array_map(static fn (mixed $h): string => trim((string) $h), array_values($section['encabezados']))
                : [];
            $usable = count($headers) === count($result['columns']) && ! in_array('', $headers, true);

            $sections[] = [
                'heading' => trim((string) ($section['titulo'] ?? '')) ?: 'Sección '.($index + 1),
                'description' => isset($section['descripcion']) ? trim((string) $section['descripcion']) : null,
                'columns' => $usable ? $headers : $result['columns'],
                'rows' => $result['rows'],
                'note' => $result['truncated'] ? 'Se muestran las primeras '.count($result['rows']).' filas; hay más registros que coinciden.' : null,
                'chart' => $this->chartFor(is_array($section) ? ($section['grafica'] ?? null) : null, $result),
            ];
        }

        $totalRows = array_sum(array_map(static fn (array $s): int => count($s['rows']), $sections));

        $report = $this->reports->create($this->companyId, $this->userId, $this->userName, [
            'title' => $title,
            'subtitle' => isset($args['subtitulo']) ? trim((string) $args['subtitulo']) : null,
            // El resumen lo calcula el sistema: el modelo nunca escribe cifras del reporte.
            'summary' => 'Este reporte incluye '.count($sections).' sección(es) con '.$totalRows.' registro(s), calculados con los datos actuales de la empresa.',
            'sections' => $sections,
        ]);

        return new ToolOutcome(
            [
                'reporte_listo' => true,
                'titulo' => $report->title,
                'secciones' => array_map(static fn (array $s): array => ['titulo' => $s['heading'], 'filas' => count($s['rows'])], $sections),
                'nota' => 'El PDF ya aparece como tarjeta de descarga en el chat. No incluyas enlaces ni cifras que no hayas visto.',
            ],
            artifacts: [$report->toArtifact()],
        );
    }

    /**
     * Datos de la gráfica de una sección. Los valores salen de las filas reales; el modelo
     * solo indica qué columnas usar.
     *
     * @param  array{columns: list<string>, rows: list<list<mixed>>}  $result
     * @return array{type: string, labels: list<string>, values: list<float>}|null
     */
    private function chartFor(mixed $spec, array $result): ?array
    {
        if (! is_array($spec)) {
            return null;
        }

        $type = (string) ($spec['tipo'] ?? '');
        if (! in_array($type, ChartRenderer::TYPES, true)) {
            throw new InvalidToolCallException('La gráfica necesita «tipo»: barras, lineas, pastel o dona.');
        }

        $labelIndex = $spec['columna_etiqueta'] ?? null;
        $valueIndex = $spec['columna_valor'] ?? null;
        $columnCount = count($result['columns']);

        if (! is_int($labelIndex) || ! is_int($valueIndex) || $labelIndex === $valueIndex
            || $labelIndex < 0 || $valueIndex < 0 || $labelIndex >= $columnCount || $valueIndex >= $columnCount) {
            throw new InvalidToolCallException('La gráfica necesita «columna_etiqueta» y «columna_valor» distintas y válidas para las columnas del resultado.');
        }

        $labels = [];
        $values = [];
        foreach ($result['rows'] as $row) {
            $raw = $row[$valueIndex] ?? null;
            if (! is_numeric($raw)) {
                continue;
            }
            $labels[] = (string) ($row[$labelIndex] ?? '');
            $values[] = (float) $raw;
        }

        if ($values === []) {
            throw new InvalidToolCallException('La columna de valores no tiene números para graficar.');
        }

        $limit = in_array($type, ['pastel', 'dona'], true) ? PHP_INT_MAX : ChartRenderer::MAX_POINTS;
        if (count($values) > $limit) {
            // En pastel se agrupan el resto de las porciones en «Otros» al dibujarse.
            $labels = array_slice($labels, 0, $limit);
            $values = array_slice($values, 0, $limit);
        }

        return ['type' => $type, 'labels' => $labels, 'values' => $values];
    }

    /**
     * @param  array<string, mixed>  $args
     */
    private function novedades(array $args): ToolOutcome
    {
        $updates = $this->updates->latest((int) ($args['limite'] ?? 12));

        return new ToolOutcome($updates === []
            ? ['novedades' => [], 'nota' => 'No hay un registro de novedades disponible.']
            : ['novedades' => $updates]);
    }

    /**
     * @param  array<string, mixed>  $args
     */
    private function prepararPrenomina(array $args): ToolOutcome
    {
        $preview = $this->payroll->preview(
            $this->companyId,
            (string) ($args['inicio'] ?? ''),
            (string) ($args['fin'] ?? ''),
            isset($args['frecuencia']) ? (string) $args['frecuencia'] : null,
        );

        $title = 'Generar pre-nómina del '.date('d/m/Y', (int) strtotime($preview['start'])).' al '.date('d/m/Y', (int) strtotime($preview['end'])).' ('.$preview['frequency'].')';

        $action = $this->actions->propose($this->companyId, $this->userId, AiPendingAction::TYPE_PRENOMINA, $title, [
            'start' => $preview['start'],
            'end' => $preview['end'],
            'frequency' => $preview['frequency'],
        ]);

        $totals = $preview['totals'];

        return new ToolOutcome(
            [
                'vista_previa' => [
                    'periodo' => $preview['start'].' a '.$preview['end'],
                    'frecuencia' => $preview['frequency'],
                    'totales' => $totals,
                    'empleados' => array_slice($preview['rows'], 0, 30),
                    'avisos' => $preview['warnings'],
                ],
                'accion_pendiente' => true,
                'nota' => 'Aún NO se generó nada. El usuario debe confirmar con el botón que aparece en el chat. Resume la vista previa y pídele que confirme. Los importes quedarán en 0 porque no hay sueldos en el sistema.',
            ],
            actions: [[
                'id' => $action->id,
                'type' => AiPendingAction::TYPE_PRENOMINA,
                'title' => $title,
                'lines' => [
                    $totals['empleados'].' empleado(s) · '.$totals['por_revisar'].' por revisar',
                    $totals['retardos'].' retardo(s) · '.$totals['faltas'].' falta(s) sin justificar',
                ],
                'confirmLabel' => 'Generar pre-nómina',
            ]],
        );
    }

    /**
     * Traduce los parámetros de la herramienta al formato interno del motor de consultas.
     *
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    private function toSpec(array $args): array
    {
        $items = static fn (mixed $value): array => is_array($value) ? array_values(array_filter($value, 'is_array')) : [];

        return [
            'table' => $args['tabla'] ?? null,
            'columns' => is_array($args['columnas'] ?? null) ? $args['columnas'] : [],
            'filters' => array_map(static fn (array $f): array => [
                'column' => $f['columna'] ?? null,
                'op' => $f['operador'] ?? '=',
                'value' => $f['valor'] ?? null,
                'values' => $f['valores'] ?? null,
            ], $items($args['filtros'] ?? null)),
            'group_by' => array_map(static fn (array $g): array => [
                'column' => $g['columna'] ?? null,
                'bucket' => $g['periodo'] ?? '',
            ], $items($args['agrupar_por'] ?? null)),
            'aggregates' => array_map(static fn (array $a): array => [
                'fn' => $a['funcion'] ?? '',
                'column' => $a['columna'] ?? '*',
                'alias' => $a['alias'] ?? '',
            ], $items($args['agregaciones'] ?? null)),
            'order_by' => array_map(static fn (array $o): array => [
                'column' => $o['columna'] ?? '',
                'direction' => $o['direccion'] ?? 'asc',
            ], $items($args['ordenar_por'] ?? null)),
            'limit' => $args['limite'] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function queryProperties(): array
    {
        $text = static fn (string $description): array => ['type' => 'STRING', 'description' => $description];

        return [
            'tabla' => $text('Nombre exacto de la tabla (ver la lista de tablas disponibles).'),
            'columnas' => ['type' => 'ARRAY', 'items' => ['type' => 'STRING'], 'description' => 'Columnas a listar. Solo cuando NO hay agregaciones.'],
            'filtros' => [
                'type' => 'ARRAY',
                'description' => 'Condiciones (todas se combinan con Y).',
                'items' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'columna' => $text('Columna a filtrar.'),
                        'operador' => $text('Uno de: =, !=, >, >=, <, <=, contiene, in, entre, es_nulo, no_es_nulo, etiqueta_contiene. «etiqueta_contiene» filtra una columna que apunta a otra tabla (p. ej. employee_id) por el NOMBRE, como "Ana".'),
                        'valor' => $text('Valor para =, !=, >, >=, <, <=, contiene y etiqueta_contiene. Fechas como AAAA-MM-DD.'),
                        'valores' => ['type' => 'ARRAY', 'items' => ['type' => 'STRING'], 'description' => 'Lista para «in»; [desde, hasta] para «entre».'],
                    ],
                    'required' => ['columna', 'operador'],
                ],
            ],
            'agrupar_por' => [
                'type' => 'ARRAY',
                'description' => 'Columnas por las que agrupar.',
                'items' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'columna' => $text('Columna de agrupación.'),
                        'periodo' => $text('Solo para columnas de fecha: day, month o year.'),
                    ],
                    'required' => ['columna'],
                ],
            ],
            'agregaciones' => [
                'type' => 'ARRAY',
                'description' => 'Totales a calcular. Sin agregaciones se listan filas.',
                'items' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'funcion' => $text('count, count_distinct, sum, avg, min o max.'),
                        'columna' => $text("Columna, o '*' solo con count."),
                        'alias' => $text('Nombre del resultado (letras, números y guion bajo). Opcional.'),
                    ],
                    'required' => ['funcion'],
                ],
            ],
            'ordenar_por' => [
                'type' => 'ARRAY',
                'description' => 'Orden del resultado. Con agregaciones usa los nombres de columnas del resultado o alias.',
                'items' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'columna' => $text('Columna o alias.'),
                        'direccion' => $text('asc o desc.'),
                    ],
                    'required' => ['columna'],
                ],
            ],
            'limite' => ['type' => 'INTEGER', 'description' => 'Máximo de filas (por defecto 50, máximo 200).'],
        ];
    }

    private function data(): DataQueryService
    {
        $this->boot();

        return $this->data ?? throw new AiUnavailableException('El esquema no está disponible.');
    }

    private function boot(): void
    {
        if ($this->data !== null) {
            return;
        }

        $catalog = SchemaCatalog::build();
        $policy = new ColumnPolicy;
        $labels = new LabelResolver($catalog, $policy);

        $this->data = new DataQueryService($catalog, $labels, $policy, new TenantRowReader);
        $this->describer = new SchemaDescriber($catalog, $policy);
    }
}
