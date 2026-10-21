<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantProductVariant extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_product_id', 'product_variant_id', 'price', 'sale_price', 'cost', 'is_active'];

    protected function casts(): array
    {
        return ['price' => 'decimal:2', 'sale_price' => 'decimal:2', 'cost' => 'decimal:2', 'is_active' => 'bool'];
    }

    public function tenantProduct(): BelongsTo
    {
        return $this->belongsTo(TenantProduct::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id')->withTrashed();
    }
}
