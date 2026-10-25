<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Resource over ['items'=>CartService::view(), 'totals'=>OrderTotals::forCustomer()|null, 'flash'=>bool] (all math = OrderCalculator). */
class CartResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $c = $this->resource;
        $t = $c['totals'] ?? null;

        return [
            'items' => collect($c['items'])->map(fn ($i) => ['id' => $i['id'], 'product_id' => $i['product_id'], 'variant_id' => $i['variant_id'], 'bundle_id' => $i['bundle_id'] ?: null, 'is_bundle' => $i['is_bundle'], 'components' => $i['components'], 'quantity' => $i['qty'],
                'name' => $i['name'], 'slug' => $i['slug'], 'image' => $i['image'], 'unit_price' => $i['unit_price'], 'available' => $i['available']])->values(),
            'packaging' => $c['packaging'] ?? null,
            'has_flash' => (bool) ($c['flash'] ?? false),
            'totals' => $t ? [
                'subtotal' => $t['subtotal'], 'discount' => $t['discount'], 'shipping' => $t['shipping'], 'shipping_discount' => $t['shippingDiscount'], 'shipping_net' => $t['shippingNet'],
                'tax' => $t['tax'], 'payment_fee' => $t['paymentFeeCharged'], 'total' => $t['total'], 'cod_fee' => $t['codFee'], 'cod_fee_label' => $t['codFeeLabel'] ?? null, 'packaging_fee' => $t['packagingFee'], 'cod_amount' => $t['codAmount'], 'prepaid_shipping' => $t['prepaidShipping'],
            ] : null,
        ];
    }
}
