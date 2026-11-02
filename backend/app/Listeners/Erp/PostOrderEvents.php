<?php

namespace App\Listeners\Erp;

use App\Enums\OrderStatus;
use App\Events\OrderPaymentUpdated;
use App\Events\OrderReturnUpdated;
use App\Events\OrderStatusChanged;
use App\Models\Order;
use App\Services\Erp\Posting\PaymentPoster;
use App\Services\Erp\Posting\PostingGuard;
use App\Services\Erp\Posting\ReturnPoster;
use App\Services\Erp\Posting\SalesPoster;

/** Thin adapters: existing order events -> ledger posters (guarded: a ledger failure is logged, never breaks the order flow). */
class PostOrderEvents
{
    public function __construct(private PostingGuard $guard, private SalesPoster $sales, private PaymentPoster $payments, private ReturnPoster $returns) {}

    public function subscribe($events): array
    {
        return [
            \App\Events\OrderStatusChanged::class => 'onStatusChanged',
            \App\Events\Erp\CarrierSettled::class => 'onCarrierSettled',
            \App\Events\OrderPaymentUpdated::class => 'onPaymentUpdated',
            \App\Events\OrderReturnUpdated::class => 'onReturnUpdated',
        ];
    }

    public function onStatusChanged(OrderStatusChanged $e): void
    {
        $o = $e->order;
        $this->guard->run($o->tenant_id, 'order', $o->id, 'status:'.$e->to->value, function () use ($o, $e) {
            if (in_array($e->to, OrderStatus::salesStatuses(), true)) {
                $this->sales->confirmed($o);
                $this->payments->sync($o);
            } elseif ($e->to === OrderStatus::CANCELLED) {
                $this->payments->sync($o);
                $this->sales->cancelled($o);
                $this->payments->cancelRefund($o);
            } else {
                $this->payments->sync($o);
            }
        });
    }

    public function onCarrierSettled(\App\Events\Erp\CarrierSettled $e): void
    {
        $o = $e->order;
        $this->guard->run($o->tenant_id, 'order', $o->id, 'carrier.settled', fn () => $this->payments->carrierSettled($o));
    }

    public function onPaymentUpdated(OrderPaymentUpdated $e): void
    {
        if (! in_array($e->kind, ['paid', 'proof_approved'], true)) {
            return;
        }
        $o = $e->order;
        $this->guard->run($o->tenant_id, 'order', $o->id, 'payment:'.$e->kind, fn () => $this->payments->sync($o));
    }

    public function onReturnUpdated(OrderReturnUpdated $e): void
    {
        $r = $e->return;
        $order = Order::withoutGlobalScopes()->find($r->order_id);
        if (! in_array($e->kind, ['approved', 'refunded'], true) || ! $order) {
            return;
        }
        $this->guard->run($order->tenant_id, 'order_return', $r->id, 'return:'.$e->kind, fn () => $e->kind === 'approved' ? $this->returns->approved($r) : $this->returns->refunded($r));
    }
}
