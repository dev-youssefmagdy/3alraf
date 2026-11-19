<?php

namespace App\Support;

/** A stored image is a family: main + `_thumb` (each as .avif and the .webp fallback/original) + the small `_w{width}.avif` sizes for srcset. Everything that deletes or prunes files must act on the whole family. */
final class MediaPaths
{
    public const EXTENSIONS = ['avif', 'webp'];

    /** Small square sizes stored beside the 400px thumbnail. */
    public const WIDTHS = [128, 256];

    /** `sizes` of a product-rail card image (also used to preload the first one, so both must match). */
    public const RAIL_SIZES = '(min-width: 1280px) 230px, (min-width: 1024px) 22vw, (min-width: 768px) 28vw, 35vw';

    /** Path without extension and without the `_thumb` / `_w{width}` suffix. */
    public static function stem(string $path): string
    {
        return preg_replace('/(_thumb|_w\d+)?\.[A-Za-z0-9]+$/', '', $path);
    }

    /** @return string[] every file that can belong to this image */
    public static function family(?string $path): array
    {
        if (! $path) {
            return [];
        }
        $stem = self::stem($path);
        $out = [];
        foreach (self::EXTENSIONS as $ext) {
            $out[] = "$stem.$ext";
            $out[] = "{$stem}_thumb.$ext";
        }

        foreach (self::WIDTHS as $width) {
            $out[] = "{$stem}_w$width.avif";
        }

        return array_values(array_unique(array_merge($out, [$path])));
    }

    public static function thumbOf(string $path): string
    {
        return self::stem($path).'_thumb.'.pathinfo($path, PATHINFO_EXTENSION);
    }

    public static function variantOf(string $path, int $width): string
    {
        return self::stem($path)."_w$width.avif";
    }

    public static function withExtension(string $path, string $ext): string
    {
        return preg_replace('/\.[A-Za-z0-9]+$/', ".$ext", $path);
    }
}
