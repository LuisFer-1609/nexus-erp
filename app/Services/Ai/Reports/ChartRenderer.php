<?php

namespace App\Services\Ai\Reports;

use InvalidArgumentException;

/**
 * Dibuja gráficas (barras, líneas, pastel y dona) como PNG con la paleta de Nexus.
 *
 * Dompdf no dibuja SVG en línea, así que la gráfica se genera como imagen. Se
 * renderiza al doble de resolución para que se vea nítida en el PDF.
 */
final class ChartRenderer
{
    public const TYPES = ['barras', 'lineas', 'pastel', 'dona'];

    /** Máximo de puntos en barras y líneas; máximo de porciones en pastel y dona. */
    public const MAX_POINTS = 30;

    public const MAX_SLICES = 8;

    private const WIDTH = 1400;

    private const HEIGHT = 780;

    /** Altura donde empieza el área de la gráfica: más arriba si no lleva título. */
    private int $top = 200;

    private const PALETTE = [
        [35, 198, 200], [10, 22, 30], [19, 155, 158], [125, 211, 212],
        [71, 85, 105], [148, 163, 184], [15, 118, 110], [203, 213, 225],
    ];

    /**
     * @param  list<string>  $labels
     * @param  list<float>  $values
     *
     * Con título vacío no se dibuja: el reporte ya muestra el encabezado de la sección.
     */
    public function render(string $type, string $title, array $labels, array $values): string
    {
        if (! in_array($type, self::TYPES, true)) {
            throw new InvalidArgumentException("Tipo de gráfica no válido: {$type}.");
        }

        $image = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        imageantialias($image, true);
        imagefill($image, 0, 0, $this->color($image, [255, 255, 255]));

        $this->top = $title === '' ? 90 : 200;
        if ($title !== '') {
            $this->title($image, $title);
        }

        match ($type) {
            'barras' => $this->bars($image, $labels, $values),
            'lineas' => $this->lines($image, $labels, $values),
            default => $this->slices($image, $labels, $values, donut: $type === 'dona'),
        };

        ob_start();
        imagepng($image);
        $png = (string) ob_get_clean();
        imagedestroy($image);

        return $png;
    }

    private function title(\GdImage $image, string $title): void
    {
        imagefilledrectangle($image, 60, 58, 72, 100, $this->color($image, [35, 198, 200]));
        $this->text($image, 34, 92, 98, [10, 22, 30], $this->font(bold: true), mb_strimwidth($title, 0, 70, '…'));
    }

    /**
     * @param  list<string>  $labels
     * @param  list<float>  $values
     */
    private function bars(\GdImage $image, array $labels, array $values): void
    {
        [$left, $right, $top, $bottom] = [170, self::WIDTH - 60, $this->top, self::HEIGHT - 170];
        $max = $this->niceMax(max(array_merge([0.0], array_map(fn (float $v): float => max(0.0, $v), $values))));
        $this->axes($image, $left, $right, $top, $bottom, $max);

        $count = count($values);
        $slot = ($right - $left) / max(1, $count);
        $width = (int) min(130, $slot * 0.62);
        $rotate = $count > 7;

        foreach ($values as $i => $value) {
            $height = (int) round(($bottom - $top) * max(0.0, $value) / $max);
            $x = (int) round($left + $slot * $i + ($slot - $width) / 2);

            // Degradado vertical cian: de más claro arriba a más intenso abajo.
            for ($y = 0; $y < $height; $y++) {
                $t = $height > 0 ? $y / $height : 0;
                $rgb = [
                    (int) round(125 + (19 - 125) * $t),
                    (int) round(211 + (155 - 211) * $t),
                    (int) round(212 + (158 - 212) * $t),
                ];
                imageline($image, $x, $bottom - $y, $x + $width, $bottom - $y, $this->color($image, $rgb));
            }

            $this->centeredText($image, 22, $x + $width / 2, $bottom - $height - 14, [10, 22, 30], $this->font(bold: true), $this->format($value));

            $label = mb_strimwidth($labels[$i] ?? '', 0, $rotate ? 22 : (int) max(8, $slot / 13), '…');
            if ($rotate) {
                $this->text($image, 20, $x - 6, $bottom + 26, [71, 85, 105], $this->font(), $label, angle: -35);
            } else {
                $this->centeredText($image, 22, $x + $width / 2, $bottom + 42, [71, 85, 105], $this->font(), $label);
            }
        }
    }

