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
            'erp_posting_failed' => __('erp_acc.notify.posting_failed', ['subject' => ($d['subject_type'] ?? '').' #'.($d['subject_id'] ?? ''), 'error' => \Illuminate\Support\Str::limit((string) ($d['error'] ?? ''), 120)]),
            default => $num,
        };
    }
}
