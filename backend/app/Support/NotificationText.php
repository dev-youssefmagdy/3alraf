<?php

namespace App\Support;

/** One place that turns stored staff-notification data into a translated title (bell + e-mail subject). */
class NotificationText
{
    public static function title(array $d): string
    {
        $num = $d['number'] ?? '';
        $total = Money::format($d['total'] ?? 0, $d['currency'] ?? '');

        return match ($d['kind'] ?? '') {
            'new_order' => __('notifications.admin.new_order', ['number' => $num, 'customer' => $d['customer'] ?? '', 'total' => $total]),
            'status_changed' => __('notifications.admin.status_changed', ['number' => $num, 'status' => __('orders.enums.order_status.'.($d['to'] ?? 'pending'))]),
            'proof_uploaded' => __('notifications.admin.proof_uploaded', ['number' => $num]),
            'payment_received' => __('notifications.admin.payment_received', ['number' => $num, 'total' => $total]),
            'return_requested' => __('notifications.admin.return_requested', ['number' => $num, 'return' => $d['return'] ?? '']),
            'tenant_created' => __('notifications.admin.tenant_created', ['name' => $d['name'] ?? '']),
            'tenant_status' => __('notifications.admin.tenant_status', ['name' => $d['name'] ?? '', 'state' => __('notifications.admin.'.(($d['active'] ?? false) ? 'tenant_active' : 'tenant_inactive'))]),
            default => $num,
        };
    }
}
