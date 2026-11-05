<?php

namespace App\Listeners\Concerns;

use App\Models\Order;
use App\Models\TenantAdmin;
use App\Services\Tenancy\TenantContext;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Gate;

/** Notifies the active admins of the order's tenant that may view orders (the notification itself decides on e-mail). Called from queued listeners. */
trait ResolvesOrderAdmins
{
    protected function notify(Order $order, Notification $notification): void
    {
        $notification->locale($order->tenant?->default_locale ?: config('app.locale'));
        $send = fn () => TenantAdmin::withoutGlobalScopes()->where('tenant_id', $order->tenant_id)->where('is_active', true)->get()
            ->filter(fn (TenantAdmin $a) => Gate::forUser($a)->allows('orders.view'))
            ->each(fn (TenantAdmin $a) => $a->notify($notification));

        // the worker runs in UTC: notify inside the tenant context so created_at is stored in the tenant timezone like web requests
        $order->tenant ? TenantContext::run($order->tenant, $send) : $send();
    }
}
