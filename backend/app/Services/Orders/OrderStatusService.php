<?php

namespace App\Services\Orders;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethodType;
use App\Enums\PaymentStatus;
use App\Events\OrderStatusChanged;
use App\Models\DiscountCodeUsage;
use App\Models\FlashSaleItem;
use App\Models\Order;
use App\Services\Payments\RefundService;
use App\Services\Stock\StockLedger;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Order status machine (OrderStatus::next) with history, stock reservation/deduction side effects. */
class OrderStatusService
{
    public function __construct(private StockLedger $ledger, private OrderProfitService $profit, private RefundService $refunds) {}

    public function change(Order $order, OrderStatus $to, ?string $note = null, ?int $userId = null, bool $force = false): Order
    {
        $from = DB::transaction(function () use ($order, $to, $note, $userId, $force) {
            $order->refresh();
            $locked = Order::whereKey($order->id)->lockForUpdate()->first();
            $from = $locked->status;
            if (! $force && ! in_array($to, $from->next(), true)) {
                throw ValidationException::withMessages(['status' => __('orders.invalid_transition')]);
            }

            if ($to === OrderStatus::CONFIRMED && ! $force) {
                $this->assertPaymentSettled($locked);
            }

            match ($to) {
                OrderStatus::SHIPPED => $this->ship($locked),
                OrderStatus::CANCELLED => $this->cancel($locked),
                OrderStatus::DELIVERED => $this->deliver($locked),
                default => null,
            };

            $locked->update(['status' => $to]);
            $this->profit->recalculate($locked); // net_profit: computed on admin confirmation, 0 again on cancel
            $locked->histories()->create(['from_status' => $from->value, 'to_status' => $to->value, 'note' => $note, 'user_id' => $userId ?? auth('tenant')->id()]);
            $order->setRawAttributes($locked->fresh()->getAttributes(), true);

            return $from;
        });

        event(new OrderStatusChanged($order, $from, $to));

        return $order;
    }

    /** Confirmation gate (CALCULATIONS.md §Payment methods): online/wallet must be paid & verified, COD needs its prepaid shipping settled. */
    private function assertPaymentSettled(Order $order): void
    {
        $type = $order->paymentMethod?->type;
        $ok = match (true) {
            $type === PaymentMethodType::ONLINE, $type === PaymentMethodType::WALLET => $order->payment_status === PaymentStatus::PAID,
            $order->prepay_method_id !== null => Money::cmp((string) $order->paid_amount, (string) $order->prepaid_shipping) >= 0,
            default => true,
        };
        if (! $ok) {
            throw ValidationException::withMessages(['status' => __('orders.confirm_payment_required')]);
        }
    }

    /**
     * Deduct stock (sale_out at the branch's current avg cost) and drop the reservation. The order's cost snapshot is re-valued to the cost
     * that actually left stock (CALCULATIONS.md §Definitions: unit_cost re-snapshotted at shipment), so COGS, returns and the ledger
     * inventory all use the stock ledger's value. A line with an uncosted movement (avg 0, e.g. backorder) keeps its listing-cost snapshot.
     */
    private function ship(Order $order): void
    {
        $cogs = '0';
        foreach ($order->items->sortBy(fn ($i) => sprintf('%012d-%012d', $i->product_id, $i->product_variant_id)) as $i) {
            $m = $this->ledger->ship($order->branch_id, $i->product_id, $i->product_variant_id, $i->quantity, $order);
            if (Money::cmp((string) $m->unit_cost, '0') > 0 && Money::cmp((string) $m->unit_cost, (string) $i->unit_cost) !== 0) {
                $i->forceFill(['unit_cost' => Money::round((string) $m->unit_cost)])->save();
            }
            $cogs = Money::add($cogs, Money::mul((string) $i->unit_cost, $i->quantity));
        }
        $order->cogs = $cogs;
        $order->stock_deducted = true;
        $order->save();
    }

    /**
     * Cancel: release reservations, flash counters, discount usage; whole paid amount (incl. shipping) is to be refunded.
     * After shipment (failed delivery / returned to sender) the goods come back into stock (return_in at the shipped cost) instead.
     */
    private function cancel(Order $order): void
    {
        $returned = (bool) $order->stock_deducted;
        foreach ($order->items->sortBy(fn ($i) => sprintf('%012d-%012d', $i->product_id, $i->product_variant_id)) as $i) {
            if ($returned) {
                $this->ledger->record($order->branch_id, $i->product_id, $i->product_variant_id, \App\Enums\StockMovementType::RETURN_IN, $i->quantity, (string) $i->unit_cost, $order, __('erp_acc.rts_note'));
            } else {
                $this->ledger->release($order->branch_id, $i->product_id, $i->product_variant_id, $i->quantity);
            }
            if ($i->flash_sale_item_id) {
                FlashSaleItem::whereKey($i->flash_sale_item_id)->where('sold', '>=', $i->quantity)->decrement('sold', $i->quantity);
            }
        }
        if ($order->discount_code_id) {
            $deleted = DiscountCodeUsage::where('order_id', $order->id)->delete();
            if ($deleted) {
                \App\Models\DiscountCode::whereKey($order->discount_code_id)->where('used_count', '>', 0)->decrement('used_count');
            }
        }
        $paid = (string) $order->paid_amount;
        $order->fill(['cancelled_at' => now(), 'affiliate_commission' => 0] + ($returned ? ['stock_deducted' => false] : []));
        if (Money::cmp($paid, '0') > 0) {
            // online money goes back through the provider and is marked refunded ONLY on provider success; wallet money is moved manually
            $res = $this->refunds->refundOnline($order, $paid);
            if ($res === null || $res->status === 'refunded') {
                $order->fill(['refunded_amount' => $paid, 'payment_status' => PaymentStatus::REFUNDED]);
            } else {
                $order->histories()->create(['from_status' => $order->status->value, 'to_status' => OrderStatus::CANCELLED->value, 'note' => __('orders.cancel_refund_failed_note')]);
            }
        }
        $order->save();
    }

    private function deliver(Order $order): void
    {
        $order->delivered_at = now();
        if ($order->paymentMethod?->type === PaymentMethodType::COD && $order->payment_status !== PaymentStatus::PAID) {
            $order->paid_amount = $order->total;
            $order->payment_status = PaymentStatus::PAID;
        }
        $order->save();
    }
}
