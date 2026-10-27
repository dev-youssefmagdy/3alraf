<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/** Growth staff notification (database only; title rendered at read time by NotificationText). kind: ad_cap_exceeded */
class GrowthNotification extends Notification
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
