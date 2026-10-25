<?php

namespace App\Services\Orders;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Support\Money;

/** orders.net_profit lifecycle (docs/CALCULATIONS.md §Profit): 0 until an admin confirms; reduced by approved returns; 0 again on cancel/returned. */
class OrderProfitService
{
    public function recalculate(Order $order): Order
    {
        $net = '0.00';
        if (in_array($order->status, OrderStatus::salesStatuses(), true)) {
            $goods = $order->revenueGoods(); // subtotal − discount − VAT inside inclusive prices
            $income = Money::add(Money::sub($goods, (string) $order->cogs), (string) $order->shipping_net, (string) $order->payment_fee_charged, (string) $order->cod_fee);
            $net = Money::sub($income, Money::add((string) $order->shipping_cost, (string) $order->payment_fee, (string) $order->affiliate_commission));

            $loss = '0';
            $returned = '0';
            foreach (OrderReturn::where('order_id', $order->id)->whereIn('status', ['approved', 'refunded'])->get() as $r) {
                $returned = Money::add($returned, (string) $r->return_value);
                $loss = Money::add($loss, Money::sub((string) $r->return_value, (string) $r->returned_cogs), (string) $r->return_shipping_cost);
            }
            // the VAT inside returned goods of an inclusive order was never profit, so it is not reversed (cumulative: exact)
            $vat = Money::sub($returned, $order->returnNetOfTax($returned));
            $net = Money::add(Money::sub($net, $loss), $vat);
        }
        $order->forceFill(['net_profit' => $net])->save();

        return $order;
    }
}
