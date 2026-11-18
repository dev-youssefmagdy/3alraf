<?php

namespace App\Observers;

use App\Enums\ImageEmbeddingSource;
use App\Models\Image;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\ImageSearch\ImageEmbeddingService;

/** Keeps the image-search index in step with product / variant gallery images: new or replaced ⇒ queued, deleted ⇒ embedding row removed. */
class ImageEmbeddingObserver
{
    public function __construct(private ImageEmbeddingService $embeddings) {}

    private function indexed(Image $image): bool
    {
        return in_array($image->imageable_type, [(new Product)->getMorphClass(), (new ProductVariant)->getMorphClass()], true);
    }

    public function created(Image $image): void
    {
        $this->indexed($image) && $this->embeddings->sourceChanged(ImageEmbeddingSource::IMAGE, $image->id);
    }

    public function updated(Image $image): void
    {
        if ($image->wasChanged(['path', 'imageable_id', 'imageable_type'])) {
            $this->indexed($image)
                ? $this->embeddings->sourceChanged(ImageEmbeddingSource::IMAGE, $image->id)
                : $this->embeddings->forget(ImageEmbeddingSource::IMAGE, [$image->id]);
        }
    }

    public function deleted(Image $image): void
    {
        $this->embeddings->forget(ImageEmbeddingSource::IMAGE, [$image->id]);
    }
}
