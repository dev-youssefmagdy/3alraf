<?php

namespace App\Models;

use App\Enums\FlashDiscountType;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class FlashSaleItem extends Model
{
    use BelongsToTenant;

    protected $fillable = ['flash_sale_id', 'product_id', 'product_variant_id', 'discount_type', 'value', 'qty_limit', 'sold'];

    protected function casts(): array
    {
        return ['discount_type' => FlashDiscountType::class, 'value' => 'decimal:2', 'qty_limit' => 'int', 'sold' => 'int'];
    }

    public function flashSale()
    {
        return $this->belongsTo(FlashSale::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function remaining(): ?int
    {
        return $this->qty_limit === null ? null : max(0, $this->qty_limit - $this->sold);
    }
}
