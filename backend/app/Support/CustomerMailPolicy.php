<?php

namespace App\Support;

use App\Models\Order;
use App\Notifications\CustomerOrderNotification;
use Illuminate\Support\Facades\Notification;

/** Decides whether (and where) a customer e-mail goes: address on the order, valid, and the tenant has that group switched on (default on). */
class CustomerMailPolicy
{
    public const GROUPS = ['placed', 'status', 'payment', 'return'];

    public static function enabled(Order $order, string $group): bool
    {
        return (bool) (($order->tenant?->settings['notifications'][$group] ?? true));
    }

    public static function send(Order $order, string $group, CustomerOrderNotification $n): void
    {
        $email = $order->customer_email ?: $order->customer?->email;
        if (! $email || ! filter_var($email, FILTER_VALIDATE_EMAIL) || ! self::enabled($order, $group)) {
            return;
        }
        Notification::route('mail', [$email => $order->customer_name ?: $email])->notify($n);
    }
}
