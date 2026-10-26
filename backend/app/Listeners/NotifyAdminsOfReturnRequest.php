<?php

namespace App\Listeners;

use App\Events\OrderReturnUpdated;
use App\Models\Order;
use App\Notifications\OrderNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyAdminsOfReturnRequest implements ShouldQueue
{
    public bool $afterCommit = true;

    public int $tries = 3;

    use Concerns\ResolvesOrderAdmins;

    public function handle(OrderReturnUpdated $event): void
    {
        if ($event->kind === 'requested') {
            $order = Order::withoutGlobalScopes()->findOrFail($event->return->order_id);
            $this->notify($order, new OrderNotification($order, 'return_requested', ['return' => $event->return->number, 'return_id' => $event->return->id], $event->actorId));
        }
    }
}
