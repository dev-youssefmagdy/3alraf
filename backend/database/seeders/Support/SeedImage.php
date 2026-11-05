<?php

namespace Database\Seeders\Support;

use App\Services\Images\ImageOptimizer;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Format;

/**
 * Writes a seeded picture exactly like a real upload (ImageUploadService): AVIF main + 400px AVIF thumb through the ImageOptimizer profiles
 * (size-capped, metadata stripped), plus the WebP siblings used as the <picture> fallback. Transparent sources are flattened on white first.
 */
class SeedImage
{
    /**
     * @param  string  $profile  config('images.profiles') name for the main file (the thumb always uses `thumb`)
     * @return array{path:string,thumb_path:string} the .avif paths (the family also holds .webp siblings)
     */
    public static function save(\GdImage $img, string $dir, string $key, string $profile = 'product', bool $thumb = true): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'seedimg');
        try {
            $flat = imagecreatetruecolor(imagesx($img), imagesy($img));
            imagefill($flat, 0, 0, imagecolorallocate($flat, 255, 255, 255));
            imagecopy($flat, $img, 0, 0, 0, 0, imagesx($img), imagesy($img));
            imagepng($flat, $tmp, 1);

            $optimizer = new ImageOptimizer;
            $disk = Storage::disk(config('media.disk'));
            $files = ["$dir/$key.avif" => $optimizer->optimize($tmp, $profile)->bytes];
            $thumb && $files["$dir/{$key}_thumb.avif"] = $optimizer->optimize($tmp, 'thumb')->bytes;
            if (config('images.webp_fallback')) {
                $files["$dir/$key.webp"] = $optimizer->optimize($tmp, $profile, Format::WEBP)->bytes;
                $thumb && $files["$dir/{$key}_thumb.webp"] = $optimizer->optimize($tmp, 'thumb', Format::WEBP)->bytes;
            }
            foreach ($files as $path => $bytes) {
                $disk->put($path, $bytes) || throw new \RuntimeException("Could not write $path");
            }
        } finally {
            @unlink($tmp);
        }

        return ['path' => "$dir/$key.avif", 'thumb_path' => "$dir/{$key}_thumb.avif"];
    }
}
