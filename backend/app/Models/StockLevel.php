<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class StockLevel extends Model
{
    use BelongsToTenant;

    protected $fillable = ['branch_id', 'product_id', 'product_variant_id', 'on_hand', 'reserved', 'avg_cost'];

    protected function casts(): array
    {
        return ['on_hand' => 'int', 'reserved' => 'int', 'avg_cost' => 'decimal:2'];
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class)->withTrashed();
    }

    public function product()
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id')->withTrashed();
    }

    public function getAvailableAttribute(): int
    {
        return $this->on_hand - $this->reserved;
    }

    public function getValuationAttribute(): string
    {
        return \App\Support\Money::mul($this->on_hand, (string) $this->avg_cost);
    }
}
