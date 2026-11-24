<?php

namespace App\Services\Ai\Tools;

use App\Services\Ai\Actions\PendingActionService;
use App\Services\Ai\Payroll\PreNominaService;
use App\Services\Ai\Reports\ReportService;

/**
 * Crea el conjunto de herramientas para una empresa y usuario concretos.
 */
class ToolboxFactory
{
    public function __construct(
        private readonly PendingActionService $actions,
        private readonly ReportService $reports,
        private readonly PreNominaService $payroll,
        private readonly PlatformUpdates $updates,
    ) {}

    public function make(int $companyId, ?int $userId, string $userName): Toolbox
    {
        return new Toolbox($companyId, $userId, $userName, $this->actions, $this->reports, $this->payroll, $this->updates);
    }
}
