<?php

namespace App\Services\Ai\Documents;

/**
 * Divide un documento en fragmentos. Cada fragmento repite el encabezado
 * (tabla e id) para entenderse por sí solo cuando se recupera aislado. Los
 * textos muy largos se parten por palabras con un pequeño solapamiento.
 */
final class Chunker
{
    public function __construct(
        private readonly int $maxChars = 1200,
        private readonly int $overlapChars = 120,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            (int) config('nexus_ai.chunking.max_chars'),
            (int) config('nexus_ai.chunking.overlap_chars'),
        );
    }

    public function maxChars(): int
    {
        return $this->maxChars;
    }

    /**
     * @return list<string>
     */
    public function split(RowDocument $document): array
    {
        $continuation = $document->header.' (continuación)';
        $budget = max(100, $this->maxChars - mb_strlen($continuation) - 1);

        $lines = [];
        foreach ($document->lines as $line) {
            foreach ($this->splitLongLine($line, $budget) as $piece) {
                $lines[] = $piece;
            }
        }

        $chunks = [];
        $current = $document->header;

        foreach ($lines as $line) {
            if (mb_strlen($current) + 1 + mb_strlen($line) > $this->maxChars && $current !== $document->header && $current !== $continuation) {
                $chunks[] = $current;
                $current = $continuation;
            }
            $current .= "\n".$line;
        }

        $chunks[] = $current;

        return $chunks;
    }

    /**
     * @return list<string>
     */
    private function splitLongLine(string $line, int $limit): array
    {
        if (mb_strlen($line) <= $limit) {
            return [$line];
        }

        $pieces = [];
        $text = $line;

        while (mb_strlen($text) > $limit) {
            $cut = mb_strrpos(mb_substr($text, 0, $limit), ' ');
            if ($cut === false || $cut < (int) ($limit * 0.5)) {
                $cut = $limit;
            }

            $pieces[] = rtrim(mb_substr($text, 0, $cut));
            $advance = max($cut - $this->overlapChars, intdiv($cut, 2), 1);
            $text = ltrim(mb_substr($text, $advance));
        }

        if ($text !== '') {
            $pieces[] = $text;
        }

        return $pieces;
    }
}
