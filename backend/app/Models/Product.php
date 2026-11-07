<?php

namespace App\Models;

use App\Enums\ProductType;
use App\Models\Concerns\Audited;
use App\Models\Concerns\HasImages;
use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Catalog product AND its storefront listing (price / sale price / cost / status / own wording / gift tags).
 * is_active = sold in the storefront. Variants carry optional price / sale price / cost overrides.
 */
class Product extends Model
{
    use Audited, HasImages, HasTranslations, SoftDeletes;

    protected array $translatable = ['name', 'description', 'meta_title', 'meta_description'];

    protected $fillable = ['brand_id', 'type', 'name', 'description', 'meta_title', 'meta_description', 'slug', 'sku', 'barcode', 'weight', 'is_active',
        'price', 'sale_price', 'cost', 'min_stock_alert_qty', 'content', 'gift_suitable', 'gift_recipients', 'gift_occasions'];

    /** Storefront wording overlay fields (`content`): shown instead of the catalog text, the catalog text itself stays untouched. */
    public const CONTENT_FIELDS = ['name', 'description', 'meta_title', 'meta_description'];

    protected function casts(): array
    {
        return ['type' => ProductType::class, 'is_active' => 'bool', 'weight' => 'decimal:3',
            'price' => 'decimal:2', 'sale_price' => 'decimal:2', 'cost' => 'decimal:2', 'min_stock_alert_qty' => 'int',
            'content' => 'array', 'gift_suitable' => 'bool', 'gift_recipients' => 'array', 'gift_occasions' => 'array'];
    }

    /** Overlay the storefront wording (`content`) onto this in-memory instance for display (never saved). */
    public function applyContent(): static
    {
        foreach ((array) $this->content as $field => $byLocale) {
            if (in_array($field, self::CONTENT_FIELDS, true) && is_array($byLocale)) {
                $this->setTranslations($field, array_replace($this->getTranslations($field), array_filter($byLocale, fn ($v) => is_string($v) && $v !== '')));
            }
        }

        return $this;
    }

    /** Effective selling price (sale if set and lower); null while the product has no price (not listed). */
    public function effectivePrice(): ?string
    {
        if ($this->price === null) {
            return null;
        }

        return ($this->sale_price !== null && bccomp((string) $this->sale_price, (string) $this->price, 2) < 0) ? (string) $this->sale_price : (string) $this->price;
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
