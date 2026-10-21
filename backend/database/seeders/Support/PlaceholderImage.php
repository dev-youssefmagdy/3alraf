<?php

namespace Database\Seeders\Support;

use Illuminate\Support\Facades\Storage;

/** Generates local gradient placeholder images (GD, no network) onto the media disk as webp. */
class PlaceholderImage
{
    private const FONT = '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf';

    /** @return array{path:string,thumb_path:string} */
    public static function make(string $dir, string $key, string $label, int $w = 800, int $h = 800, int $seed = 0): array
    {
        $path = "$dir/$key.webp";
        $thumb = "$dir/{$key}_thumb.webp";
        $disk = Storage::disk(config('media.disk'));
        if ($disk->exists($path) && $disk->exists($thumb)) {
            return ['path' => $path, 'thumb_path' => $thumb];
        }
        $hue = (crc32($key) + $seed * 47) % 360;
        $img = self::render($w, $h, $hue, $label);
        $disk->put($path, self::encode($img));
        $t = imagecreatetruecolor(400, 400);
        imagecopyresampled($t, $img, 0, 0, 0, 0, 400, 400, $w, $h);
        $disk->put($thumb, self::encode($t));

        return ['path' => $path, 'thumb_path' => $thumb];
    }

    public static function banner(string $dir, string $key, string $label, int $w = 1600, int $h = 500, int $seed = 0): string
    {
        $path = "$dir/$key.webp";
        $disk = Storage::disk(config('media.disk'));
        if (! $disk->exists($path)) {
            $disk->put($path, self::encode(self::render($w, $h, (crc32($key) + $seed * 61) % 360, $label)));
        }

        return $path;
    }

    private static function render(int $w, int $h, int $hue, string $label): \GdImage
    {
        $img = imagecreatetruecolor($w, $h);
        [$r1, $g1, $b1] = self::hsl($hue, .55, .62);
        [$r2, $g2, $b2] = self::hsl(($hue + 40) % 360, .6, .38);
        for ($y = 0; $y < $h; $y++) {
            $k = $y / $h;
            imageline($img, 0, $y, $w, $y, imagecolorallocate($img, (int) ($r1 + ($r2 - $r1) * $k), (int) ($g1 + ($g2 - $g1) * $k), (int) ($b1 + ($b2 - $b1) * $k)));
        }
        imagealphablending($img, true);
        $soft = imagecolorallocatealpha($img, 255, 255, 255, 105);
        imagefilledellipse($img, (int) ($w * .78), (int) ($h * .25), (int) ($h * .8), (int) ($h * .8), $soft);
        imagefilledellipse($img, (int) ($w * .15), (int) ($h * .85), (int) ($h * .55), (int) ($h * .55), $soft);

        $label = trim(preg_replace('/[^\x20-\x7E]/', '', $label)) ?: '3alraf';
        $white = imagecolorallocate($img, 255, 255, 255);
        $size = max(14, (int) ($h * ($w > $h * 1.5 ? .12 : .06)));
        if (is_file(self::FONT) && function_exists('imagettftext')) {
            $lines = explode("\n", wordwrap($label, $w > $h * 1.5 ? 36 : 16, "\n", true));
            $lh = (int) ($size * 1.5);
            $y = (int) (($h - $lh * count($lines)) / 2 + $size);
            foreach ($lines as $line) {
                $box = imagettfbbox($size, 0, self::FONT, $line);
                imagettftext($img, $size, 0, (int) (($w - ($box[2] - $box[0])) / 2), $y, $white, self::FONT, $line);
                $y += $lh;
            }
        }

        return $img;
    }

    private static function encode(\GdImage $img): string
    {
        ob_start();
        imagewebp($img, null, 80);

        return (string) ob_get_clean();
    }

    private static function hsl(int $h, float $s, float $l): array
    {
        $c = (1 - abs(2 * $l - 1)) * $s;
        $x = $c * (1 - abs(fmod($h / 60, 2) - 1));
        $m = $l - $c / 2;
        [$r, $g, $b] = match (intdiv($h, 60)) { 0 => [$c, $x, 0], 1 => [$x, $c, 0], 2 => [0, $c, $x], 3 => [0, $x, $c], 4 => [$x, 0, $c], default => [$c, 0, $x] };

        return [(int) (($r + $m) * 255), (int) (($g + $m) * 255), (int) (($b + $m) * 255)];
    }
}
