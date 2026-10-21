<?php

namespace App\Services\Payments;

use App\DTOs\Payments\PaymentIntentData;
use App\DTOs\Payments\PaymentResult;
use Illuminate\Http\Request;

interface PaymentGatewayInterface
{
    /** Provider key (PaymentProvider value). */
    public function key(): string;

    /** Start a payment; returns pending + redirect/iframe URL. */
    public function createPayment(PaymentIntentData $data): PaymentResult;

    /** Verify the webhook signature of an incoming request (constant-time). */
    public function verifyWebhook(Request $request): bool;

    /** Map a verified webhook to a normalised result (status + our order reference in raw['order_reference']). */
    public function parseWebhook(Request $request): PaymentResult;

    /** Refund (full when $amount null). */
    public function refund(string $gatewayReference, ?string $amount = null): PaymentResult;
}