    /**
     * @param  list<string>  $labels
     * @param  list<float>  $values
     */
    private function lines(\GdImage $image, array $labels, array $values): void
    {
        [$left, $right, $top, $bottom] = [170, self::WIDTH - 60, $this->top, self::HEIGHT - 170];
        $max = $this->niceMax(max(array_merge([0.0], array_map(fn (float $v): float => max(0.0, $v), $values))));
        $this->axes($image, $left, $right, $top, $bottom, $max);

        $count = count($values);
        $step = ($right - $left) / max(1, $count);
        $points = [];
        foreach ($values as $i => $value) {
            $points[] = [
                (int) round($left + $step * ($i + 0.5)),
                (int) round($bottom - ($bottom - $top) * max(0.0, $value) / $max),
            ];
        }

        // Área bajo la línea con transparencia.
        if ($points !== []) {
            $area = [$points[0][0], $bottom];
            foreach ($points as [$x, $y]) {
                $area[] = $x;
                $area[] = $y;
            }
            $area[] = $points[count($points) - 1][0];
            $area[] = $bottom;
            imagefilledpolygon($image, $area, $this->color($image, [35, 198, 200], alpha: 100));
        }

        imagesetthickness($image, 6);
        for ($i = 1; $i < count($points); $i++) {
            imageline($image, $points[$i - 1][0], $points[$i - 1][1], $points[$i][0], $points[$i][1], $this->color($image, [19, 155, 158]));
        }
        imagesetthickness($image, 1);

        foreach ($points as $i => [$x, $y]) {
            imagefilledellipse($image, $x, $y, 26, 26, $this->color($image, [255, 255, 255]));
            imageellipse($image, $x, $y, 26, 26, $this->color($image, [35, 198, 200]));
            imagefilledellipse($image, $x, $y, 12, 12, $this->color($image, [19, 155, 158]));

            if ($count <= 12) {
                $this->centeredText($image, 22, $x, $y - 24, [10, 22, 30], $this->font(bold: true), $this->format($values[$i]));
            }

            $label = mb_strimwidth($labels[$i] ?? '', 0, $count > 7 ? 14 : 18, '…');
            $this->centeredText($image, 20, $x, $bottom + 42, [71, 85, 105], $this->font(), $label);
        }
    }

    /**
     * @param  list<string>  $labels
     * @param  list<float>  $values
     */
    private function slices(\GdImage $image, array $labels, array $values, bool $donut): void
    {
        [$labels, $values] = $this->topSlices($labels, $values);
        $total = array_sum($values);

        if ($total <= 0) {
            $this->centeredText($image, 30, self::WIDTH / 2, self::HEIGHT / 2, [71, 85, 105], $this->font(), 'Sin valores para graficar');

            return;
        }

        $radius = 250;
        $cx = 430;
        $cy = (int) (self::HEIGHT / 2 + 30);
        $start = -90.0;

        foreach ($values as $i => $value) {
            $sweep = 360.0 * $value / $total;
            imagefilledarc($image, $cx, $cy, $radius * 2, $radius * 2, (int) round($start), (int) round($start + $sweep), $this->color($image, self::PALETTE[$i % count(self::PALETTE)]), IMG_ARC_PIE);
            $start += $sweep;
        }

        if ($donut) {
            $hole = (int) ($radius * 1.16);
            imagefilledellipse($image, $cx, $cy, $hole, $hole, $this->color($image, [255, 255, 255]));
            $this->centeredText($image, 30, $cx, $cy + 6, [10, 22, 30], $this->font(bold: true), $this->format($total));
            $this->centeredText($image, 20, $cx, $cy + 44, [100, 116, 139], $this->font(), 'Total');
        }

        // Leyenda: color, nombre, valor y porcentaje.
        $legendX = 800;
        $y = 230;
        foreach ($values as $i => $value) {
            $color = self::PALETTE[$i % count(self::PALETTE)];
            imagefilledrectangle($image, $legendX, $y - 22, $legendX + 26, $y + 4, $this->color($image, $color));
            $this->text($image, 24, $legendX + 42, $y, [15, 23, 42], $this->font(), mb_strimwidth($labels[$i], 0, 17, '…'));
            $percent = round(100 * $value / $total, 1);
            // El valor se alinea al margen derecho; el nombre se acorta para no encimarse.
            $this->text($image, 24, self::WIDTH - 70, $y, [15, 23, 42], $this->font(bold: true), $this->format($value).'  ·  '.number_format($percent, 1, ',', '.').' %', align: 'right');
            imageline($image, $legendX, $y + 26, self::WIDTH - 60, $y + 26, $this->color($image, [226, 232, 240]));
            $y += 66;
        }
    }

