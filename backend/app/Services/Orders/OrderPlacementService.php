<?php

namespace App\Services\Orders;

use App\DTOs\Orders\CheckoutData;
use App\DTOs\Payments\PaymentResult;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Events\OrderPlaced;
use App\Models\Branch;
use App\Models\DiscountCodeUsage;
use App\Models\FlashSaleItem;
use App\Models\Order;
use App\Repositories\Contracts\OrderRepositoryInterface;
use App\Services\Stock\StockLedger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Places an order in ONE transaction: quote (locked) -> order + items -> flash counters -> discount usage -> stock reservation. */
class OrderPlacementService
{
    public function __construct(
        private OrderQuoteService $quotes,
        private OrderRepositoryInterface $orders,
        private StockLedger $ledger,
        private \App\Services\Affiliates\AffiliateCommissionService $affiliates,
        private OrderPaymentService $payments,
    ) {}

    /** @return array{order:Order,payment:?PaymentResult} */
    public function place(CheckoutData $d, array $cartLines): array
    {
        if (! $d->shippingCompanyId || ! $d->cityId || ! $d->paymentMethodId) {
            throw ValidationException::withMessages(['shipping_company_id' => __('orders.checkout_incomplete')]);
        }

        $order = DB::transaction(function () use ($d, $cartLines) {
            $q = $this->quotes->quote($cartLines, $d, true);
            $this->quotes->assertPrepay($q);
            $t = $q['totals'];
            $branchId = $this->pickBranch($q['lines']);

            $order = $this->orders->create([
                'public_token' => (string) Str::uuid(), 'customer_id' => $d->customerId, 'customer_name' => $d->name, 'customer_phone' => $d->phone,
                'customer_email' => $d->email, 'city_id' => $d->cityId, 'address' => $d->address, 'notes' => $d->notes, 'branch_id' => $branchId,
                'shipping_company_id' => $d->shippingCompanyId, 'payment_method_id' => $d->paymentMethodId, 'prepay_method_id' => ($t->prepaidShipping > 0 && $q['prepay']) ? $q['prepay']->id : null, 'discount_code_id' => $q['discount']?->id, 'affiliate_id' => $q['affiliate']?->id,
                'status' => OrderStatus::PENDING, 'payment_status' => PaymentStatus::UNPAID,
                'subtotal' => $t->subtotal, 'discount' => $t->discount, 'shipping' => $t->shipping, 'shipping_discount' => $t->shippingDiscount, 'shipping_net' => $t->shippingNet,
                'shipping_cost' => $t->shippingCost, 'tax' => $t->tax, 'payment_fee' => $t->paymentFee, 'payment_fee_charged' => $t->paymentFeeCharged, 'cod_fee' => $t->codFee, 'cod_fee_label' => bccomp($t->codFee, '0', 2) > 0 ? $q['cod_fee_label'] : null, 'total' => $t->total, 'cod_amount' => $t->codAmount, 'prepaid_shipping' => $t->prepaidShipping,
                'cogs' => $t->cogs, 'affiliate_commission' => $t->affiliateCommission, 'net_profit' => 0, // computed when an admin confirms (OrderProfitService)
                'currency' => current_tenant()?->currency_code, 'placed_at' => now(),
            ]);
            $order->update(['number' => sprintf('ORD-%06d', $order->id)]);

            $reserve = [];
            foreach ($q['lines'] as $l) {
                $order->items()->create([
                    'product_id' => $l['product_id'], 'product_variant_id' => $l['variant_id'], 'flash_sale_item_id' => $l['flash']?->id, 'name' => $l['name'],
                    'sku' => $l['sku'], 'barcode' => $l['barcode'], 'unit_price' => $l['unit_price'], 'unit_cost' => $l['unit_cost'], 'quantity' => $l['qty'],
                    'line_subtotal' => \App\Support\Money::mul($l['unit_price'], $l['qty']), 'line_discount' => $t->lineDiscounts[$l['key']] ?? 0,
                ]);
                if ($l['flash']) { // never past qty_limit (the locked quote already split; this guards a stale/limitless race)
                    $ok = FlashSaleItem::whereKey($l['flash']->id)->where(fn ($q) => $q->whereNull('qty_limit')->orWhereRaw('sold + ? <= qty_limit', [$l['qty']]))->increment('sold', $l['qty']);
                    if (! $ok) {
                        throw ValidationException::withMessages(['cart' => __('orders.item_unavailable')]);
                    }
                }
                $k = $l['product_id'].'-'.$l['variant_id'];
                $reserve[$k] = ($reserve[$k] ?? 0) + $l['qty'];
            }
            // consistent lock order (product, variant) so concurrent orders can never deadlock on stock rows
            uksort($reserve, fn ($a, $b) => array_map('intval', explode('-', $a)) <=> array_map('intval', explode('-', $b)));
            foreach ($reserve as $k => $qty) {
                [$p, $v] = array_map('intval', explode('-', $k));
                $this->ledger->reserve($branchId, $p, $v, $qty);
            }

            if ($q['discount']) {
                DiscountCodeUsage::create(['discount_code_id' => $q['discount']->id, 'order_id' => $order->id, 'customer_id' => $d->customerId, 'amount' => $t->discount]);
                $q['discount']->increment('used_count');
            }
            if ($q['affiliate']) {
                $this->affiliates->record($order, $q['affiliate'], $q['affiliate_percent']);
            }
            $order->histories()->create(['from_status' => null, 'to_status' => OrderStatus::PENDING->value]);

            return $order;
        });

        $payment = $this->payments->startOnline($order); // null for COD/wallet or when the provider is unreachable (customer can retry from the order page)

        event(new OrderPlaced($order));

        return ['order' => $order, 'payment' => $payment];
    }

    /** First active branch (default first) that can serve every line; else the default branch when backorder is allowed. */
    private function pickBranch(array $lines): int
    {
        $branches = Branch::where('is_active', true)->orderByDesc('is_default')->orderBy('id')->get();
        $need = [];
        foreach ($lines as $l) {
            $k = $l['product_id'].'-'.$l['variant_id'];
            $need[$k] = ($need[$k] ?? 0) + $l['qty'];
        }
        foreach ($branches as $b) {
            $ok = true;
            foreach ($need as $k => $qty) {
                [$p, $v] = array_map('intval', explode('-', $k));
                if ($this->ledger->available($b->id, $p, $v) < $qty) {
                    $ok = false;
                    break;
                }
            }
            if ($ok) {
                return $b->id;
            }
        }
        if ($branches->isNotEmpty() && (current_tenant()?->settings['allow_backorder'] ?? false)) {
            return $branches->first()->id;
        }

        throw ValidationException::withMessages(['cart' => __('orders.out_of_stock')]);
    }
}
