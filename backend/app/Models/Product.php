<?php

namespace App\Models;

use App\Enums\ProductType;
use App\Models\Concerns\HasImages;
use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasImages, HasTranslations, SoftDeletes;

    protected array $translatable = ['name', 'description', 'meta_title', 'meta_description'];

    protected $fillable = ['brand_id', 'type', 'name', 'description', 'meta_title', 'meta_description', 'slug', 'sku', 'barcode', 'weight', 'is_active'];

    protected function casts(): array
    {
        return ['type' => ProductType::class, 'is_active' => 'bool', 'weight' => 'decimal:3'];
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('id');
    }

    public function isVariable(): bool
    {
        return $this->type === ProductType::VARIABLE;
    }
}
