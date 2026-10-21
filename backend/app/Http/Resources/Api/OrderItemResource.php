<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\OrderItem  (unit_cost is internal and never exposed) */
class OrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'product_id' => $this->product_id, 'variant_id' => $this->product_variant_id, 'name' => $this->name, 'sku' => $this->sku,
            'unit_price' => (string) $this->unit_price, 'quantity' => $this->quantity, 'line_subtotal' => (string) $this->line_subtotal,
            'line_discount' => (string) $this->line_discount, 'returned_qty' => $this->returned_qty];
    }
}
