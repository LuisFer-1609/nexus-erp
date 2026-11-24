<?php

namespace App\Http\Controllers\ia;

use App\Http\Controllers\Controller;
use App\Models\AiReport;
use App\Models\User;
use App\Services\Ai\Documents\PdfPreview;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class IaReportController extends Controller
{
    /**
     * Descarga un reporte PDF. Solo la empresa que lo generó puede descargarlo.
     */
    public function download(Request $request, AiReport $report): StreamedResponse
    {
        $this->authorizeReport($request, $report);

        return Storage::disk('local')->download(
            $report->path,
            $this->fileName($report),
            ['Content-Type' => 'application/pdf'],
        );
    }

    /**
     * Muestra el PDF dentro del navegador (vista previa del chat), sin descargarlo.
     */
    public function preview(Request $request, AiReport $report): StreamedResponse
    {
        $this->authorizeReport($request, $report);

        return Storage::disk('local')->response(
            $report->path,
            $this->fileName($report),
            ['Content-Type' => 'application/pdf', 'X-Content-Type-Options' => 'nosniff'],
            'inline',
        );
    }

    /**
     * Miniatura PNG de la primera página del reporte, para la tarjeta del chat.
     */
    public function thumbnail(Request $request, AiReport $report, PdfPreview $previews): Response
    {
        $this->authorizeReport($request, $report);

        $png = $previews->png((string) Storage::disk('local')->get($report->path));
        abort_if($png === null, 404);

        return response($png, 200, ['Content-Type' => 'image/png', 'Cache-Control' => 'private, max-age=86400']);
    }

    private function authorizeReport(Request $request, AiReport $report): void
    {
        $user = $request->user();

        abort_unless($user instanceof User && $user->company_id !== null && $user->company_id === $report->company_id, 404);
        abort_unless(Storage::disk('local')->exists($report->path), 404);
    }

    private function fileName(AiReport $report): string
    {
        return (Str::slug($report->title) ?: 'reporte').'.pdf';
    }
}
