<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/** ERP staff notification (database only; title rendered at read time by NotificationText). kind: erp_posting_failed */
class ErpNotification extends Notification
{
    public function __construct(private string $kind, private array $data = []) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return ['kind' => $this->kind] + $this->data;
    }
}
