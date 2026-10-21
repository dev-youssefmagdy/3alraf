<?php

namespace App\Models;

use App\Models\Concerns\HasImages;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductVariant extends Model
{
    use HasImages, SoftDeletes;

    protected $fillable = ['product_id', 'combination_hash', 'sku', 'barcode', 'weight', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'bool', 'weight' => 'decimal:3'];
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
