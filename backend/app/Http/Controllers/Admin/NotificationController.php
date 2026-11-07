<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Support\NotificationText;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;

/** Bell dropdown: polled every 15s by resources/js/lib/orders-form.js. */
class NotificationController extends Controller
{
    public function index(Request $r): JsonResponse
    {
        $admin = $this->admin($r);
        $latest = $admin->notifications()->limit(10)->get();
        $after = $r->integer('after');
        $fresh = $admin->unreadNotifications()->when($after, fn ($q) => $q->where('created_at', '>=', Carbon::createFromTimestamp($after)))->limit(10)->get();

        return response()->json([
            'unread' => $admin->unreadNotifications()->count(),
            'items' => $latest->map(fn ($n) => $this->present($n))->values(),
            'fresh' => $fresh->map(fn ($n) => $this->present($n))->values(),
            'latest' => $admin->notifications()->max('created_at') ? Carbon::parse($admin->notifications()->max('created_at'))->timestamp : 0,
        ]);
    }

    public function read(Request $r, string $id): JsonResponse
    {
        $this->admin($r)->notifications()->whereKey($id)->first()?->markAsRead();

        return response()->json(['success' => true]);
    }

    public function readAll(Request $r): JsonResponse
    {
        $this->admin($r)->unreadNotifications->markAsRead();

        return response()->json(['success' => true]);
    }

    /** Same endpoints for both panels: guard from the route-name prefix (central.* / tenant.*). */
    private function admin(Request $r)
    {
        return auth(str_starts_with((string) $r->route()->getName(), 'central.') ? 'central' : 'tenant')->user();
    }

    private function present(DatabaseNotification $n): array
    {
        $d = $n->data;
        $url = match (true) {
            isset($d['order_id']) => route('tenant.orders.show', $d['order_id']),
            ($d['kind'] ?? '') === 'ad_cap_exceeded' => route('tenant.growth.lab.index'),
            isset($d['tenant_id']) => route('central.tenants.edit', $d['tenant_id']),
            default => '#',
        };

        return ['id' => $n->id, 'title' => NotificationText::title($d), 'url' => $url, 'read' => (bool) $n->read_at, 'time' => $n->created_at->diffForHumans()];
    }
}
