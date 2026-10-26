<?php

namespace App\Notifications;

use App\Models\Tenant;
use App\Support\NotificationText;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/** Central-panel notification (DB + realtime). kind: tenant_created|tenant_status */
class TenantNotification extends Notification
{
    public function __construct(private Tenant $tenant, private string $kind) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toArray(object $notifiable): array
    {
        return ['kind' => $this->kind, 'tenant_id' => $this->tenant->id, 'name' => $this->tenant->name, 'active' => (bool) $this->tenant->is_active];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable) + ['title' => NotificationText::title($this->toArray($notifiable))]);
    }
}
