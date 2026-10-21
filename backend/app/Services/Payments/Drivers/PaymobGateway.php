<?php

namespace App\Services\Payments\Drivers;

use App\DTOs\Payments\PaymentIntentData;
use App\DTOs\Payments\PaymentResult;
use App\Exceptions\GatewayException;
use App\Exceptions\PaymentMismatchException;
use App\Models\Order;
use App\Services\Payments\AbstractGateway;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

/**
 * Paymob Accept (Egypt) card/wallet iframe flow.
 *  1. POST /api/auth/tokens {api_key}                         -> token
 *  2. POST /api/ecommerce/orders {auth_token, amount_cents, merchant_order_id = our order number, ...} -> id
 *  3. POST /api/acceptance/payment_keys {auth_token, order_id, integration_id, billing_data...}        -> token
 *  4. redirect: /api/acceptance/iframes/{iframe_id}?payment_token={token}
 * Webhook (processed callback) = POST {type:'TRANSACTION', obj:{...}} + ?hmac=  (HMAC-SHA512 of the documented fields,
 * concatenated in lexicographic key order). The transaction is then re-queried: GET /api/acceptance/transactions/{id}.
 * Refund: POST /api/acceptance/void_refund/refund {transaction_id, amount_cents}.
 */
class PaymobGateway extends AbstractGateway
{
    /** Order of the HMAC input (Paymob docs: "Transaction processed callback"). */
    private const HMAC_FIELDS = ['amount_cents', 'created_at', 'currency', 'error_occured', 'has_parent_transaction', 'id', 'integration_id',
        'is_3d_secure', 'is_auth', 'is_capture', 'is_refunded', 'is_standalone_payment', 'is_voided', 'order.id', 'owner', 'pending',
        'source_data.pan', 'source_data.sub_type', 'source_data.type', 'success'];

    public function key(): string
    {
        return 'paymob';
    }

    private function authToken(): string
    {
        $token = $this->send(fn ($h) => $h->post($this->baseUrl().'/api/auth/tokens', ['api_key' => $this->credential('api_key')]))->json('token');
        if (! $token) {
            throw new GatewayException('paymob: no auth token');
        }

        return $token;
    }

    public function createPayment(PaymentIntentData $data): PaymentResult
    {
        $auth = $this->authToken();
        $cents = $this->cents($data->amount);

        $order = $this->send(fn ($h) => $h->post($this->baseUrl().'/api/ecommerce/orders', [
            'auth_token' => $auth, 'delivery_needed' => false, 'amount_cents' => $cents, 'currency' => $data->currency,
            'merchant_order_id' => $data->orderReference, 'items' => [],
        ]));
        $orderId = $order->json('id');
        if (! $orderId) {
            throw new GatewayException('paymob: order registration failed');
        }

        $name = trim((string) ($data->customer['name'] ?? '')) ?: 'NA';
        [$first, $last] = array_pad(explode(' ', $name, 2), 2, 'NA');
        $key = $this->send(fn ($h) => $h->post($this->baseUrl().'/api/acceptance/payment_keys', [
            'auth_token' => $auth, 'amount_cents' => $cents, 'expiration' => 3600, 'order_id' => $orderId, 'currency' => $data->currency,
            'integration_id' => (int) $this->credential('integration_id'), 'lock_order_when_paid' => true,
            'billing_data' => [
                'first_name' => $first, 'last_name' => $last, 'email' => $data->customer['email'] ?: 'na@example.com',
                'phone_number' => $data->customer['phone'] ?: 'NA', 'apartment' => 'NA', 'floor' => 'NA', 'street' => 'NA', 'building' => 'NA',
                'shipping_method' => 'NA', 'postal_code' => 'NA', 'city' => 'NA', 'country' => 'EG', 'state' => 'NA',
            ],
        ]))->json('token');
        if (! $key) {
            throw new GatewayException('paymob: payment key failed');
        }

        $url = $this->baseUrl().'/api/acceptance/iframes/'.rawurlencode($this->credential('iframe_id')).'?payment_token='.$key;

        return new PaymentResult('pending', (string) $orderId, $url, ['sandbox' => $this->method->is_sandbox, 'order_reference' => $data->orderReference]);
    }

