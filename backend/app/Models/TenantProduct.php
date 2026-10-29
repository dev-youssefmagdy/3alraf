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

    protected $fillable = ['product_id', 'price', 'sale_price', 'cost', 'min_stock_alert_qty', 'is_active', 'content'];

    protected function casts(): array
    {
        return ['price' => 'decimal:2', 'sale_price' => 'decimal:2', 'cost' => 'decimal:2', 'is_active' => 'bool', 'content' => 'array'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    /** Tenant text overlay fields (central product stays untouched; the tenant only owns its own storefront wording). */
    public const CONTENT_FIELDS = ['name', 'description', 'meta_title', 'meta_description'];

    /** Overlay the tenant's own wording onto the (in-memory, never saved) product for display. */
    public function applyContent(): static
    {
        $p = $this->relationLoaded('product') ? $this->product : null;
        foreach ((array) $this->content as $field => $byLocale) {
            if ($p && in_array($field, self::CONTENT_FIELDS, true) && is_array($byLocale)) {
                $p->setTranslations($field, array_replace($p->getTranslations($field), array_filter($byLocale, fn ($v) => is_string($v) && $v !== '')));
            }
        }

        return $this;
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
