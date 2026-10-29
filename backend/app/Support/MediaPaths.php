<?php

namespace App\Support;

/** A stored image is a family: main + `_thumb`, each as .avif and the .webp fallback/original. Everything that deletes or prunes files must act on the whole family. */
final class MediaPaths
{
    public const EXTENSIONS = ['avif', 'webp'];

    /** Path without extension and without the `_thumb` suffix. */
    public static function stem(string $path): string
    {
        return preg_replace('/(_thumb)?\.[A-Za-z0-9]+$/', '', $path);
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

        return array_values(array_unique(array_merge($out, [$path])));
    }

    public static function thumbOf(string $path): string
    {
        return self::stem($path).'_thumb.'.pathinfo($path, PATHINFO_EXTENSION);
    }

    public static function withExtension(string $path, string $ext): string
    {
        return preg_replace('/\.[A-Za-z0-9]+$/', ".$ext", $path);
    }
}
