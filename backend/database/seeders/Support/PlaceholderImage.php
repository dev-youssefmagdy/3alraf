<?php

namespace Database\Seeders\Support;

use Illuminate\Support\Facades\Storage;

/**
 * Seeded pictures onto the media disk as AVIF + WebP fallback (SeedImage, same family as a real upload), no network:
 * a bundled free photo when database/seeders/assets/stock has one for the key (demo catalog products / categories / banners,
 * CC0 or public domain, provenance in SOURCES.json), otherwise a local gradient name card.
 */
class PlaceholderImage
{
    private const FONT = '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf';

    /** Media-disk folder => stock folder; other folders (e.g. the real gift catalog) never get a stock photo. */
    public const STOCK_BUCKETS = ['catalog/products' => 'products', 'catalog/categories' => 'categories', 'banners' => 'banners'];

    /** @return array{path:string,thumb_path:string} */
    public static function make(string $dir, string $key, string $label, int $w = 800, int $h = 800, int $seed = 0): array
    {
        $path = "$dir/$key.avif";
        $thumb = "$dir/{$key}_thumb.avif";
        $disk = Storage::disk(config('media.disk'));
        if ($disk->exists($path) && $disk->exists($thumb)) {
            return ['path' => $path, 'thumb_path' => $thumb];
        }

        return SeedImage::save(self::stock($dir, $key) ?? self::render($w, $h, (crc32($key) + $seed * 47) % 360, $label), $dir, $key);
    }

    public static function banner(string $dir, string $key, string $label, int $w = 1600, int $h = 500, int $seed = 0): string
    {
        $path = "$dir/$key.avif";
        if (! Storage::disk(config('media.disk'))->exists($path)) {
            SeedImage::save(self::stock($dir, $key) ?? self::render($w, $h, (crc32($key) + $seed * 61) % 360, $label), $dir, $key, 'banner', thumb: false);
        }

        return $path;
    }

    /** Bundled photo file for a seeded key (banner keys `demo-{i}-{locale}` share one photo per banner), or null. */
    public static function stockFile(string $dir, string $key): ?string
    {
        $bucket = self::STOCK_BUCKETS[$dir] ?? null;
        if ($bucket === 'banners') {
            $key = preg_match('/^demo-(\d+)-/', $key, $m) ? $m[1] : $key;
        }
        $file = $bucket ? database_path("seeders/assets/stock/$bucket/$key.webp") : null;

        return $file && is_file($file) ? $file : null;
    }

    public static function stock(string $dir, string $key): ?\GdImage
    {
        $file = self::stockFile($dir, $key);

        return $file ? (@imagecreatefromwebp($file) ?: null) : null;
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

    private static function hsl(int $h, float $s, float $l): array
    {
        $c = (1 - abs(2 * $l - 1)) * $s;
        $x = $c * (1 - abs(fmod($h / 60, 2) - 1));
        $m = $l - $c / 2;
        [$r, $g, $b] = match (intdiv($h, 60)) { 0 => [$c, $x, 0], 1 => [$x, $c, 0], 2 => [0, $c, $x], 3 => [0, $x, $c], 4 => [$x, 0, $c], default => [$c, 0, $x] };

        return [(int) (($r + $m) * 255), (int) (($g + $m) * 255), (int) (($b + $m) * 255)];
    }
}
