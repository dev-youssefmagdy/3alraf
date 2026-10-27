<?php

namespace App\Services\Orders;

use App\DTOs\Orders\ReturnRequestData;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReturnStatus;
use App\Enums\StockMovementType;
use App\Events\OrderReturnUpdated;
use App\Events\OrderStatusChanged;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderReturn;
use App\Models\OrderReturnItem;
use App\Repositories\Contracts\OrderReturnRepositoryInterface;
use App\Services\Affiliates\AffiliateCommissionService;
use App\Services\Payments\RefundService;
use App\Services\Stock\StockLedger;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Returns (requested -> approved [restock] | rejected) and refunds (approved -> refunded). Math: docs/CALCULATIONS.md §Returns & refunds. */
class ReturnService
{
    public function __construct(private OrderReturnRepositoryInterface $returns, private StockLedger $ledger, private RefundService $refunds, private OrderProfitService $profit) {}

    public function find(int|string $id): OrderReturn
    {
        return $this->returns->query()->with(['order.items', 'items.orderItem', 'branch'])->findOrFail($id);
    }

    /**
     * return_value = unit_price × qty − discount_share, CUMULATIVE per line (CALCULATIONS.md §Returns):
     * discount_share = round(line_discount × (A+qty)/ordered) − round(line_discount × A/ordered), A = units already returned/pending
     * (default returned_qty), so partial returns sum exactly to line_subtotal − line_discount.
     */
    public function lineValue(OrderItem $item, int $qty, ?int $already = null): string
    {
        $already ??= (int) $item->returned_qty;
        $at = fn (int $n) => Money::round(bcdiv(bcmul((string) $item->line_discount, (string) $n, 8), (string) $item->quantity, 8));
        $discountShare = Money::sub($at($already + $qty), $at($already));

        return Money::sub(Money::mul((string) $item->unit_price, $qty), $discountShare);
    }

