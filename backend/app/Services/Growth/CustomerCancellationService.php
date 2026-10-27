<?php

namespace App\Services\Growth;

use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Services\Orders\OrderStatusService;
use Illuminate\Validation\ValidationException;

/**
 * Customer-facing cancellation (feature `return_intel`): the owner may cancel an order that has not shipped, choosing a structured reason code (N6 codes).
 * The cancel itself is the normal status machine (stock release, refund rules unchanged); only the reason code is new.
 */
class CustomerCancellationService
{
    public function __construct(private OrderStatusService $status, private ReturnIntelService $returns, private GrowthFeatures $features) {}

    public function enabled(): bool
    {
        return $this->features->active('return_intel');
    }

    public function canCancel(Order $o, ?Customer $c): bool
    {
        return $this->enabled() && $c && $o->customer_id === $c->id && in_array(OrderStatus::CANCELLED, $o->status->next(), true);
    }

    public function cancel(Order $o, ?Customer $c, ?string $code): Order
    {
        abort_unless($this->enabled(), 404);
        abort_unless($c && $o->customer_id === $c->id, 403);
        if (! in_array(OrderStatus::CANCELLED, $o->status->next(), true)) {
            throw ValidationException::withMessages(['status' => __('growth_gaps.cancel_not_allowed')]);
        }
        if (! $code || ! in_array($code, ReturnIntelService::CODES, true)) {
            throw ValidationException::withMessages(['reason_code' => __('growth_gaps.cancel_reason_required')]);
        }
        $this->status->change($o, OrderStatus::CANCELLED, __('growth_gaps.cancel_note'));
        $o->update(['cancel_reason_code' => $code]);

        return $o->refresh();
    }
}
