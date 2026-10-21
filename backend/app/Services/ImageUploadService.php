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

/** Stores uploads on the `public` disk as webp (max 1600px) + 400px thumbnail. */
class ImageUploadService
{
    public const DISK = 'public';

    private ImageManager $manager;

    public function __construct()
    {
        $this->manager = new ImageManager(new Driver);
    }

    /** @return array{path:string,thumb_path:string} */
    public function store(UploadedFile $file, string $dir): array
    {
        $name = Str::uuid()->toString();
        $dir = trim($dir, '/').'/'.date('Y/m');
        $disk = Storage::disk(self::DISK);

        $img = $this->manager->decodePath($file->getRealPath());
        $disk->put("$dir/$name.webp", (string) (clone $img)->scaleDown(1600, 1600)->encodeUsingFormat(Format::WEBP, quality: 85));
        $disk->put("$dir/{$name}_thumb.webp", (string) (clone $img)->cover(400, 400)->encodeUsingFormat(Format::WEBP, quality: 80));

        return ['path' => "$dir/$name.webp", 'thumb_path' => "$dir/{$name}_thumb.webp"];
    }

    /** Store a single file for a plain column (logo/category image); returns path. */
    public function storeSingle(UploadedFile $file, string $dir, ?string $old = null): string
    {
        $this->deletePath($old);

        return $this->store($file, $dir)['path'];
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
            Storage::disk(self::DISK)->delete([$path, str_replace('.webp', '_thumb.webp', $path)]);
        }
    }
}
