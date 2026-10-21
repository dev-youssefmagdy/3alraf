<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TenantProduct extends Model
{
    use BelongsToTenant;

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

    /** Effective selling price (sale if set and lower). */
    public function effectivePrice(): string
    {
        return ($this->sale_price !== null && bccomp((string) $this->sale_price, (string) $this->price, 2) < 0) ? (string) $this->sale_price : (string) $this->price;
    }
}
