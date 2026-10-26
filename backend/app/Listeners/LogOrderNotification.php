<?php

namespace App\Listeners;

use App\Models\Order;
use App\Notifications\CustomerOrderNotification;
use App\Notifications\OrderNotification;
use App\Support\Mask;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Events\NotificationSent;

/**
 * Audit trail of every order notification actually delivered (or failed): log "notification", subject = the order,
 * who got it (customer e-mail masked / staff id), channel and kind. Database/broadcast rows of staff are logged once (database) to avoid noise.
 */
class LogOrderNotification
{
    public function handle(NotificationSent|NotificationFailed $e): void
    {
        $n = $e->notification;
        if (! ($n instanceof OrderNotification || $n instanceof CustomerOrderNotification) || $e->channel === 'broadcast') {
            return;
        }
        /** @var Order $order */
        $order = $n->order();
        $customer = $n instanceof CustomerOrderNotification;
        $to = $e->notifiable instanceof AnonymousNotifiable
            ? Mask::value((string) collect((array) ($e->notifiable->routes['mail'] ?? []))->map(fn ($v, $k) => is_string($k) ? $k : $v)->first())
            : 'admin#'.$e->notifiable->getKey();

        activity('notification')->performedOn($order)->event($e instanceof NotificationFailed ? 'failed' : 'sent')
            ->withProperties(['channel' => $e->channel, 'kind' => $n->kind(), 'audience' => $customer ? 'customer' : 'staff', 'to' => $to])
            ->tap(fn ($a) => $a->tenant_id = $order->tenant_id)
            ->log(($customer ? 'customer.' : 'staff.').$n->kind().'.'.$e->channel);
    }
}
