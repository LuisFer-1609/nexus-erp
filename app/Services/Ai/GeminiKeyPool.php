<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Cache;

/**
 * Pool de llaves de Gemini. En cada solicitud se elige una al azar entre las
 * que no están "descansando"; una llave que falla (cuota, error, inválida) se
 * aparta un tiempo y se usa otra, para que las llaves duren más.
 *
 * Las llaves nunca se escriben en logs ni en mensajes: solo se muestra una
 * versión enmascarada.
 */
final class GeminiKeyPool
{
    /**
     * @param  list<string>  $keys
     */
    public function __construct(private readonly array $keys) {}

    public static function fromConfig(): self
    {
        /** @var list<string> $keys */
        $keys = array_values(array_unique((array) config('nexus_ai.gemini.keys', [])));

        return new self($keys);
    }

    public function count(): int
    {
        return count($this->keys);
    }

    /**
     * Llaves que no están descansando.
     *
     * @param  list<string>  $exclude
     * @return list<string>
     */
    public function available(array $exclude = []): array
    {
        return array_values(array_filter(
            $this->keys,
            fn (string $key): bool => ! in_array($key, $exclude, true) && ! Cache::has($this->cooldownKey($key)),
        ));
    }

    /**
     * Elige una llave disponible al azar.
     *
     * @param  list<string>  $exclude
     */
    public function pick(array $exclude = []): ?string
    {
        $available = $this->available($exclude);

        if ($available === []) {
            return null;
        }

        return $available[random_int(0, count($available) - 1)];
    }

    /** Aparta una llave durante $seconds segundos. */
    public function cooldown(string $key, int $seconds): void
    {
        Cache::put($this->cooldownKey($key), true, max(1, $seconds));
    }

    /** Llave enmascarada para mostrar en estado o logs. */
    public function mask(string $key): string
    {
        return '…'.substr($key, -4);
    }

    private function cooldownKey(string $key): string
    {
        return 'nexus_ai:key_cooldown:'.hash('sha256', $key);
    }
}
