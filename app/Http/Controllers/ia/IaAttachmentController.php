<?php

namespace App\Http\Controllers\ia;

use App\Http\Controllers\Controller;
use App\Models\NexusAttachment;
use App\Models\NexusConversation;
use App\Models\User;
use App\Services\Ai\Documents\PdfPreview;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Entrega los archivos que el usuario adjuntó en sus conversaciones. Solo su
 * dueño (dentro de su empresa) puede verlos o descargarlos.
 */
class IaAttachmentController extends Controller
{
    /**
     * Miniatura PNG de la primera página de un PDF adjunto.
     */
    public function thumbnail(Request $request, NexusAttachment $attachment, PdfPreview $previews): Response
    {
        $this->authorizeAttachment($request, $attachment);
        abort_unless($attachment->mime === 'application/pdf', 404);

        $png = $previews->png((string) Storage::disk('local')->get($attachment->path));
        abort_if($png === null, 404);

        return response($png, 200, ['Content-Type' => 'image/png', 'Cache-Control' => 'private, max-age=86400']);
    }

    public function show(Request $request, NexusAttachment $attachment): StreamedResponse
    {
        $this->authorizeAttachment($request, $attachment);

        return Storage::disk('local')->response(
            $attachment->path,
            $attachment->name,
            // El tipo lo detectó el servidor al subirlo; nosniff evita que el navegador lo reinterprete.
            ['Content-Type' => $attachment->mime, 'X-Content-Type-Options' => 'nosniff'],
            $request->boolean('descargar') ? 'attachment' : 'inline',
        );
    }

    private function authorizeAttachment(Request $request, NexusAttachment $attachment): void
    {
        $user = $request->user();
        $conversation = $attachment->message?->conversation;

        abort_unless(
            $user instanceof User
                && $conversation instanceof NexusConversation
                && $user->company_id !== null
                && $conversation->user_id === $user->id
                && $conversation->company_id === $user->company_id,
            404,
        );
        abort_unless(Storage::disk('local')->exists($attachment->path), 404);
    }
}