    public function request(Order $order, ReturnRequestData $d): OrderReturn
    {
        if (! $d->quantities) {
            throw ValidationException::withMessages(['items' => __('orders.return_nothing')]);
        }

        return DB::transaction(function () use ($order, $d) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail(); // serialises concurrent requests on the same order
            if ($order->status !== OrderStatus::DELIVERED) {
                throw ValidationException::withMessages(['items' => __('orders.return_not_allowed')]);
            }
            $items = $order->items()->get()->keyBy('id');
            $pending = OrderReturnItem::query()->whereIn('order_return_id', OrderReturn::where('order_id', $order->id)->where('status', ReturnStatus::REQUESTED->value)->pluck('id'))
                ->selectRaw('order_item_id, SUM(quantity) q')->groupBy('order_item_id')->pluck('q', 'order_item_id');

            $value = '0';
            $cogs = '0';
            $rows = [];
            foreach ($d->quantities as $itemId => $qty) {
                $item = $items->get($itemId) ?? throw ValidationException::withMessages(['items' => __('orders.return_invalid_item')]);
                if ($qty > $item->quantity - $item->returned_qty - (int) ($pending[$itemId] ?? 0)) {
                    throw ValidationException::withMessages(['items' => __('orders.return_qty_exceeded', ['name' => $item->name])]);
                }
                $v = $this->lineValue($item, $qty, (int) $item->returned_qty + (int) ($pending[$itemId] ?? 0));
                $value = Money::add($value, $v);
                $cogs = Money::add($cogs, Money::mul((string) $item->unit_cost, $qty));
                $rows[] = ['order_item_id' => $itemId, 'quantity' => $qty, 'return_value' => $v];
            }

            $ret = $this->returns->create([
                'order_id' => $order->id, 'status' => ReturnStatus::REQUESTED, 'reason' => $d->reason, 'reason_code' => $d->reasonCode, 'restock' => $d->restock, 'branch_id' => $d->branchId ?? $order->branch_id,
                'refund_shipping' => $d->refundShipping, 'return_value' => $value, 'returned_cogs' => $cogs, 'return_shipping_cost' => Money::round($d->returnShippingCost),
            ]);
            $ret->update(['number' => sprintf('RET-%06d', $ret->id)]);
            $ret->items()->createMany($rows);
            event(new OrderReturnUpdated($ret, 'requested'));

            return $ret;
        });
    }

    public function approve(OrderReturn $ret, ?bool $restock = null, ?int $branchId = null): OrderReturn
    {
        $this->ensure($ret, ReturnStatus::REQUESTED);

        return DB::transaction(function () use ($ret, $restock, $branchId) {
            // lock order then return row (same order as refund/request), re-check the state on the FRESH row: no double restock
            $order = Order::whereKey($ret->order_id)->lockForUpdate()->first();
            $ret = $this->lockFresh($ret, ReturnStatus::REQUESTED);
            $restock ??= $ret->restock;
            $branchId ??= $ret->branch_id ?? $order->branch_id;

            $items = $ret->items()->with('orderItem')->get();
            foreach ($items as $ri) {
                if ($ri->orderItem->returned_qty + $ri->quantity > $ri->orderItem->quantity) {
                    throw ValidationException::withMessages(['items' => __('orders.return_qty_exceeded', ['name' => $ri->orderItem->name])]);
                }
            }
            foreach ($items->sortBy(fn ($ri) => sprintf('%012d-%012d', $ri->orderItem->product_id, $ri->orderItem->product_variant_id)) as $ri) {
                $oi = $ri->orderItem;
                if ($restock) {
                    $this->ledger->record($branchId, $oi->product_id, $oi->product_variant_id, StockMovementType::RETURN_IN, $ri->quantity, (string) $oi->unit_cost, $ret);
                }
                $oi->increment('returned_qty', $ri->quantity);
            }
            $ret->update(['status' => ReturnStatus::APPROVED, 'restock' => $restock, 'branch_id' => $branchId, 'approved_at' => now()]);

            $order->load('items');
            if ($order->items->every(fn ($i) => $i->returned_qty >= $i->quantity)) {
                $from = $order->status;
                $order->update(['status' => OrderStatus::RETURNED, 'affiliate_commission' => 0]);
                $order->histories()->create(['from_status' => $from->value, 'to_status' => OrderStatus::RETURNED->value, 'note' => $ret->number, 'user_id' => auth('tenant')->id()]);
                DB::afterCommit(fn () => event(new OrderStatusChanged($order, $from, OrderStatus::RETURNED)));
            } else {
                // partial return: recomputed from the CUMULATIVE returned value (CALCULATIONS.md §Affiliate commission)
                app(AffiliateCommissionService::class)->recalculateAfterReturns($order);
            }

            app(AffiliateCommissionService::class)->syncOrder($order->refresh());
            $this->profit->recalculate($order->refresh()); // returns reduce orders.net_profit

            event(new OrderReturnUpdated($ret, 'approved'));

            return $ret->refresh();
        });
    }

    public function reject(OrderReturn $ret): OrderReturn
    {
        $this->ensure($ret, ReturnStatus::REQUESTED);

        return DB::transaction(function () use ($ret) {
            $ret = $this->lockFresh($ret, ReturnStatus::REQUESTED);
            $ret->update(['status' => ReturnStatus::REJECTED]);
            event(new OrderReturnUpdated($ret, 'rejected'));

            return $ret;
        });
    }

    /**
     * Tax refunded with a return (CALCULATIONS.md step 3): exclusive-tax orders refund tax CUMULATIVELY pro-rata
     * (`round(tax × (P+v)/B) − round(tax × P/B)`, P = return value already refunded), so all returns sum exactly to `tax`;
     * tax-inclusive orders refund nothing extra (it is inside return_value).
     */
    public function taxShare(Order $order, string $returnValue, string $prior = '0'): string
    {
        if ($order->taxIncluded()) {
            return '0.00'; // inclusive: tax was never added on top of the goods
        }

        return $order->returnTax($returnValue, $prior);
    }

    /** Max refundable = return_value + its tax share (+ shipping_net when flagged), never above what was paid and not yet refunded. */
    public function refundable(OrderReturn $ret): string
    {
        $order = $ret->order;
        $prior = Money::round((string) OrderReturn::where('order_id', $ret->order_id)->whereKeyNot($ret->id)->where('status', ReturnStatus::REFUNDED)->sum('return_value'));
        $cap = Money::add((string) $ret->return_value, $this->taxShare($order, (string) $ret->return_value, $prior), $this->shippingRefundable($ret) ? (string) $order->shipping_net : '0');
        $left = Money::sub((string) $order->paid_amount, (string) $order->refunded_amount);

        return Money::max('0', Money::min($cap, $left));
    }

    /** Shipping is refunded at most ONCE per order: false when this return has no refund_shipping flag or an earlier return already refunded it. */
    private function shippingRefundable(OrderReturn $ret): bool
    {
        if (! $ret->refund_shipping) {
            return false;
        }

        return ! OrderReturn::where('order_id', $ret->order_id)->whereKeyNot($ret->id)
            ->where('refund_shipping', true)->where('status', ReturnStatus::REFUNDED)->exists();
    }

    public function refund(OrderReturn $ret, ?string $amount = null): OrderReturn
    {
        $this->ensure($ret, ReturnStatus::APPROVED);

        return DB::transaction(function () use ($ret, $amount) {
            $order = Order::whereKey($ret->order_id)->lockForUpdate()->first();
            $ret = $this->lockFresh($ret, ReturnStatus::APPROVED); // fresh state under lock: the provider is never asked twice
            $ret->setRelation('order', $order);
            $max = $this->refundable($ret);
            $amount = $amount === null || $amount === '' ? $max : Money::round($amount);
            if (Money::cmp($amount, $max) > 0 || Money::cmp($amount, '0') <= 0) {
                throw ValidationException::withMessages(['amount' => __('orders.refund_exceeds', ['max' => $max])]);
            }

            // Online money goes back through the provider; mark refunded ONLY on provider success.
            $res = $this->refunds->refundOnline($order, $amount, $this->shippingRefundable($ret));
            if ($res !== null && $res->status !== 'refunded') {
                throw ValidationException::withMessages(['amount' => __('orders.refund_failed')]);
            }

            $refunded = Money::add((string) $order->refunded_amount, $amount);
            $order->update([
                'refunded_amount' => $refunded,
                'payment_status' => Money::cmp($refunded, (string) $order->paid_amount) >= 0 ? PaymentStatus::REFUNDED : PaymentStatus::PARTIALLY_REFUNDED,
            ]);
            $ret->update(['status' => ReturnStatus::REFUNDED, 'refund_amount' => $amount, 'refunded_at' => now()]);
            event(new OrderReturnUpdated($ret, 'refunded'));

            return $ret->refresh();
        });
    }

    /** Re-read the return row FOR UPDATE inside the current transaction and re-check its state on the fresh row. */
    private function lockFresh(OrderReturn $ret, ReturnStatus $expected): OrderReturn
    {
        $fresh = OrderReturn::whereKey($ret->id)->lockForUpdate()->firstOrFail();
        $this->ensure($fresh, $expected);

        return $fresh;
    }

    private function ensure(OrderReturn $ret, ReturnStatus $expected): void
    {
        if ($ret->status !== $expected) {
            throw ValidationException::withMessages(['status' => __('orders.return_invalid_state')]);
        }
    }
}
