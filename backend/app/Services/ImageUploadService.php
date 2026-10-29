<?php

namespace App\Services;

use App\Models\Image;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Services\Images\ImageOptimizer;
use App\Services\Images\InvalidImageException;
use App\Support\MediaPaths;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Format;

/** Stores uploads on the `media` (CDN) disk as AVIF (profile-sized, <= the profile byte limit) + 400px AVIF thumbnail, plus a WebP fallback sibling for browsers without AVIF. Names = content hash + random suffix (unique per upload => immutable for CDN caching and never shared between two owners, so deleting one owner's file cannot break another), namespaced tenants/{id}/ or catalog/. All encoding lives in Images\ImageOptimizer. */
class ImageUploadService
{
    public function __construct(private ?ImageOptimizer $optimizer = null)
    {
        $this->optimizer ??= new ImageOptimizer;
    }

    public static function disk(): \Illuminate\Contracts\Filesystem\Filesystem
    {
        return Storage::disk(config('media.disk'));
    }

    /** Tenant uploads live under tenants/{id}/, central (no tenant) ones under catalog/. */
    private function namespace(): string
    {
        $t = current_tenant();

        return $t ? "tenants/{$t->id}/" : 'catalog/';
    }

    /**
     * @return array{path:string,thumb_path:string}
     *
     * @throws ValidationException when the file is not a safe, supported static image
     */
    public function store(UploadedFile $file, string $dir): array
    {
        $profile = config("images.dirs.$dir", 'default');
        try {
            $main = $this->optimizer->optimize($file->getRealPath(), $profile);
            $thumb = $this->optimizer->optimize($file->getRealPath(), 'thumb');
            $fallback = config('images.webp_fallback') ? [
                $this->optimizer->optimize($file->getRealPath(), $profile, Format::WEBP),
                $this->optimizer->optimize($file->getRealPath(), 'thumb', Format::WEBP),
            ] : [];
        } catch (InvalidImageException $e) {
            throw ValidationException::withMessages(['image' => $e->getMessage()]);
        }
        foreach ([$main, $thumb] as $r) {
            $r->withinLimit() || Log::warning('image.over_limit', ['dir' => $dir, 'bytes' => $r->size(), 'limit' => $r->limit, 'quality' => $r->quality, 'scale' => $r->scale]);
        }

        $dir = $this->namespace().trim($dir, '/').'/'.date('Y/m');
        $name = substr(hash('sha256', $main->bytes), 0, 32).'-'.Str::lower(Str::random(8)); // unique per upload: safe for `immutable` caching, never shared between owners
        $files = ["$dir/$name.avif" => $main->bytes, "$dir/{$name}_thumb.avif" => $thumb->bytes];
        if ($fallback) {
            $files["$dir/$name.webp"] = $fallback[0]->bytes;
            $files["$dir/{$name}_thumb.webp"] = $fallback[1]->bytes;
        }

        $disk = self::disk();
        try {
            foreach ($files as $path => $bytes) {
                $disk->put($path, $bytes) || throw new \RuntimeException("Could not write $path");
            }
        } catch (\Throwable $e) {
            $disk->delete(array_keys($files)); // never leave half a family behind
            throw $e;
        }

        return ['path' => "$dir/$name.avif", 'thumb_path' => "$dir/{$name}_thumb.avif"];
    }

    /** Store a single file for a plain column (logo/category image); returns path. */
    public function storeSingle(UploadedFile $file, string $dir, ?string $old = null): string
    {
        $path = $this->store($file, $dir)['path']; // store first: a failing upload must not cost the old file
        $this->deletePath($old);

        return $path;
    }

    /** Attach uploaded files to any model using HasImages. @param  UploadedFile[]  $files */
    public function attach(Model $model, array $files, string $dir = 'products'): void
    {
        $order = (int) $model->images()->max('sort_order');
        foreach ($files as $file) {
            $model->images()->create($this->store($file, $dir) + ['sort_order' => ++$order]);
        }
    }

    /** @param  int[]  $ids */
    public function removeByIds(Model $model, array $ids): void
    {
        $model->images()->whereIn('id', $ids)->get()->each(fn (Image $i) => $this->delete($i));
    }

    public function delete(Image $image): void
    {
        $this->deletePath($image->path);
        $this->deletePath($image->thumb_path);
        $image->delete();
    }

    public function deletePath(?string $path): void
    {
        if ($path) {
            self::disk()->delete(MediaPaths::family($path));
        }
    }
}
