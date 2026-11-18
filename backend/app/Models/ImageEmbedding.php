<?php

namespace App\Models;

use App\Enums\ImageEmbeddingSource;
use App\Enums\ImageEmbeddingStatus;
use Illuminate\Database\Eloquent\Model;

/**
 * Image-search vector of one source (image / bundle) for one model (IMAGE_SEARCH_PLAN.md §3.2). `vector` = raw little-endian float32 bytes.
 * Internal index only: never shown to customers, never exported through an API.
 */
class ImageEmbedding extends Model
{
    protected $fillable = ['source_type', 'source_id', 'product_id', 'product_variant_id', 'bundle_id', 'source_path', 'source_hash', 'model', 'dim',
        'vector', 'status', 'attempts', 'error', 'queued_at', 'embedded_at', 'failed_at'];

    protected $hidden = ['vector'];

    protected function casts(): array
    {
        return ['source_type' => ImageEmbeddingSource::class, 'status' => ImageEmbeddingStatus::class, 'dim' => 'int', 'attempts' => 'int',
            'queued_at' => 'datetime', 'embedded_at' => 'datetime', 'failed_at' => 'datetime'];
    }
}
