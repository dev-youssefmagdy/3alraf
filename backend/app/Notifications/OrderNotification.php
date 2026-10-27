<?php

namespace App\Notifications;

use App\Models\Order;
use App\Support\Money;
use App\Support\NotificationText;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Staff notification for tenant admins. Stored in the DB and pushed over the admin's private channel; rendered (translated) at read time.
 * E-mail only for MAILABLE kinds, admins holding `orders.email_notifications`, never to the admin who caused the change.
 * kind: new_order|status_changed|proof_uploaded|payment_received|return_requested
 */
class OrderNotification extends Notification
{
    public const MAILABLE = ['new_order', 'proof_uploaded', 'return_requested'];

    public function __construct(private Order $order, private string $kind, private array $extra = [], private ?int $actorId = null) {}

    public function order(): Order
    {
        return $this->order;
    }

    public function kind(): string
    {
        return $this->kind;
    }

    public function via(object $notifiable): array
    {
        $via = ['database', 'broadcast'];
        if (in_array($this->kind, self::MAILABLE, true) && $notifiable->email && $notifiable->getKey() !== $this->actorId && $notifiable->can('orders.email_notifications')) {
            $via[] = 'mail';
        }

        return $via;
    }

    public function toArray(object $notifiable): array
    {
        return ['kind' => $this->kind, 'order_id' => $this->order->id, 'number' => $this->order->number, 'total' => (string) $this->order->total,
            'currency' => $this->order->currency, 'customer' => $this->order->customer_name] + $this->extra;
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable) + ['title' => NotificationText::title($this->toArray($notifiable))]);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $d = $this->toArray($notifiable);
        $url = tenant_url($this->order->tenant, 'admin/orders/'.$this->order->id);

        return (new MailMessage)->subject(NotificationText::title($d))
            ->greeting(__('notifications.admin_mail.greeting', ['name' => $notifiable->name]))
            ->line(NotificationText::title($d))
            ->line(__('notifications.admin_mail.customer').': '.$this->order->customer_name.' ('.($this->order->phone_display ?: $this->order->customer_phone).')')
            ->line(__('notifications.admin_mail.total').': '.Money::format((string) $this->order->total, $this->order->currency))
            ->line(__('notifications.admin_mail.payment').': '.($this->order->paymentMethod?->name ?? '-').' / '.$this->order->payment_status->label())
            ->action(__('notifications.admin_mail.view'), $url);
    }
}
