<?php

namespace App\Observers;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\ImageSearch\ImageEmbeddingService;
use Illuminate\Database\Eloquent\Model;

/** Product / variant soft delete or restore changes which embeddings image search may return: invalidate its cached index. */
class CatalogVisibilityObserver
{
    public function __construct(private ImageEmbeddingService $embeddings) {}

    public function deleted(Model $model): void
    {
        $this->embeddings->visibilityChanged();
    }

    public function restored(Model $model): void
    {
        $this->embeddings->visibilityChanged();
        $product = $model instanceof ProductVariant ? $model->product : $model;
        $product instanceof Product && $this->embeddings->productRestored($product);
    }
}
