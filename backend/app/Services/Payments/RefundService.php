<?php

namespace App\Services\Payments;

use App\DTOs\Payments\PaymentResult;
use App\Enums\PaymentMethodType;
use App\Models\Order;
use App\Support\Money;

/** Provider refunds. null = nothing to send to a provider (wallet/COD money is moved manually). */
class RefundService
{
    public function __construct(private PaymentGatewayFactory $gateways) {}

    /** Portion of $amount that was paid through an online gateway and must go back through it. */
    public function onlinePortion(Order $order, string $amount, bool $includesShipping = true): string
    {
        $payer = $order->payingMethod();
        if (! $payer || $payer->type !== PaymentMethodType::ONLINE || ! $order->gateway_reference) {
            return '0.00';
        }
        if ($order->prepay_method_id) {
            // COD with an online prepaid shipping leg: only the shipping part was paid online
            return $includesShipping ? Money::min($amount, (string) $order->prepaid_shipping) : '0.00';
        }

        return Money::round($amount);
    }

    /** @return PaymentResult|null result of the provider refund (status 'refunded' only on success), null when no provider is involved */
    public function refundOnline(Order $order, string $amount, bool $includesShipping = true): ?PaymentResult
    {
        $portion = $this->onlinePortion($order, $amount, $includesShipping);
        if (Money::cmp($portion, '0') <= 0) {
            return null;
        }

        try {
            return $this->gateways->make($order->payingMethod())->refund((string) $order->gateway_reference, $portion);
        } catch (\Throwable $e) {
            report($e);

            return new PaymentResult('failed', (string) $order->gateway_reference, null, ['error' => $e->getMessage()]);
        }
    }
}
