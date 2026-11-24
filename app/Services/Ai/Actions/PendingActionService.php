<?php

namespace App\Services\Ai\Actions;

use App\Models\AiPendingAction;
use App\Models\AiReport;
use App\Services\Ai\Payroll\PreNominaService;
use App\Services\Ai\Reports\ReportService;
use App\Services\Ai\Tools\InvalidToolCallException;
use Throwable;

/**
 * Acciones que modifican datos. Nexus solo puede PROPONERLAS; se ejecutan
 * únicamente cuando el usuario las confirma, una sola vez y dentro de su empresa.
 */
class PendingActionService
{
    private const TTL_MINUTES = 30;

    public function __construct(
        private readonly PreNominaService $payroll,
        private readonly ReportService $reports,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function propose(int $companyId, ?int $userId, string $type, string $title, array $payload): AiPendingAction
    {
        return AiPendingAction::query()->create([
            AiPendingAction::COMPANY_ID => $companyId,
            AiPendingAction::USER_ID => $userId,
            AiPendingAction::TYPE => $type,
            AiPendingAction::TITLE => mb_substr($title, 0, 200),
            AiPendingAction::PAYLOAD => $payload,
            AiPendingAction::STATUS => AiPendingAction::STATUS_PENDING,
            AiPendingAction::EXPIRES_AT => now()->addMinutes(self::TTL_MINUTES),
        ]);
    }

    /**
     * @return array{message: string, report: AiReport|null}
     *
     * @throws InvalidToolCallException
     */
    public function confirm(AiPendingAction $action, string $author): array
    {
        // Se reclama la acción de forma atómica: una doble pulsación no la ejecuta dos veces.
        $claimed = AiPendingAction::query()
            ->whereKey($action->getKey())
            ->where(AiPendingAction::STATUS, AiPendingAction::STATUS_PENDING)
            ->where(AiPendingAction::EXPIRES_AT, '>', now())
            ->update([AiPendingAction::STATUS => AiPendingAction::STATUS_CONFIRMED]);

        if ($claimed === 0) {
            throw new InvalidToolCallException(
                $action->expires_at->isPast() && $action->status === AiPendingAction::STATUS_PENDING
                    ? 'Esta acción expiró. Pídele a Nexus que la prepare de nuevo.'
                    : 'Esta acción ya fue atendida.',
            );
        }

        try {
            $result = match ($action->type) {
                AiPendingAction::TYPE_PRENOMINA => $this->generatePrenomina($action, $author),
                default => throw new InvalidToolCallException('Tipo de acción desconocido.'),
            };
        } catch (Throwable $e) {
            $action->forceFill([
                AiPendingAction::STATUS => AiPendingAction::STATUS_FAILED,
                AiPendingAction::RESULT => ['error' => mb_substr($e->getMessage(), 0, 300)],
            ])->save();

            throw $e;
        }

        $action->forceFill([AiPendingAction::RESULT => ['message' => $result['message'], 'report_id' => $result['report']?->id]])->save();

        return $result;
    }

    public function cancel(AiPendingAction $action): void
    {
        AiPendingAction::query()
            ->whereKey($action->getKey())
            ->where(AiPendingAction::STATUS, AiPendingAction::STATUS_PENDING)
            ->update([AiPendingAction::STATUS => AiPendingAction::STATUS_CANCELLED]);
    }

    /**
     * @return array{message: string, report: AiReport|null}
     */
    private function generatePrenomina(AiPendingAction $action, string $author): array
    {
        /** @var array{start: string, end: string, frequency: string} $payload */
        $payload = $action->payload;

        $generated = $this->payroll->generate($action->company_id, $payload['start'], $payload['end'], $payload['frequency']);
        $preview = $generated['preview'];
        /** @var list<array<string, mixed>> $rows */
        $rows = $preview['rows'];
        /** @var array<string, mixed> $totals */
        $totals = $preview['totals'];

        $period = $this->formatDate($preview['start']).' al '.$this->formatDate($preview['end']);

        $report = $this->reports->create($action->company_id, $action->user_id, $author, [
            'title' => 'Pre-nómina del '.$period,
            'subtitle' => 'Frecuencia de pago: '.$preview['frequency'],
            'summary' => 'Pre-nómina calculada con las asistencias registradas en el periodo. '
                .$totals['empleados'].' empleado(s), '.$totals['por_revisar'].' por revisar.',
            'sections' => [
                [
                    'heading' => 'Detalle por empleado',
                    'description' => 'Las faltas justificadas por una incidencia no se cuentan como falta.',
                    'columns' => ['Empleado', 'Código', 'Días trabajados', 'Retardos', 'Faltas', 'Faltas justificadas', 'Horas extra', 'Estatus', 'Observaciones'],
                    'rows' => array_map(static fn (array $r): array => [
                        $r['empleado'], $r['codigo'], $r['dias_trabajados'], $r['retardos'], $r['faltas'],
                        $r['faltas_justificadas'], $r['horas_extra'], $r['estatus'], $r['observaciones'],
                    ], $rows),
                ],
                [
                    'heading' => 'Totales del periodo',
                    'columns' => ['Concepto', 'Total'],
                    'rows' => [
                        ['Empleados', $totals['empleados']],
                        ['Por revisar', $totals['por_revisar']],
                        ['Días trabajados', $totals['dias_trabajados']],
                        ['Retardos', $totals['retardos']],
                        ['Faltas', $totals['faltas']],
                        ['Horas extra', $totals['horas_extra']],
                    ],
                ],
                [
                    'heading' => 'Importante',
                    'columns' => ['Nota'],
                    'rows' => [['Los importes (percepciones, deducciones y neto) quedan en 0 hasta capturar los conceptos de sueldo de cada empleado: el sistema aún no guarda sueldos base.']],
                ],
            ],
        ]);

        $verb = $generated['created'] ? 'quedó generada' : 'se actualizó';

        return [
            'message' => "La pre-nómina del {$period} {$verb}: {$totals['empleados']} empleado(s), {$totals['por_revisar']} por revisar. Los importes quedan en 0 hasta capturar los sueldos.",
            'report' => $report,
        ];
    }

    private function formatDate(string $date): string
    {
        return date('d/m/Y', (int) strtotime($date));
    }
}
