<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

/** Bell dropdown: polled every 15s by resources/js/lib/orders-form.js. */
class NotificationController extends Controller
{
    public function index(Request $r): JsonResponse
    {
        $admin = auth('tenant')->user();
        $latest = $admin->notifications()->limit(10)->get();
        $after = $r->integer('after');
        $fresh = $admin->unreadNotifications()->when($after, fn ($q) => $q->where('created_at', '>=', \Illuminate\Support\Carbon::createFromTimestamp($after)))->limit(10)->get();

        return response()->json([
            'unread' => $admin->unreadNotifications()->count(),
            'items' => $latest->map(fn ($n) => $this->present($n))->values(),
            'fresh' => $fresh->map(fn ($n) => $this->present($n))->values(),
            'latest' => $admin->notifications()->max('created_at') ? \Illuminate\Support\Carbon::parse($admin->notifications()->max('created_at'))->timestamp : 0,
        ]);
    }

    public function read(string $id): JsonResponse
    {
        auth('tenant')->user()->notifications()->whereKey($id)->first()?->markAsRead();

        return response()->json(['success' => true]);
    }

    public function readAll(): JsonResponse
    {
        auth('tenant')->user()->unreadNotifications->markAsRead();

        return response()->json(['success' => true]);
    }

    private function present(DatabaseNotification $n): array
    {
        $d = $n->data;
        $num = $d['number'] ?? '';
        $title = match ($d['kind'] ?? '') {
            'new_order' => __('orders.notify.new_order', ['number' => $num, 'customer' => $d['customer'] ?? '', 'total' => \App\Support\Money::format($d['total'] ?? 0, $d['currency'] ?? '')]),
            'status_changed' => __('orders.notify.status_changed', ['number' => $num, 'status' => __('orders.enums.order_status.'.($d['to'] ?? 'pending'))]),
            'proof_uploaded' => __('orders.notify.proof_uploaded', ['number' => $num]),
            default => $num,
        };

        return ['id' => $n->id, 'title' => $title, 'url' => isset($d['order_id']) ? route('tenant.orders.show', $d['order_id']) : '#', 'read' => (bool) $n->read_at, 'time' => $n->created_at->diffForHumans()];
    }
}
