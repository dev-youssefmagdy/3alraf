<?php

namespace App\Services;

use App\DTOs\ProductMediaOrderData;
use App\DTOs\ProductVideoData;
use App\Models\Product;
use App\Models\ProductVideo;
use App\Repositories\Contracts\ProductVideoRepositoryInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Public product gallery videos on the `media` disk. Nothing here touches the private social-media library. */
class ProductVideoService
{
    public function __construct(private ProductVideoRepositoryInterface $videos, private ImageUploadService $images) {}

    /** Effective upload limit: the configured one, capped by PHP's upload_max_filesize / post_max_size. */
    public static function maxKb(): int
    {
        return (int) floor(min((int) config('media.product_video.max_mb') * 1024, UploadedFile::getMaxFilesize() / 1024));
    }

    public static function maxMb(): int
    {
        return (int) floor(self::maxKb() / 1024);
    }

    public function upload(Product $product, ProductVideoData $dto): ProductVideo
    {
        $poster = $dto->poster ? $this->storePoster($dto->poster) : null;
        $disk = ImageUploadService::disk();
        $mime = (string) $dto->video->getMimeType();
        $path = null;
        try {
            // the name comes from the server and the extension from the detected content, never from the client
            $path = $disk->putFileAs('catalog/videos/'.date('Y/m'), $dto->video, Str::lower(Str::random(40)).'.'.config('media.product_video.mimes')[$mime]);
            $path || throw new \RuntimeException('Could not store the product video');

            return $this->videos->create([
                'product_id' => $product->id, 'path' => $path, 'poster_path' => $poster['path'] ?? null, 'poster_thumb_path' => $poster['thumb_path'] ?? null,
                'mime' => $mime, 'size_bytes' => (int) $dto->video->getSize(), 'sort_order' => $this->videos->lastMediaOrder($product) + 1,
            ]);
        } catch (\Throwable $e) {
            $path && $disk->delete($path);
            $this->images->deletePath($poster['path'] ?? null);
            throw $e;
        }
    }

    public function delete(ProductVideo $video): void
    {
        ImageUploadService::disk()->delete($video->path);
        $this->images->deletePath($video->poster_path);
        $this->videos->delete($video);
    }

    public function reorder(Product $product, ProductMediaOrderData $dto): void
    {
        DB::transaction(fn () => $this->videos->applyOrder($product, $dto->items));
    }

    /** @return array{path:string,thumb_path:string} */
    private function storePoster(UploadedFile $file): array
    {
        try {
            return $this->images->store($file, 'products');
        } catch (ValidationException $e) {
            throw ValidationException::withMessages(['poster' => collect($e->errors())->flatten()->first()]);
        }
    }
}
