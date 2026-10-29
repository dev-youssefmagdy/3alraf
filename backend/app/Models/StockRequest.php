<?php

namespace App\Models;

use App\Enums\StockRequestStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** A customer asking to be contacted when an out-of-stock product (or one of its variants) is available. */
class StockRequest extends Model
{
    use BelongsToTenant;

    protected $fillable = ['product_id', 'product_variant_id', 'customer_id', 'name', 'phone', 'phone_country_code', 'note', 'status'];

    protected function casts(): array
    {
        return ['status' => StockRequestStatus::class];
    }

    public function product()
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id')->withTrashed();
    }
}
