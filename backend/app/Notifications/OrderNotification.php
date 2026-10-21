<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Notifications\Notification;

/** Stored in the DB for tenant admins; rendered (translated) at read time. kind: new_order|status_changed|proof_uploaded */
class OrderNotification extends Notification
{
    public function __construct(private Order $order, private string $kind, private array $extra = []) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return ['kind' => $this->kind, 'order_id' => $this->order->id, 'number' => $this->order->number, 'total' => (string) $this->order->total,
            'currency' => $this->order->currency, 'customer' => $this->order->customer_name] + $this->extra;
    }
}
