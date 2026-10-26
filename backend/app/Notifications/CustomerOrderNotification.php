<?php

namespace App\Notifications;

use App\Models\Order;
use App\Models\OrderReturn;
use App\Support\Money;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * E-mail to the customer (guest or account; sent to an anonymous route so the address on the order wins).
 * kind: placed|status_changed|proof_approved|proof_rejected|paid|return_requested|return_approved|return_rejected|return_refunded
 */
class CustomerOrderNotification extends Notification
{
    public function __construct(private Order $order, private string $kind, private ?string $status = null, private ?OrderReturn $return = null)
    {
        $this->locale($order->locale ?: $order->tenant?->default_locale ?: config('app.locale'));
    }

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
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $o = $this->order;
        $store = $o->tenant?->name ?? config('app.name');
        $statusLabel = $this->status ? __('orders.enums.order_status.'.$this->status) : '';
        $p = ['number' => $o->number, 'status' => $statusLabel, 'return' => $this->return?->number, 'store' => $store,
            'amount' => Money::format((string) ($this->return?->refund_amount ?? 0), $o->currency)];
        $key = 'notifications.customer.'.$this->kind;

        $mail = (new MailMessage)->from(config('mail.from.address'), $store)->subject(__("$key.subject", $p))
            ->greeting(__('notifications.customer.greeting', ['name' => $o->customer_name ?: __('notifications.customer.guest')]))
            ->line(__("$key.line", $p))
            ->line(__('notifications.customer.order').': '.$o->number)
            ->line(__('notifications.customer.total').': '.Money::format((string) $o->total, $o->currency))
            ->action(__('notifications.customer.view'), tenant_url($o->tenant, ($this->locale ?? app()->getLocale()).'/order/'.$o->public_token))
            ->line(__('notifications.customer.thanks', ['store' => $store]));
        if ($o->tenant?->settings['support_email'] ?? false) {
            $mail->replyTo($o->tenant->settings['support_email']);
        }

        return $mail;
    }
}
