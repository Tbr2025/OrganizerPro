<?php

declare(strict_types=1);

namespace App\Services\Poster\Presets;

/**
 * Draws a theme's background: a diagonal gradient, a soft glow, angled light
 * bands and a darker foot so footer text and sponsor logos stay readable.
 * Deterministic — the same theme and size always give the same picture.
 */
class PresetBackgroundGenerator
{
    public function render(array $theme, int $width, int $height): \GdImage
    {
        $img = imagecreatetruecolor($width, $height);
        imagealphablending($img, true);

        [$r1, $g1, $b1] = $this->rgb($theme['from']);
        [$r2, $g2, $b2] = $this->rgb($theme['to']);

        // Diagonal gradient, drawn as 2-px bands across the diagonal.
        $span = $width + $height;
        for ($d = 0; $d < $span; $d += 2) {
            $t = $d / $span;
            $t = $t * $t * (3 - 2 * $t); // smoothstep: deeper corners
            $c = imagecolorallocate($img, (int) ($r1 + ($r2 - $r1) * $t), (int) ($g1 + ($g2 - $g1) * $t), (int) ($b1 + ($b2 - $b1) * $t));
            imageline($img, $d, 0, $d - $height, $height, $c);
            imageline($img, $d + 1, 0, $d + 1 - $height, $height, $c);
        }

        // Glow, top right.
        [$gr, $gg, $gb] = $this->rgb($theme['glow']);
        $cx = (int) ($width * 0.82);
        $cy = (int) ($height * 0.12);
        $max = (int) (max($width, $height) * 0.9);
        for ($rad = $max; $rad > 0; $rad -= 12) {
            $alpha = (int) (127 - 18 * (1 - $rad / $max) ** 2);
            imagefilledellipse($img, $cx, $cy, $rad, $rad, imagecolorallocatealpha($img, $gr, $gg, $gb, max(105, $alpha)));
        }

        // Angled light bands.
        $band = imagecolorallocatealpha($img, 255, 255, 255, $theme['light'] ? 110 : 118);
        foreach ([0.18, 0.46, 0.74] as $i => $at) {
            $x0 = (int) ($width * $at);
            $w = (int) ($width * (0.05 + 0.03 * $i));
            imagefilledpolygon($img, [$x0, 0, $x0 + $w, 0, $x0 + $w - (int) ($height * 0.55), $height, $x0 - (int) ($height * 0.55), $height], $band);
        }

        // Fine diagonal hatching for texture.
        $hatch = imagecolorallocatealpha($img, 255, 255, 255, 124);
        for ($x = -$height; $x < $width; $x += 18) {
            imageline($img, $x, $height, $x + $height, 0, $hatch);
        }

        // Darker foot (lighter for the light theme) under the footer band.
        $footTop = (int) ($height * 0.78);
        for ($y = $footTop; $y < $height; $y++) {
            $t = ($y - $footTop) / max(1, $height - $footTop);
            $c = $theme['light']
                ? imagecolorallocatealpha($img, 255, 255, 255, (int) (127 - 70 * $t))
                : imagecolorallocatealpha($img, 0, 0, 0, (int) (127 - 80 * $t));
            imageline($img, 0, $y, $width, $y, $c);
        }

        // Accent rule along the very bottom.
        [$ar, $ag, $ab] = $this->rgb($theme['accent']);
        imagefilledrectangle($img, 0, $height - max(6, (int) ($height * 0.006)), $width, $height, imagecolorallocate($img, $ar, $ag, $ab));

        return $img;
    }

    private function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }
}
