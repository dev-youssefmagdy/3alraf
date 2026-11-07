<?php

namespace App\Listeners\Concerns;

use App\Models\Order;
use App\Models\Admin;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Gate;

/** Notifies the active admins that may view orders (the notification itself decides on e-mail). Called from queued listeners. */
trait ResolvesOrderAdmins
{
    protected function notify(Order $order, Notification $notification): void
    {
        $notification->locale(setting('default_locale') ?: config('app.locale'));
        // the shop timezone is set once at boot (AppServiceProvider), so queued and web notifications store the same wall-clock time
        Admin::query()->where('is_active', true)->get()
            ->filter(fn (Admin $a) => Gate::forUser($a)->allows('orders.view'))
            ->each(fn (Admin $a) => $a->notify($notification));
    }
}
