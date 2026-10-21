<?php

namespace App\Listeners;

use App\Events\OrderPlaced;
use App\Notifications\OrderNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyAdminsOfNewOrder implements ShouldQueue
{
    /** Queued (database/redis) once the surrounding DB transaction committed; the order is re-fetched by id in the worker. */
    public bool $afterCommit = true;

    public int $tries = 3;

    use Concerns\ResolvesOrderAdmins;

    public function handle(OrderPlaced $event): void
    {
        $this->notify($event->order, new OrderNotification($event->order, 'new_order'));
    }
}
