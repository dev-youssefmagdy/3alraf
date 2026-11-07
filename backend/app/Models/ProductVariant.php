<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use App\Models\Concerns\HasImages;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Variant of a variable product; price / sale_price / cost NULL = the product's value. */
class ProductVariant extends Model
{
    use Audited, HasImages, SoftDeletes;

    protected $fillable = ['product_id', 'combination_hash', 'sku', 'barcode', 'weight', 'is_active', 'price', 'sale_price', 'cost', 'min_stock_alert_qty'];

    protected function casts(): array
    {
        return ['is_active' => 'bool', 'weight' => 'decimal:3', 'price' => 'decimal:2', 'sale_price' => 'decimal:2', 'cost' => 'decimal:2', 'min_stock_alert_qty' => 'int'];
    }

    /** Effective selling price: own price / sale price, falling back to the product's. */
    public function effectivePrice(): ?string
    {
        $product = $this->product;
        $price = $this->price ?? $product?->price;
        if ($price === null) {
            return null;
        }
        $sale = $this->price !== null ? $this->sale_price : ($this->sale_price ?? $product?->sale_price);

        return ($sale !== null && bccomp((string) $sale, (string) $price, 2) < 0) ? (string) $sale : (string) $price;
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function values(): BelongsToMany
    {
        return $this->belongsToMany(AttributeValue::class, 'product_variant_values');
    }

    /** "Red / XL" label from the loaded values. */
    public function label(): string
    {
        return $this->values->map->value->implode(' / ');
    }

    public static function hashFor(array $valueIds): string
    {
        $ids = array_map('intval', $valueIds);
        sort($ids);

        return implode('-', $ids);
    }
}