    /**
     * Pastel y dona: las porciones más grandes se muestran y el resto se agrupa en «Otros».
     *
     * @param  list<string>  $labels
     * @param  list<float>  $values
     * @return array{0: list<string>, 1: list<float>}
     */
    private function topSlices(array $labels, array $values): array
    {
        $pairs = [];
        foreach ($values as $i => $value) {
            $pairs[] = [$labels[$i] ?? '', max(0.0, $value)];
        }
        usort($pairs, static fn (array $a, array $b): int => $b[1] <=> $a[1]);

        if (count($pairs) <= self::MAX_SLICES) {
            return [array_column($pairs, 0), array_column($pairs, 1)];
        }

        $head = array_slice($pairs, 0, self::MAX_SLICES - 1);
        $rest = array_sum(array_column(array_slice($pairs, self::MAX_SLICES - 1), 1));
        $head[] = ['Otros', $rest];

        return [array_column($head, 0), array_column($head, 1)];
    }

    private function axes(\GdImage $image, int $left, int $right, int $top, int $bottom, float $max): void
    {
        for ($i = 0; $i <= 5; $i++) {
            $y = (int) round($bottom - ($bottom - $top) * $i / 5);
            imageline($image, $left, $y, $right, $y, $this->color($image, [226, 232, 240]));
            $this->text($image, 20, $left - 22, $y + 7, [100, 116, 139], $this->font(), $this->format($max * $i / 5), align: 'right');
        }

        imagefilledrectangle($image, $left, $bottom, $right, $bottom + 2, $this->color($image, [148, 163, 184]));
    }

    /** Redondea el máximo del eje a un valor legible: 1, 2, 2.5, 5 o 10 × una potencia de diez. */
    private function niceMax(float $value): float
    {
        if ($value <= 0) {
            return 1.0;
        }

        $exponent = 10 ** floor(log10($value));
        $fraction = $value / $exponent;
        $nice = match (true) {
            $fraction <= 1 => 1,
            $fraction <= 2 => 2,
            $fraction <= 2.5 => 2.5,
            $fraction <= 5 => 5,
            default => 10,
        };

        return $nice * $exponent;
    }

    private function format(float $value): string
    {
        return number_format($value, fmod($value, 1.0) === 0.0 ? 0 : 2, ',', '.');
    }

    /** @param  array{0: int, 1: int, 2: int}|list<int>  $rgb */
    private function color(\GdImage $image, array $rgb, int $alpha = 0): int
    {
        return imagecolorallocatealpha($image, $rgb[0], $rgb[1], $rgb[2], $alpha);
    }

    private function font(bool $bold = false): string
    {
        return base_path('vendor/dompdf/dompdf/lib/fonts/'.($bold ? 'DejaVuSans-Bold.ttf' : 'DejaVuSans.ttf'));
    }

    /** @param  array{0: int, 1: int, 2: int}|list<int>  $rgb */
    private function text(\GdImage $image, int $size, int $x, int $y, array $rgb, string $font, string $text, float $angle = 0, string $align = 'left'): void
    {
        if ($align === 'right') {
            $box = imagettfbbox($size, 0, $font, $text);
            $x -= (int) abs($box[2] - $box[0]);
        }

        imagettftext($image, $size, $angle, $x, $y, $this->color($image, $rgb), $font, $text);
    }

    /** @param  array{0: int, 1: int, 2: int}|list<int>  $rgb */
    private function centeredText(\GdImage $image, int $size, float $cx, float $y, array $rgb, string $font, string $text): void
    {
        $box = imagettfbbox($size, 0, $font, $text);
        $width = abs($box[2] - $box[0]);
        $this->text($image, $size, (int) round($cx - $width / 2), (int) round($y), $rgb, $font, $text);
    }
}
