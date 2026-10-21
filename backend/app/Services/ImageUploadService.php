<?php

namespace App\Services;

use App\Models\Image;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Format;
use Intervention\Image\ImageManager;

/** Stores uploads on the `media` (CDN) disk as webp (max 1600px) + 400px thumbnail, names = content hash + random suffix (unique per upload => immutable for CDN caching and never shared between two owners, so deleting one owner's file cannot break another), namespaced tenants/{id}/ or catalog/. */
class ImageUploadService
{
    private ImageManager $manager;

    public function __construct()
    {
        $this->manager = new ImageManager(new Driver);
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

    /** @return array{path:string,thumb_path:string} */
    public function store(UploadedFile $file, string $dir): array
    {
        $dir = $this->namespace().trim($dir, '/').'/'.date('Y/m');
        $disk = self::disk();

        $img = $this->manager->decodePath($file->getRealPath());
        $main = (string) (clone $img)->scaleDown(1600, 1600)->encodeUsingFormat(Format::WEBP, quality: 85);
        $name = substr(hash('sha256', $main), 0, 32).'-'.Str::lower(Str::random(8)); // unique per upload: safe for `immutable` caching, never shared between owners
        $disk->put("$dir/$name.webp", $main);
        $disk->put("$dir/{$name}_thumb.webp", (string) (clone $img)->cover(400, 400)->encodeUsingFormat(Format::WEBP, quality: 80));

        return ['path' => "$dir/$name.webp", 'thumb_path' => "$dir/{$name}_thumb.webp"];
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
            self::disk()->delete([$path, str_replace('.webp', '_thumb.webp', $path)]);
        }
    }
}
