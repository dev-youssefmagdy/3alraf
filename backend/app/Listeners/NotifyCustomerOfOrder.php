<?php

namespace App\Listeners;

use App\Enums\OrderStatus;
use App\Events\OrderPaymentUpdated;
use App\Events\OrderPlaced;
use App\Events\OrderReturnUpdated;
use App\Events\OrderStatusChanged;
use App\Models\Order;
use App\Notifications\CustomerOrderNotification;
use App\Support\CustomerMailPolicy;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;

/** All customer e-mails for an order. Queued after commit; models are re-fetched by id in the worker. */
class NotifyCustomerOfOrder implements ShouldQueue
{
    public bool $afterCommit = true;

    public int $tries = 3;

    public function subscribe(Dispatcher $events): array
    {
        return [
            OrderPlaced::class => 'placed',
            OrderStatusChanged::class => 'status',
            OrderPaymentUpdated::class => 'payment',
            OrderReturnUpdated::class => 'returned',
        ];
    }

    public function placed(OrderPlaced $e): void
    {
        CustomerMailPolicy::send($e->order, 'placed', new CustomerOrderNotification($e->order, 'placed'));
    }

    public function status(OrderStatusChanged $e): void
    {
        // pending = the order was just placed (covered by "placed")
        if ($e->to !== OrderStatus::PENDING) {
            CustomerMailPolicy::send($e->order, 'status', new CustomerOrderNotification($e->order, 'status_changed', $e->to->value));
        }
    }

    public function payment(OrderPaymentUpdated $e): void
    {
        CustomerMailPolicy::send($e->order, 'payment', new CustomerOrderNotification($e->order, $e->kind === 'paid' ? 'paid' : $e->kind));
    }

    public function returned(OrderReturnUpdated $e): void
    {
        $order = Order::withoutGlobalScopes()->findOrFail($e->return->order_id);
        CustomerMailPolicy::send($order, 'return', new CustomerOrderNotification($order, 'return_'.$e->kind, null, $e->return));
    }
}
