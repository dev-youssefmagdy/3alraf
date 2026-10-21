<?php

namespace App\Listeners\Concerns;

use App\Models\Order;
use App\Models\TenantAdmin;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Gate;

/** Notifies the active admins of the order's tenant that may view orders. Called from queued listeners. */
trait ResolvesOrderAdmins
{
    protected function notify(Order $order, Notification $notification): void
    {
        TenantAdmin::withoutGlobalScopes()->where('tenant_id', $order->tenant_id)->where('is_active', true)->get()
            ->filter(fn (TenantAdmin $a) => Gate::forUser($a)->allows('orders.view'))
            ->each(fn (TenantAdmin $a) => $a->notify($notification));
    }
}
