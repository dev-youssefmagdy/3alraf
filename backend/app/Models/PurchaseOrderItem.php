<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrderItem extends Model
{
    public $timestamps = false;

    protected $fillable = ['purchase_order_id', 'product_id', 'product_variant_id', 'quantity', 'unit_cost', 'line_total'];

    protected function casts(): array
    {
        return ['unit_cost' => 'decimal:2', 'line_total' => 'decimal:2'];
    }

    public function product()
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id')->withTrashed();
    }

    /** "Product name - Red / XL" */
    public function label(): string
    {
        return $this->product->name.($this->product_variant_id ? ' - '.$this->variant?->load('values')->label() : '');
    }
}
