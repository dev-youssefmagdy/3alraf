<?php

namespace App\Services\Orders;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethodType;
use App\Models\Order;
use Illuminate\Validation\ValidationException;

/**
 * POS "paid at the counter" (ERP-014): the walk-in customer pays cash at the register and takes the goods, so the placed order runs
 * confirmed -> processing -> shipped -> delivered at once (stock deducted, revenue recognised) with paid_amount = total. It is flagged
 * counter_sale with cod_amount = 0: no carrier ever collects it (excluded from carrier settlement), the ERP books the cash into POS cash.
 */
class CounterSaleService
{
    public function __construct(private OrderStatusService $status) {}

    public function complete(Order $order): Order
    {
        if ($order->paymentMethod?->type !== PaymentMethodType::COD || $order->prepay_method_id || $order->status !== OrderStatus::PENDING) {
            throw ValidationException::withMessages(['payment_method_id' => __('erp_acc.counter_sale_cod_only')]);
        }
        $order->forceFill(['counter_sale' => true, 'cod_amount' => 0])->save();
        foreach ([OrderStatus::CONFIRMED, OrderStatus::PROCESSING, OrderStatus::SHIPPED, OrderStatus::DELIVERED] as $to) {
            $order = $this->status->change($order, $to, __('erp_acc.counter_sale_note'));
        }

        return $order->refresh();
    }
}
