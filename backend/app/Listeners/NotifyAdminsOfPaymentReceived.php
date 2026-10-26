<?php

namespace App\Listeners;

use App\Events\OrderPaymentUpdated;
use App\Notifications\OrderNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

/** Online gateway money arrived (proof approvals are an admin's own action and are not announced). */
class NotifyAdminsOfPaymentReceived implements ShouldQueue
{
    public bool $afterCommit = true;

    public int $tries = 3;

    use Concerns\ResolvesOrderAdmins;

    public function handle(OrderPaymentUpdated $event): void
    {
        if ($event->kind === 'paid') {
            $this->notify($event->order, new OrderNotification($event->order, 'payment_received'));
        }
    }
}
