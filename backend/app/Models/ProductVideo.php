<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Public product gallery video (P8) on the `media` disk. Never related to the private social-media library (SocialMediaAsset).
 * sort_order shares one numbering with the product's Image rows.
 */
class ProductVideo extends Model
{
    protected $fillable = ['product_id', 'path', 'poster_path', 'poster_thumb_path', 'mime', 'size_bytes', 'sort_order'];

    protected function casts(): array
    {
        return ['size_bytes' => 'int', 'sort_order' => 'int'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function getUrlAttribute(): string
    {
        return media_url($this->path);
    }

    public function getPosterUrlAttribute(): ?string
    {
        return media_url($this->poster_path);
    }

    public function getPosterThumbUrlAttribute(): ?string
    {
        return media_url($this->poster_thumb_path ?: $this->poster_path);
    }
}
