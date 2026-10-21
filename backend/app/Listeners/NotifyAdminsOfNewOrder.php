<?php

namespace App\Listeners;

use App\Events\OrderPlaced;
use App\Notifications\OrderNotification;

class NotifyAdminsOfNewOrder
{
    use Concerns\ResolvesOrderAdmins;

    public function handle(OrderPlaced $event): void
    {
        $this->notify($event->order, new OrderNotification($event->order, 'new_order'));
    }
}
