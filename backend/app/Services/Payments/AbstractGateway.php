<?php

namespace App\Services\Payments;

use App\DTOs\Payments\PaymentIntentData;
use App\DTOs\Payments\PaymentResult;
use App\Models\PaymentMethod;
use Illuminate\Http\Request;

/**
 * Sandbox-ready skeleton. Concrete drivers declare the signature header/secret and fill the TODO API calls.
 * Credentials come from the (encrypted) payment_methods.credentials of the tenant.
 */
abstract class AbstractGateway implements PaymentGatewayInterface
{
    public function __construct(protected PaymentMethod $method) {}

    protected function credential(string $key): ?string
    {
        return $this->method->credentials[$key] ?? null;
    }

    protected function sandbox(): bool
    {
        return $this->method->is_sandbox;
    }

    /** Credential key holding the webhook signing secret. */
    abstract protected function secretKey(): string;

    /** Header carrying the signature (or request key for gateways that sign in the payload). */
    abstract protected function signatureHeader(): string;

    public function createPayment(PaymentIntentData $data): PaymentResult
    {
        // TODO: call the provider API (sandbox when is_sandbox) and return its redirect/iframe URL.
        return new PaymentResult('pending', 'stub-'.$this->key().'-'.$data->orderReference, null, ['sandbox' => $this->sandbox(), 'order_reference' => $data->orderReference]);
    }

    /** Default stub: HMAC-SHA256 of the raw body vs signature header. Override per provider spec. */
    public function verifyWebhook(Request $request): bool
    {
        $secret = $this->credential($this->secretKey());
        $given = (string) $request->header($this->signatureHeader(), $request->input($this->signatureHeader(), ''));
        if (! $secret || $given === '') {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $request->getContent(), $secret), $given);
    }

    public function parseWebhook(Request $request): PaymentResult
    {
        // TODO: map provider payload -> status. Skeleton trusts a normalised payload.
        $paid = filter_var($request->input('success', false), FILTER_VALIDATE_BOOL);

        return new PaymentResult($paid ? 'paid' : 'failed', (string) $request->input('id'), null, ['order_reference' => $request->input('order_reference'), 'payload' => $request->all()]);
    }

    public function refund(string $gatewayReference, ?string $amount = null): PaymentResult
    {
        // TODO: call provider refund API.
        return new PaymentResult('pending', $gatewayReference, null, ['amount' => $amount]);
    }
}
