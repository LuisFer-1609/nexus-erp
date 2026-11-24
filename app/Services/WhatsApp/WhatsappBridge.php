<?php

namespace App\Services\WhatsApp;

use App\Models\WhatsappLink;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Habla con el puente de WhatsApp (whatsapp-bridge/) y refleja el estado del
 * vínculo de cada usuario en la tabla whatsapp_links.
 */
class WhatsappBridge
{
    public function isConfigured(): bool
    {
        return (string) config('nexus_ai.whatsapp.token') !== '';
    }

    /**
     * @return array{status: string, qr: string|null, phone: string|null}
     *
     * @throws RuntimeException si el puente no responde.
     */
    public function start(int $userId): array
    {
        $this->request()->post("sessions/{$userId}/start")->throw();

        return $this->status($userId);
    }

    /**
     * @return array{status: string, qr: string|null, phone: string|null}
     *
     * @throws RuntimeException si el puente no responde.
     */
    public function status(int $userId): array
    {
        try {
            /** @var array{status?: string, qr?: string|null, phone?: string|null} $data */
            $data = $this->request()->get("sessions/{$userId}")->throw()->json() ?? [];
        } catch (ConnectionException) {
            throw new RuntimeException('El puente de WhatsApp no está en ejecución.');
        }

        $state = [
            'status' => (string) ($data['status'] ?? WhatsappLink::STATUS_DISCONNECTED),
            'qr' => $data['qr'] ?? null,
            'phone' => $data['phone'] ?? null,
        ];

        $this->mirror($userId, $state);

        return $state;
    }

    public function disconnect(int $userId): void
    {
        try {
            $this->request()->delete("sessions/{$userId}")->throw();
        } catch (ConnectionException) {
            throw new RuntimeException('El puente de WhatsApp no está en ejecución.');
        }

        $this->mirror($userId, ['status' => WhatsappLink::STATUS_DISCONNECTED, 'qr' => null, 'phone' => null]);
    }

    /**
     * Último estado guardado (sin consultar al puente).
     */
    public function stored(int $userId): ?WhatsappLink
    {
        return WhatsappLink::query()->where(WhatsappLink::USER_ID, $userId)->first();
    }

    /**
     * @param  array{status: string, qr: string|null, phone: string|null}  $state
     */
    private function mirror(int $userId, array $state): void
    {
        $link = WhatsappLink::query()->firstOrNew([WhatsappLink::USER_ID => $userId]);
        $wasConnected = $link->status === WhatsappLink::STATUS_CONNECTED;

        $link->status = $state['status'];
        $link->phone = $state['phone'];
        if ($state['status'] === WhatsappLink::STATUS_CONNECTED && ! $wasConnected) {
            $link->connected_at = now();
        }
        if ($state['status'] === WhatsappLink::STATUS_DISCONNECTED) {
            $link->connected_at = null;
        }

        $link->save();
    }

    private function request(): PendingRequest
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Falta WHATSAPP_BRIDGE_TOKEN en el .env.');
        }

        return Http::baseUrl(rtrim((string) config('nexus_ai.whatsapp.url'), '/').'/')
            ->withHeaders(['x-bridge-token' => (string) config('nexus_ai.whatsapp.token')])
            ->acceptJson()
            ->timeout((int) config('nexus_ai.whatsapp.timeout'));
    }
}
