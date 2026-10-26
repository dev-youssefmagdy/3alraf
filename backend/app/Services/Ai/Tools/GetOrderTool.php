<?php

namespace App\Services\Ai\Tools;

use App\Models\Order;
use App\Services\Ai\AiContext;

/** Every amount is the value stored by OrderCalculator at placement; nothing is recomputed here. */
class GetOrderTool extends AbstractTool
{
    public function name(): string { return 'get_order'; }

    public function description(): string { return 'One order by number: lines (variants/bundles), subtotal, discount, shipping, COD fee and COD amount vs prepaid shipping, packaging, taxes, payment, totals, status history. PII masked.'; }

    public function schema(): array
    {
        return $this->obj(['number' => ['type' => 'string']], ['number']);
    }

    protected function run(array $a, AiContext $ctx): array
    {
        $o = Order::query()->with(['items', 'histories'])->where('number', (string) ($a['number'] ?? ''))->first();
        if (! $o) {
            return ['error' => 'not_found'];
        }

        return [
            'number' => $o->number, 'status' => $o->status?->value, 'payment_status' => $o->payment_status?->value, 'currency' => $o->currency ?: $ctx->currency,
            'placed_at' => ($o->placed_at ?? $o->created_at)?->toDateTimeString(), 'delivered_at' => $o->delivered_at?->toDateTimeString(), 'customer' => $this->maskName($o->customer_name),
            'items' => $o->items->take(60)->map(fn ($i) => [
                'name' => $this->data($i->name, 120), 'sku' => $i->sku, 'qty' => $i->quantity, 'returned_qty' => $i->returned_qty, 'unit_price' => $i->unit_price, 'line_subtotal' => $i->line_subtotal, 'line_discount' => $i->line_discount,
                'bundle' => $i->bundle_name ? $this->data(is_array($i->bundle_name) ? (reset($i->bundle_name) ?: '') : $i->bundle_name, 80) : null,
            ])->all(),
            'amounts' => [
                'subtotal' => $o->subtotal, 'discount' => $o->discount, 'shipping' => $o->shipping, 'shipping_discount' => $o->shipping_discount, 'cod_fee' => $o->cod_fee, 'payment_fee_charged' => $o->payment_fee_charged,
                'packaging_fee' => $o->packaging_fee, 'packaging_name' => $o->packaging_fee > 0 ? $this->data($o->packagingLabel(), 60) : null, 'goods_tax' => $o->tax, 'packaging_tax' => $o->packaging_tax, 'total' => $o->total,
                'prepaid_shipping' => $o->prepaid_shipping, 'cod_amount_due_on_delivery' => $o->cod_amount, 'paid_amount' => $o->paid_amount, 'refunded_amount' => $o->refunded_amount,
                'cogs' => $o->cogs, 'net_profit' => $o->net_profit,
            ],
            'history' => $o->histories->take(20)->map(fn ($h) => ['from' => $h->from_status, 'to' => $h->to_status, 'at' => $h->created_at?->toDateTimeString()])->all(),
        ];
    }
}
