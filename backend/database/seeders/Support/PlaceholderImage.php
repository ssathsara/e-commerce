<?php

namespace Database\Seeders\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Generates tasteful gradient placeholder images with GD so the demo catalogue
 * has real image files (stored via Laravel Storage) without any network access.
 */
final class PlaceholderImage
{
    /** Common TrueType font locations (Windows, Debian/Alpine, macOS). */
    private const FONT_CANDIDATES = [
        'C:/Windows/Fonts/segoeuib.ttf',
        'C:/Windows/Fonts/arialbd.ttf',
        '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
        '/usr/share/fonts/dejavu/DejaVuSans-Bold.ttf',
        '/usr/share/fonts/ttf-dejavu/DejaVuSans-Bold.ttf',
        '/System/Library/Fonts/Supplemental/Arial Bold.ttf',
    ];

    public static function create(string $directory, string $label, int $hue, int $width = 800, int $height = 800, int $variant = 0): string
    {
        $image = imagecreatetruecolor($width, $height);

        // Diagonal-ish vertical gradient between two tones of the same hue.
        [$r1, $g1, $b1] = self::hsl($hue, 0.55, 0.93 - $variant * 0.03);
        [$r2, $g2, $b2] = self::hsl(($hue + 25) % 360, 0.50, 0.80 - $variant * 0.04);

        for ($y = 0; $y < $height; $y++) {
            $t = $y / $height;
            $color = imagecolorallocate(
                $image,
                (int) ($r1 + ($r2 - $r1) * $t),
                (int) ($g1 + ($g2 - $g1) * $t),
                (int) ($b1 + ($b2 - $b1) * $t),
            );
            imageline($image, 0, $y, $width, $y, $color);
        }

        // Soft decorative circle.
        [$cr, $cg, $cb] = self::hsl($hue, 0.45, 0.70);
        $circle = imagecolorallocatealpha($image, $cr, $cg, $cb, 80);
        imagefilledellipse($image, (int) ($width * 0.72), (int) ($height * 0.30), (int) ($width * 0.7), (int) ($width * 0.7), $circle);

        [$tr, $tg, $tb] = self::hsl($hue, 0.45, 0.25);
        $textColor = imagecolorallocate($image, $tr, $tg, $tb);
        self::drawLabel($image, $label, $textColor, $width, $height);

        $path = trim($directory, '/').'/'.Str::random(32).'.jpg';

        ob_start();
        imagejpeg($image, null, 85);
        Storage::disk('public')->put($path, (string) ob_get_clean());
        imagedestroy($image);

        return $path;
    }

    private static function drawLabel(\GdImage $image, string $label, int $color, int $width, int $height): void
    {
        $font = collect(self::FONT_CANDIDATES)->first(fn ($f) => is_file($f));
        $lines = explode("\n", wordwrap($label, 16, "\n", true));

        if ($font !== null) {
            $size = (int) ($width / 16);
            $lineHeight = (int) ($size * 1.5);
            $y = (int) ($height * 0.62);

            foreach (array_slice($lines, 0, 3) as $line) {
                $box = imagettfbbox($size, 0, $font, $line);
                $x = (int) (($width - ($box[2] - $box[0])) / 2);
                imagettftext($image, $size, 0, $x, $y, $color, $font, $line);
                $y += $lineHeight;
            }

            return;
        }

        // Fallback: built-in bitmap font (no TTF available).
        $y = (int) ($height * 0.6);
        foreach (array_slice($lines, 0, 3) as $line) {
            $x = (int) (($width - imagefontwidth(5) * strlen($line)) / 2);
            imagestring($image, 5, $x, $y, $line, $color);
            $y += 24;
        }
    }

    /** @return array{int, int, int} */
    private static function hsl(int $h, float $s, float $l): array
    {
        $c = (1 - abs(2 * $l - 1)) * $s;
        $x = $c * (1 - abs(fmod($h / 60, 2) - 1));
        $m = $l - $c / 2;

        [$r, $g, $b] = match (true) {
            $h < 60 => [$c, $x, 0],
            $h < 120 => [$x, $c, 0],
            $h < 180 => [0, $c, $x],
            $h < 240 => [0, $x, $c],
            $h < 300 => [$x, 0, $c],
            default => [$c, 0, $x],
        };

        return [(int) (($r + $m) * 255), (int) (($g + $m) * 255), (int) (($b + $m) * 255)];
    }
}
