<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TenantProduct extends Model
{
    use Audited, BelongsToTenant;

    protected $fillable = ['product_id', 'price', 'sale_price', 'cost', 'is_active'];

    protected function casts(): array
    {
        return ['price' => 'decimal:2', 'sale_price' => 'decimal:2', 'cost' => 'decimal:2', 'is_active' => 'bool'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function variants(): HasMany
    {
        return $this->hasMany(TenantProductVariant::class);
    }

    /** Make sure a tenant_product_variants row exists for every live central variant. */
    public function ensureVariantRows(): void
    {
        $product = Product::query()->find($this->product_id);
        if (! $product?->isVariable()) {
            return;
        }
        $have = $this->variants()->pluck('product_variant_id')->all();
        foreach ($product->variants()->whereNotIn('id', $have ?: [0])->pluck('id') as $variantId) {
            $this->variants()->create(['product_variant_id' => $variantId, 'is_active' => true]);
        }
    }

    /** Effective selling price (sale if set and lower). */
    public function effectivePrice(): string
    {
        return ($this->sale_price !== null && bccomp((string) $this->sale_price, (string) $this->price, 2) < 0) ? (string) $this->sale_price : (string) $this->price;
    }
}
