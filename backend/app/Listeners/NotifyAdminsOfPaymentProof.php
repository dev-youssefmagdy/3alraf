<?php

namespace App\Listeners;

use App\Events\OrderPaymentProofUploaded;
use App\Notifications\OrderNotification;

class NotifyAdminsOfPaymentProof
{
    use Concerns\ResolvesOrderAdmins;

    public function handle(OrderPaymentProofUploaded $event): void
    {
        $this->notify($event->order, new OrderNotification($event->order, 'proof_uploaded'));
    }
}
