<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    use BelongsToTenant;

    protected $fillable = ['order_id', 'product_id', 'product_variant_id', 'flash_sale_item_id', 'name', 'sku', 'barcode', 'unit_price', 'unit_cost', 'quantity', 'line_subtotal', 'line_discount', 'returned_qty'];

    protected function casts(): array
    {
        return ['unit_price' => 'decimal:2', 'unit_cost' => 'decimal:2', 'line_subtotal' => 'decimal:2', 'line_discount' => 'decimal:2',
            'quantity' => 'int', 'returned_qty' => 'int'];
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function flashSaleItem()
    {
        return $this->belongsTo(FlashSaleItem::class);
    }
}
