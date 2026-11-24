<?php

namespace App\Services\Ai;

use App\Services\Ai\Exceptions\AiUnavailableException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Cliente de la API REST de Gemini con pool de llaves y reintento automático.
 *
 * Cada llamada elige una llave al azar. Si Google responde 429 (cuota),
 * 5xx, rechaza la llave o no hay conexión, esa llave se aparta un tiempo y
 * se reintenta con otra distinta hasta agotar los intentos.
 */
final class GeminiClient
{
    public function __construct(private readonly GeminiKeyPool $pool) {}

    public function isConfigured(): bool
    {
        return $this->pool->count() > 0;
    }

    /**
     * Embeddings de varios textos en una sola llamada (batchEmbedContents).
     *
     * @param  list<string>  $texts
     * @param  'RETRIEVAL_DOCUMENT'|'RETRIEVAL_QUERY'  $taskType
     * @return list<list<float>>
     */
    public function embed(array $texts, string $taskType): array
    {
        if ($texts === []) {
            return [];
        }

        $model = (string) config('nexus_ai.gemini.embedding_model');
        $dimensions = (int) config('nexus_ai.gemini.dimensions');

        $requests = array_map(static fn (string $text): array => [
            'model' => 'models/'.$model,
            'content' => ['parts' => [['text' => $text]]],
            'taskType' => $taskType,
            'outputDimensionality' => $dimensions,
        ], $texts);

        $json = $this->post('models/'.$model.':batchEmbedContents', ['requests' => $requests]);

        $embeddings = $json['embeddings'] ?? null;
        if (! is_array($embeddings) || count($embeddings) !== count($texts)) {
            throw new AiUnavailableException('Google devolvió una cantidad inesperada de embeddings.');
        }

        $vectors = [];
        foreach ($embeddings as $embedding) {
            $values = is_array($embedding) ? ($embedding['values'] ?? null) : null;
            if (! is_array($values) || $values === []) {
                throw new AiUnavailableException('Google devolvió un embedding vacío.');
            }
            $vectors[] = array_values(array_map('floatval', $values));
        }

        return $vectors;
    }

    /**
     * Genera texto con el modelo ligero configurado.
     */
    public function generate(string $systemInstruction, string $userPrompt): string
    {
        $json = $this->generateContent([
            'systemInstruction' => ['parts' => [['text' => $systemInstruction]]],
            'contents' => [['role' => 'user', 'parts' => [['text' => $userPrompt]]]],
            'generationConfig' => $this->generationConfig(),
        ]);

        $parts = $json['candidates'][0]['content']['parts'] ?? [];
        $text = '';
        if (is_array($parts)) {
            foreach ($parts as $part) {
                if (is_array($part) && isset($part['text']) && is_string($part['text'])) {
                    $text .= $part['text'];
                }
            }
        }

        return trim($text);
    }

    /**
     * Llamada completa a generateContent (con herramientas, adjuntos, etc.).
     * Devuelve la respuesta tal cual, para poder reenviar el turno del modelo sin alterarlo.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function generateContent(array $payload): array
    {
        return $this->post('models/'.(string) config('nexus_ai.gemini.chat_model').':generateContent', $payload);
    }

    /**
     * @return array<string, mixed>
     */
    public function generationConfig(): array
    {
        $config = ['maxOutputTokens' => (int) config('nexus_ai.gemini.max_output_tokens')];

        $temperature = config('nexus_ai.gemini.temperature');
        if (is_numeric($temperature)) {
            $config['temperature'] = (float) $temperature;
        }

        return $config;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function post(string $path, array $payload): array
    {
        if (! $this->isConfigured()) {
            throw new AiUnavailableException('No hay llaves de Gemini configuradas. Agrega GEMINI_API_KEYS en el .env.');
        }

        $attempts = max(1, min((int) config('nexus_ai.gemini.max_attempts'), $this->pool->count()));
        $tried = [];
        $lastReason = 'sin llaves disponibles';

        for ($i = 0; $i < $attempts; $i++) {
            $key = $this->pool->pick($tried);
            if ($key === null) {
                break;
            }
            $tried[] = $key;

            try {
                $response = Http::baseUrl((string) config('nexus_ai.gemini.base_url'))
                    ->withHeaders(['x-goog-api-key' => $key])
                    ->acceptJson()
                    ->asJson()
                    ->timeout((int) config('nexus_ai.gemini.timeout'))
                    ->post($path, $payload);
            } catch (ConnectionException) {
                $this->pool->cooldown($key, 10);
                $lastReason = 'sin conexión con Google';

                continue;
            }

            if ($response->successful()) {
                /** @var array<string, mixed> $json */
                $json = $response->json() ?? [];

                return $json;
            }

            $status = $response->status();

            if ($status === 429) {
                $this->pool->cooldown($key, $this->retryAfter($response));
                $lastReason = 'cuota agotada';

                continue;
            }

            if ($status === 401 || $status === 403 || $this->isInvalidKey($response)) {
                $this->pool->cooldown($key, (int) config('nexus_ai.gemini.invalid_key_cooldown_seconds'));
                $lastReason = 'llave rechazada';

                continue;
            }

            if ($status >= 500) {
                $this->pool->cooldown($key, 5);
                $lastReason = 'error de servidor de Google';

                continue;
            }

            // Otro 4xx: la solicitud es inválida, cambiar de llave no lo arregla.
            $message = mb_substr((string) $response->json('error.message', 'solicitud rechazada'), 0, 200);

            throw new AiUnavailableException("Google rechazó la solicitud ({$status}): {$message}");
        }

        throw new AiUnavailableException("No se pudo completar la consulta a Gemini: {$lastReason}.");
    }

    private function retryAfter(Response $response): int
    {
        $header = $response->header('Retry-After');

        return is_numeric($header) && (int) $header > 0
            ? min((int) $header, 3600)
            : (int) config('nexus_ai.gemini.cooldown_seconds');
    }

    private function isInvalidKey(Response $response): bool
    {
        return $response->status() === 400 && str_contains($response->body(), 'API_KEY_INVALID');
    }
}
