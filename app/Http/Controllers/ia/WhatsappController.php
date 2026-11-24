<?php

namespace App\Http\Controllers\ia;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\WhatsApp\WhatsappBridge;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Throwable;

/**
 * Vínculo del WhatsApp de cada usuario por QR. Cada usuario solo puede ver y
 * administrar SU propia sesión.
 */
class WhatsappController extends Controller
{
    public function index(Request $request, WhatsappBridge $bridge): Response
    {
        $stored = $bridge->stored($this->userId($request));

        return Inertia::render('ia/WhatsApp', [
            'configured' => $bridge->isConfigured(),
            'link' => [
                'status' => $stored->status ?? 'disconnected',
                'phone' => $stored?->phone,
                'connectedAt' => $stored?->connected_at?->toIso8601String(),
            ],
        ]);
    }

    public function start(Request $request, WhatsappBridge $bridge): JsonResponse
    {
        return $this->respond(fn (): array => $bridge->start($this->userId($request)));
    }

    public function status(Request $request, WhatsappBridge $bridge): JsonResponse
    {
        return $this->respond(fn (): array => $bridge->status($this->userId($request)));
    }

    public function destroy(Request $request, WhatsappBridge $bridge): JsonResponse
    {
        $userId = $this->userId($request);

        return $this->respond(function () use ($bridge, $userId): array {
            $bridge->disconnect($userId);

            return ['status' => 'disconnected', 'qr' => null, 'phone' => null];
        });
    }

    /**
     * @param  callable(): array<string, mixed>  $action
     */
    private function respond(callable $action): JsonResponse
    {
        try {
            return response()->json($action());
        } catch (RuntimeException $e) {
            Log::warning('Puente de WhatsApp: '.$e->getMessage());

            return response()->json([
                'message' => 'El servicio de WhatsApp no está disponible. Pide que lo inicien con «npm run whatsapp».',
            ], 503);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'No se pudo completar la acción de WhatsApp. Intenta de nuevo.'], 502);
        }
    }

    private function userId(Request $request): int
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user->id;
    }
}
