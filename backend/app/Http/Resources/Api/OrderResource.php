<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Order  (cogs, net_profit, shipping_cost, affiliate_commission, proof path are internal and never exposed) */
class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $o = $this->resource;

        return [
            'number' => $o->number, 'token' => $o->public_token, 'status' => ['value' => $o->status->value, 'label' => $o->status->label()],
            'payment_status' => $o->payment_status->value, 'proof_status' => $o->proof_status?->value,
            'payment_method' => $o->relationLoaded('paymentMethod') && $o->paymentMethod ? ['id' => $o->paymentMethod->id, 'type' => $o->paymentMethod->type->value, 'name' => $o->paymentMethod->name] : null,
            'subtotal' => (string) $o->subtotal, 'discount' => (string) $o->discount, 'shipping' => (string) $o->shipping_net, 'tax' => (string) $o->tax,
            'payment_fee' => (string) $o->payment_fee_charged, 'total' => (string) $o->total, 'paid_amount' => (string) $o->paid_amount, 'due' => $o->due(),
            'cod_fee' => (string) $o->cod_fee, 'cod_fee_label' => (float) $o->cod_fee > 0 ? $o->codFeeLabel() : null, 'cod_amount' => (string) $o->cod_amount, 'prepaid_shipping' => (string) $o->prepaid_shipping, 'currency' => $o->currency,
            'placed_at' => $o->placed_at?->toIso8601String(), 'items_count' => $o->relationLoaded('items') ? (int) $o->items->sum('quantity') : null,
        ];
    }
}