    public function verifyWebhook(Request $request): bool
    {
        $secret = $this->credential('hmac_secret');
        $obj = $request->input('obj');
        $given = (string) ($request->query('hmac') ?? $request->input('hmac', ''));
        if ($secret === '' || ! is_array($obj) || $given === '') {
            return false;
        }

        return $this->constantEquals(self::hmac($obj, $secret), strtolower($given));
    }

    public static function hmac(array $obj, string $secret): string
    {
        $concat = '';
        foreach (self::HMAC_FIELDS as $f) {
            $v = Arr::get($obj, $f);
            $concat .= is_bool($v) ? ($v ? 'true' : 'false') : (string) ($v ?? '');
        }

        return hash_hmac('sha512', $concat, $secret);
    }

    public function orderReference(Request $request): ?string
    {
        $ref = Arr::get((array) $request->input('obj'), 'order.merchant_order_id');

        return $this->stripRetry(is_scalar($ref) ? (string) $ref : null);
    }

    public function parseWebhook(Request $request, Order $order, string $expectedAmount): PaymentResult
    {
        $obj = (array) $request->input('obj');
        $id = Arr::get($obj, 'id');
        if (! $id) {
            throw new PaymentMismatchException('paymob: no transaction id');
        }

        // Never trust the callback body alone: re-query the transaction from Paymob.
        $auth = $this->authToken();
        $tx = $this->send(fn ($h) => $h->withToken($auth)->get($this->baseUrl().'/api/acceptance/transactions/'.$id))->json();

        if ($this->stripRetry(Arr::get($tx, 'order.merchant_order_id')) !== $order->number) {
            throw new PaymentMismatchException('paymob: order reference mismatch');
        }
        if ((int) Arr::get($tx, 'amount_cents') !== $this->cents($expectedAmount) || (int) Arr::get($obj, 'amount_cents') !== $this->cents($expectedAmount)) {
            throw new PaymentMismatchException('paymob: amount mismatch');
        }
        if (strtoupper((string) Arr::get($tx, 'currency')) !== strtoupper((string) $order->currency)) {
            throw new PaymentMismatchException('paymob: currency mismatch');
        }
        $success = filter_var(Arr::get($tx, 'success'), FILTER_VALIDATE_BOOL);
        if ($success !== filter_var(Arr::get($obj, 'success'), FILTER_VALIDATE_BOOL)) {
            throw new PaymentMismatchException('paymob: status mismatch with provider');
        }
        $pending = filter_var(Arr::get($tx, 'pending'), FILTER_VALIDATE_BOOL);
        $reversed = filter_var(Arr::get($tx, 'is_refunded'), FILTER_VALIDATE_BOOL) || filter_var(Arr::get($tx, 'is_voided'), FILTER_VALIDATE_BOOL);

        $status = $reversed ? 'refunded' : ($pending ? 'pending' : ($success ? 'paid' : 'failed'));

        return new PaymentResult($status, (string) $id, null, ['order_reference' => $order->number, 'amount_cents' => (int) Arr::get($tx, 'amount_cents')], $id.':'.$status);
    }

    public function refund(string $gatewayReference, ?string $amount = null): PaymentResult
    {
        try {
            $auth = $this->authToken();
            $res = $this->send(fn ($h) => $h->withToken($auth)->post($this->baseUrl().'/api/acceptance/void_refund/refund', [
                'transaction_id' => $gatewayReference, 'amount_cents' => $this->cents((string) $amount),
            ]));
        } catch (GatewayException $e) {
            return new PaymentResult('failed', $gatewayReference, null, ['error' => $e->getMessage()]);
        }
        $ok = filter_var($res->json('success'), FILTER_VALIDATE_BOOL) && ! $res->json('error_occured');

        return new PaymentResult($ok ? 'refunded' : 'failed', (string) ($res->json('id') ?? $gatewayReference), null, ['amount' => $amount]);
    }
}
