<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderReturn;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Activity;

/** Audit rows that belong to one order (order, its returns, notifications sent) or one customer (account + all their orders), tenant-scoped. */
class ActivityTimeline
{
    public function forOrder(Order $order, int $limit = 60): Collection
    {
        $returns = OrderReturn::where('order_id', $order->id)->pluck('id');

        return $this->base($order->tenant_id, $limit)->where(fn ($q) => $q
            ->where(fn ($w) => $w->where('subject_type', Order::class)->where('subject_id', $order->id))
            ->orWhere(fn ($w) => $w->where('subject_type', OrderReturn::class)->whereIn('subject_id', $returns)))->get();
    }

    public function forCustomer(Customer $customer, int $limit = 60): Collection
    {
        $orders = Order::where('customer_id', $customer->id)->pluck('id');

        return $this->base($customer->tenant_id, $limit)->where(fn ($q) => $q
            ->where(fn ($w) => $w->where('subject_type', Customer::class)->where('subject_id', $customer->id))
            ->orWhere(fn ($w) => $w->where('subject_type', Order::class)->whereIn('subject_id', $orders)))->get();
    }

    private function base(?int $tenantId, int $limit)
    {
        return Activity::query()->with('causer')->where('tenant_id', $tenantId)->latest('id')->limit($limit);
    }
}
