<?php

namespace App\Services\Orders;

use App\Enums\OrderStatus;
use App\Enums\ReturnReasonCode;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Setting;
use Illuminate\Validation\ValidationException;

/**
 * Customer-facing cancellation (setting `customer_cancel.enabled`, Settings → General, default OFF): the owner of an order that has not shipped
 * (pending / confirmed / processing) may cancel it, choosing a structured reason code. The cancel itself is the normal status machine
 * (stock release, refund rules unchanged); only the reason code is stored (orders.cancel_reason_code).
 */
class CustomerCancellationService
{
    public function __construct(private OrderStatusService $status) {}

    public function enabled(): bool
    {
        return (bool) setting('customer_cancel.enabled', false);
    }

    public function setEnabled(bool $on): void
    {
        $row = (array) (setting('customer_cancel') ?? []);
        $row['enabled'] = $on;
        Setting::put('customer_cancel', $row);
    }

    public function canCancel(Order $o, ?Customer $c): bool
    {
        return $this->enabled() && $c && $o->customer_id === $c->id && $o->status->cancellableBeforeShipping();
    }

    public function cancel(Order $o, ?Customer $c, ?string $code): Order
    {
        abort_unless($this->enabled(), 404);
        abort_unless($c && $o->customer_id === $c->id, 403);
        if (! $o->status->cancellableBeforeShipping()) {
            throw ValidationException::withMessages(['status' => __('orders.customer_cancel.not_allowed')]);
        }
        if (! $code || ! ReturnReasonCode::tryFrom($code)) {
            throw ValidationException::withMessages(['reason_code' => __('orders.customer_cancel.reason_required')]);
        }
        $this->status->change($o, OrderStatus::CANCELLED, __('orders.customer_cancel.note'));
        $o->update(['cancel_reason_code' => $code]);

        return $o->refresh();
    }
}
