<?php

namespace App\Models;

use App\Enums\DiscountScope;
use App\Enums\DiscountType;
use App\Models\Concerns\Audited;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DiscountCode extends Model
{
    use Audited, BelongsToTenant, SoftDeletes;

    protected $fillable = ['code', 'type', 'value', 'min_order', 'max_discount', 'usage_limit', 'per_customer_limit', 'used_count',
        'starts_at', 'ends_at', 'scope', 'product_ids', 'category_ids', 'free_shipping', 'is_active'];

    protected function casts(): array
    {
        return ['type' => DiscountType::class, 'scope' => DiscountScope::class, 'value' => 'decimal:2', 'min_order' => 'decimal:2', 'max_discount' => 'decimal:2',
            'starts_at' => 'datetime', 'ends_at' => 'datetime', 'product_ids' => 'array', 'category_ids' => 'array', 'free_shipping' => 'bool', 'is_active' => 'bool'];
    }

    public function usages()
    {
        return $this->hasMany(DiscountCodeUsage::class);
    }
}
