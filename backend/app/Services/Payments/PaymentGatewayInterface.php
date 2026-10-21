<?php

namespace App\Services\Payments;

use App\DTOs\Payments\PaymentIntentData;
use App\DTOs\Payments\PaymentResult;
use App\Models\Order;
use Illuminate\Http\Request;

interface PaymentGatewayInterface
{
    /** Provider key (PaymentProvider value). */
    public function key(): string;

    /** Start a payment; returns pending + a real redirect/iframe URL. @throws \App\Exceptions\PaymentException */
    public function createPayment(PaymentIntentData $data): PaymentResult;

    /** Verify the webhook signature with the provider's own scheme (constant-time). */
    public function verifyWebhook(Request $request): bool;

    /** Our order number carried by the payload (retry suffix stripped). Only trust it AFTER verifyWebhook(). */
    public function orderReference(Request $request): ?string;

    /**
     * Normalise a VERIFIED webhook and cross-check amount, currency, provider status and order reference against OUR order.
     *
     * @throws \App\Exceptions\PaymentMismatchException on any disagreement
     * @throws \App\Exceptions\GatewayException when the provider cannot be re-queried
     */
    public function parseWebhook(Request $request, Order $order, string $expectedAmount): PaymentResult;

    /** Refund against the provider transaction reference (full when $amount null). status 'refunded' only on provider success, else 'failed'. */
    public function refund(string $gatewayReference, ?string $amount = null): PaymentResult;
}
