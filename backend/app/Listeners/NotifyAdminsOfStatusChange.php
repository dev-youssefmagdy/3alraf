<?php

namespace App\Listeners;

use App\Events\OrderStatusChanged;
use App\Notifications\OrderNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyAdminsOfStatusChange implements ShouldQueue
{
    /** Queued (database/redis) once the surrounding DB transaction committed; the order is re-fetched by id in the worker. */
    public bool $afterCommit = true;

    public int $tries = 3;

    use Concerns\ResolvesOrderAdmins;

    public function handle(OrderStatusChanged $event): void
    {
        $this->notify($event->order, new OrderNotification($event->order, 'status_changed', ['from' => $event->from?->value, 'to' => $event->to->value]));
    }
}
