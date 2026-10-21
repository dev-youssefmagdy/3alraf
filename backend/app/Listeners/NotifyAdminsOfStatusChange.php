<?php

namespace App\Listeners;

use App\Events\OrderStatusChanged;
use App\Notifications\OrderNotification;

class NotifyAdminsOfStatusChange
{
    use Concerns\ResolvesOrderAdmins;

    public function handle(OrderStatusChanged $event): void
    {
        $this->notify($event->order, new OrderNotification($event->order, 'status_changed', ['from' => $event->from?->value, 'to' => $event->to->value]));
    }
}
