<?php

namespace App\Services\Orders;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethodType;
use App\Enums\PaymentStatus;
use App\Events\OrderStatusChanged;
use App\Models\DiscountCodeUsage;
use App\Models\FlashSaleItem;
use App\Models\Order;
use App\Services\Stock\StockLedger;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Order status machine (OrderStatus::next) with history, stock reservation/deduction side effects. */
class OrderStatusService
{
    public function __construct(private StockLedger $ledger) {}

    public function change(Order $order, OrderStatus $to, ?string $note = null, ?int $userId = null, bool $force = false): Order
    {
        $from = DB::transaction(function () use ($order, $to, $note, $userId, $force) {
            $order->refresh();
            $locked = Order::whereKey($order->id)->lockForUpdate()->first();
            $from = $locked->status;
            if (! $force && ! in_array($to, $from->next(), true)) {
                throw ValidationException::withMessages(['status' => __('orders.invalid_transition')]);
            }

            match ($to) {
                OrderStatus::SHIPPED => $this->ship($locked),
                OrderStatus::CANCELLED => $this->cancel($locked),
                OrderStatus::DELIVERED => $this->deliver($locked),
                default => null,
            };

            $locked->update(['status' => $to]);
            $locked->histories()->create(['from_status' => $from->value, 'to_status' => $to->value, 'note' => $note, 'user_id' => $userId ?? auth('tenant')->id()]);
            $order->setRawAttributes($locked->fresh()->getAttributes(), true);

            return $from;
        });

        event(new OrderStatusChanged($order, $from, $to));

        return $order;
    }

    /** Deduct stock (sale_out at current avg cost) and drop the reservation. */
    private function ship(Order $order): void
    {
        foreach ($order->items as $i) {
            $this->ledger->ship($order->branch_id, $i->product_id, $i->product_variant_id, $i->quantity, $order);
        }
        $order->stock_deducted = true;
        $order->save();
    }

    /** Before shipping only: release reservations, flash counters, discount usage; whole paid amount (incl. shipping) is to be refunded. */
    private function cancel(Order $order): void
    {
        foreach ($order->items as $i) {
            $this->ledger->release($order->branch_id, $i->product_id, $i->product_variant_id, $i->quantity);
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
        $order->fill(['cancelled_at' => now(), 'affiliate_commission' => 0]);
        if (Money::cmp($paid, '0') > 0) {
            $order->fill(['refunded_amount' => $paid, 'payment_status' => PaymentStatus::REFUNDED]);
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
