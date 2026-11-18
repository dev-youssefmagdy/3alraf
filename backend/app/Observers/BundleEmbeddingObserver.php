<?php

namespace App\Observers;

use App\Enums\ImageEmbeddingSource;
use App\Models\Bundle;
use App\Services\ImageSearch\ImageEmbeddingService;

/** Bundle image (single `image` column) in the image-search index: set / replaced ⇒ queued, removed or bundle purged ⇒ row deleted. */
class BundleEmbeddingObserver
{
    public function __construct(private ImageEmbeddingService $embeddings) {}

    public function saved(Bundle $bundle): void
    {
        if (! $bundle->wasRecentlyCreated && ! $bundle->wasChanged('image')) {
            return;
        }
        $bundle->image
            ? $this->embeddings->sourceChanged(ImageEmbeddingSource::BUNDLE, $bundle->id)
            : $this->embeddings->forget(ImageEmbeddingSource::BUNDLE, [$bundle->id]);
    }

    /** Soft delete keeps the row (restore brings it back); search hides trashed bundles. */
    public function deleted(Bundle $bundle): void
    {
        $this->embeddings->visibilityChanged();
    }

    public function restored(Bundle $bundle): void
    {
        $this->embeddings->visibilityChanged();
        $bundle->image && $this->embeddings->sourceChanged(ImageEmbeddingSource::BUNDLE, $bundle->id);
    }

    public function forceDeleted(Bundle $bundle): void
    {
        $this->embeddings->forget(ImageEmbeddingSource::BUNDLE, [$bundle->id]);
    }
}
