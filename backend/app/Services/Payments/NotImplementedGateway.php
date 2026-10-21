<?php

namespace App\Services\Payments;

use App\DTOs\Payments\PaymentIntentData;
use App\DTOs\Payments\PaymentResult;
use App\Exceptions\GatewayNotImplementedException;
use App\Models\Order;
use Illuminate\Http\Request;

/** Clean stub for providers that are planned but not built yet (paypal, paytabs, stripe). Never offered at checkout. */
abstract class NotImplementedGateway extends AbstractGateway
{
    public function createPayment(PaymentIntentData $data): PaymentResult
    {
        throw new GatewayNotImplementedException($this->key().' gateway is not implemented yet.');
    }

    public function verifyWebhook(Request $request): bool
    {
        return false;
    }

    public function orderReference(Request $request): ?string
    {
        return null;
    }

    public function parseWebhook(Request $request, Order $order, string $expectedAmount): PaymentResult
    {
        throw new GatewayNotImplementedException($this->key().' gateway is not implemented yet.');
    }

    public function refund(string $gatewayReference, ?string $amount = null): PaymentResult
    {
        return new PaymentResult('failed', $gatewayReference, null, ['error' => 'not_implemented']);
    }
}
