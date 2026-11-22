<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Read-only snapshot of a product that exists on 3alraf.com (imported from the store MCP). Casting / ad / video work from it; the live product is never modified. */
class CastingLiveProduct extends Model
{
    protected $fillable = ['live_id', 'sku', 'name', 'description', 'categories', 'tags', 'price', 'gift_recipients', 'url', 'image_urls', 'image_disk', 'image_path', 'fetched_at'];

    protected $hidden = ['image_disk', 'image_path'];

    protected function casts(): array
    {
        return ['name' => 'array', 'description' => 'array', 'categories' => 'array', 'tags' => 'array', 'gift_recipients' => 'array', 'image_urls' => 'array', 'price' => 'decimal:2', 'fetched_at' => 'datetime'];
    }

    public function displayName(?string $locale = null): string
    {
        $n = (array) $this->name;

        return (string) ($n[$locale ?? app()->getLocale()] ?? $n['en'] ?? $n['ar'] ?? reset($n) ?: '#'.$this->live_id);
    }
}
