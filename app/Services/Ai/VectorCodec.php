<?php

namespace App\Services\Ai;

/**
 * Empaqueta y compara vectores. Los embeddings se guardan como float32
 * binario, de modo que funcionan en cualquier MySQL/MariaDB sin tipos
 * vectoriales nativos.
 */
final class VectorCodec
{
    /**
     * @param  array<int, float>  $vector
     */
    public static function pack(array $vector): string
    {
        return pack('g*', ...$vector);
    }

    /**
     * @return array<int, float>
     */
    public static function unpack(string $blob): array
    {
        $values = unpack('g*', $blob);

        return $values === false ? [] : array_values($values);
    }

    /**
     * Normaliza a longitud 1 para que el producto punto sea la similitud coseno.
     *
     * @param  array<int, float>  $vector
     * @return list<float>
     */
    public static function normalize(array $vector): array
    {
        $sum = 0.0;
        foreach ($vector as $value) {
            $sum += $value * $value;
        }

        if ($sum <= 0.0) {
            return array_values($vector);
        }

        $norm = sqrt($sum);

        return array_values(array_map(static fn (float $v): float => $v / $norm, $vector));
    }

    /**
     * @param  array<int, float>  $a
     * @param  array<int, float>  $b
     */
    public static function dot(array $a, array $b): float
    {
        $total = 0.0;
        $count = min(count($a), count($b));

        for ($i = 0; $i < $count; $i++) {
            $total += $a[$i] * $b[$i];
        }

        return $total;
    }
}
