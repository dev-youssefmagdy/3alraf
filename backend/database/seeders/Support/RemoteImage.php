<?php

namespace Database\Seeders\Support;

use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Seeds real product photos: downloads remote images (in parallel), stores them on the media disk as WebP main (<= 1000px) + 400px thumb.
 * Images that cannot be downloaded or decoded are left out of the result; the seeder then shows the product's name card instead.
 */
class RemoteImage
{
    private const MAX = 1000;

    private const THUMB = 400;

    private const UA = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36';

    /**
     * @param  array<string,string>  $urls  key (file stem, unique) => remote URL
     * @return array<string,array{path:string,thumb_path:string}>
     */
    public static function fetchMany(string $dir, array $urls, int $concurrency = 12): array
    {
        $disk = Storage::disk(config('media.disk'));
        $out = [];
        $todo = [];
        foreach ($urls as $key => $url) {
            $path = "$dir/$key.webp";
            $thumb = "$dir/{$key}_thumb.webp";
            if ($disk->exists($path) && $disk->exists($thumb)) {
                $out[$key] = ['path' => $path, 'thumb_path' => $thumb];
            } else {
                $todo[$key] = $url;
            }
        }

        foreach (array_chunk($todo, $concurrency, true) as $chunk) {
            $responses = Http::pool(fn (Pool $pool) => collect($chunk)->map(
                fn ($url, $key) => $pool->as((string) $key)->withHeaders(['User-Agent' => self::UA])->timeout(30)->retry(2, 500, throw: false)->get($url)
            )->all());
            foreach ($chunk as $key => $url) {
                $res = $responses[$key] ?? null;
                $stored = ($res instanceof Response && $res->successful()) ? self::store($dir, (string) $key, $res->body()) : null;
                if ($stored) {
                    $out[$key] = $stored;
                } else {
                    // Not stored: the caller shows the product name card instead (a hot-linked URL that refused us would most likely be broken for shoppers too).
                    Log::warning('seed.remote_image_failed', ['key' => $key, 'url' => $url]);
                }
            }
        }

        return $out;
    }

    /** @return array{path:string,thumb_path:string}|null */
    public static function store(string $dir, string $key, string $bytes): ?array
    {
        $img = @imagecreatefromstring($bytes);
        if (! $img) {
            return null;
        }
        $disk = Storage::disk(config('media.disk'));
        $path = "$dir/$key.webp";
        $thumb = "$dir/{$key}_thumb.webp";
        $disk->put($path, self::encode(self::fit($img, self::MAX), 80));
        $disk->put($thumb, self::encode(self::fit($img, self::THUMB), 75));

        return ['path' => $path, 'thumb_path' => $thumb];
    }

    /** Copy an already stored image family under a new key (e.g. a category cover taken from one of its products). */
    public static function copy(string $from, string $dir, string $key): ?string
    {
        $disk = Storage::disk(config('media.disk'));
        if (preg_match('#^https?://#i', $from)) {
            return $from;
        }
        if (! $disk->exists($from)) {
            return null;
        }
        $to = "$dir/$key.webp";
        $disk->exists($to) || $disk->copy($from, $to);

        return $to;
    }

    /** Delete a stored main image and its thumb (remote URLs are left alone). */
    public static function deleteFamily(?string $path): void
    {
        if (! $path || preg_match('#^https?://#i', $path)) {
            return;
        }
        Storage::disk(config('media.disk'))->delete([$path, preg_replace('/\.webp$/', '_thumb.webp', $path)]);
    }

    /** Scale down to fit a square box (never up), on a white background (product shots are usually transparent or white). */
    private static function fit(\GdImage $src, int $box): \GdImage
    {
        $w = imagesx($src);
        $h = imagesy($src);
        $k = min(1, $box / max($w, $h));
        $nw = max(1, (int) round($w * $k));
        $nh = max(1, (int) round($h * $k));
        $dst = imagecreatetruecolor($nw, $nh);
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);

        return $dst;
    }

    private static function encode(\GdImage $img, int $quality): string
    {
        ob_start();
        imagewebp($img, null, $quality);

        return (string) ob_get_clean();
    }
}
