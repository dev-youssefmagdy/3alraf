<?php

namespace App\Services\Images;

use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Format;
use Intervention\Image\Interfaces\ImageInterface;
use Intervention\Image\ImageManager;
use Throwable;

/**
 * Pure encoder: file in, AVIF (or WebP fallback) bytes out. No storage, no DB.
 * Decodes with GD (auto-orients; metadata incl. GPS is dropped by re-encoding; alpha is kept), resizes per profile
 * without upscaling, then walks scale × quality until the encoded size fits the profile limit.
 */
class ImageOptimizer
{
    private ImageManager $manager;

    public function __construct()
    {
        $this->manager = new ImageManager(new Driver);
    }

    /** Whether this PHP build can really encode AVIF (the package alone is not enough). */
    public static function avifSupported(): bool
    {
        return function_exists('imageavif') && (gd_info()['AVIF Support'] ?? false);
    }

    /** @return array{max_w:int,max_h:int,fit:string,max_bytes:int} */
    public static function profile(string $name): array
    {
        $p = config("images.profiles.$name") ?? config('images.profiles.default');
        $p['max_bytes'] = (int) ($p['max_bytes'] ?? config('images.max_bytes'));

        return $p;
    }

    /** Profile for a stored path, from its upload directory (tenants/{id}/{dir}/… or catalog/{dir}/… or {dir}/…) and `_thumb` suffix. */
    public static function profileForPath(string $path): string
    {
        if (preg_match('/_thumb\.[A-Za-z0-9]+$/', $path)) {
            return 'thumb';
        }
        $parts = explode('/', ltrim(preg_replace('#^(tenants/\d+|catalog)/#', '', $path), '/'));

        return config('images.dirs.'.$parts[0], 'default');
    }

    /**
     * @throws InvalidImageException for unreadable, unsupported, animated, oversized or corrupt input
     */
    public function optimize(string $file, string $profile, Format $format = Format::AVIF): OptimizedImage
    {
        $info = $this->inspect($file);
        $p = self::profile($profile);
        try {
            $img = $this->manager->decodePath($file);
        } catch (Throwable $e) {
            throw new InvalidImageException('Image could not be decoded: '.$e->getMessage(), 0, $e);
        }

        $attempts = 0;
        $best = null;
        foreach (config('images.scale_steps') as $scale) {
            $work = $this->resize(clone $img, $p, (float) $scale);
            foreach (config('images.qualities') as $q) {
                $attempts++;
                $bytes = (string) $work->encodeUsingFormat($format, quality: (int) $q);
                if ($best === null || strlen($bytes) < strlen($best[0])) {
                    $best = [$bytes, $work->width(), $work->height(), $q, $scale];
                }
                if (strlen($bytes) <= $p['max_bytes']) {
                    return $this->result([$bytes, $work->width(), $work->height(), $q, $scale], $p, $format, $info, $attempts);
                }
            }
        }

        return $this->result($best, $p, $format, $info, $attempts); // flagged: caller sees withinLimit() === false
    }

    /** Validate the real content (never the extension). @return array{mime:string,bytes:int,w:int,h:int} */
    public function inspect(string $file): array
    {
        if (! is_file($file) || ! is_readable($file)) {
            throw new InvalidImageException('Source file is missing or unreadable.');
        }
        $bytes = (int) filesize($file);
        if ($bytes === 0 || $bytes > config('images.max_source_bytes')) {
            throw new InvalidImageException("Source size {$bytes} bytes is outside the accepted range.");
        }
        $i = @getimagesize($file);
        if ($i === false || ! in_array($i['mime'] ?? '', config('images.allowed_mimes'), true)) {
            throw new InvalidImageException('Not a supported raster image ('.($i['mime'] ?? 'unknown').').');
        }
        if ($i[0] * $i[1] > config('images.max_source_pixels')) {
            throw new InvalidImageException("Image dimensions {$i[0]}x{$i[1]} exceed the pixel limit.");
        }
        if ($i['mime'] === 'image/gif' && $this->isAnimatedGif($file)) {
            throw new InvalidImageException('Animated GIFs are not converted (animation would be lost).');
        }

        return ['mime' => $i['mime'], 'bytes' => $bytes, 'w' => $i[0], 'h' => $i[1]];
    }

    private function resize(ImageInterface $img, array $p, float $scale): ImageInterface
    {
        $w = max(1, (int) round($p['max_w'] * $scale));
        $h = max(1, (int) round($p['max_h'] * $scale));

        return $p['fit'] === 'cover' ? $img->coverDown($w, $h) : $img->scaleDown($w, $h);
    }

    private function result(array $best, array $p, Format $format, array $info, int $attempts): OptimizedImage
    {
        [$bytes, $w, $h, $q, $scale] = $best;

        return new OptimizedImage($bytes, $w, $h, (int) $q, (float) $scale, $p['max_bytes'], $format->mediaType()->value, $info['bytes'], $attempts);
    }

    private function isAnimatedGif(string $file): bool
    {
        $h = fopen($file, 'rb');
        $frames = 0;
        while (! feof($h) && $frames < 2) {
            $chunk = fread($h, 1024 * 100);
            $frames += preg_match_all('#\x00\x21\xF9\x04.{4}\x00[\x2C\x21]#s', $chunk);
        }
        fclose($h);

        return $frames > 1;
    }
}
