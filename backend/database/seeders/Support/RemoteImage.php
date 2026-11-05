<?php

namespace Database\Seeders\Support;

use App\Support\MediaPaths;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Seeds real product photos: downloads remote images (in parallel) and stores them on the media disk like a real upload (SeedImage): AVIF main + 400px AVIF thumb + WebP fallbacks.
 * Images that cannot be downloaded or decoded are left out of the result; the seeder then shows the product's name card instead.
 */
class RemoteImage
{
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
            $path = "$dir/$key.avif";
            $thumb = "$dir/{$key}_thumb.avif";
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

    /** @return array{path:string,thumb_path:string}|null null when the bytes are not a decodable image */
    public static function store(string $dir, string $key, string $bytes): ?array
    {
        $img = @imagecreatefromstring($bytes);
        if (! $img) {
            return null;
        }
        try {
            return SeedImage::save($img, $dir, $key);
        } catch (\Throwable $e) {
            Log::warning('seed.remote_image_encode_failed', ['key' => $key, 'error' => $e->getMessage()]);

            return null;
        }
    }

    /** Copy an already stored image family under a new key (e.g. a category cover taken from one of its products): main + thumb, AVIF + WebP. */
    public static function copy(string $from, string $dir, string $key): ?string
    {
        $disk = Storage::disk(config('media.disk'));
        if (preg_match('#^https?://#i', $from)) {
            return $from;
        }
        if (! $disk->exists($from)) {
            return null;
        }
        $stem = MediaPaths::stem($from);
        foreach (MediaPaths::EXTENSIONS as $ext) {
            foreach (['', '_thumb'] as $suffix) {
                $src = "$stem$suffix.$ext";
                $dst = "$dir/$key$suffix.$ext";
                $disk->exists($src) && ! $disk->exists($dst) && $disk->copy($src, $dst);
            }
        }

        return "$dir/$key.".pathinfo($from, PATHINFO_EXTENSION);
    }

    /** Delete a stored main image and its whole family: thumb + AVIF/WebP siblings (remote URLs are left alone). */
    public static function deleteFamily(?string $path): void
    {
        if (! $path || preg_match('#^https?://#i', $path)) {
            return;
        }
        Storage::disk(config('media.disk'))->delete(MediaPaths::family($path));
    }
}
