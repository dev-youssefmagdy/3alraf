<?php

namespace App\Services\Payments\Drivers;

use App\DTOs\Payments\PaymentIntentData;
use App\DTOs\Payments\PaymentResult;
use App\Exceptions\GatewayException;
use App\Exceptions\PaymentMismatchException;
use App\Models\Order;
use App\Services\Payments\AbstractGateway;
use Illuminate\Http\Request;

/**
 * FawryPay (Egypt, EGP only).
 *  - Charge/redirect: POST {base}/fawrypay-api/api/payments/init -> hosted checkout URL.
 *    signature = SHA256(merchantCode + merchantRefNum + customerProfileId + returnUrl + itemId + quantity + price(2dp) + secureKey)
 *  - Webhook (v2, JSON): messageSignature = SHA256(fawryRefNumber + merchantRefNumber + paymentAmount(2dp) + orderAmount(2dp)
 *      + orderStatus + paymentMethod + paymentRefrenceNumber + secureKey)   ("Refrence" is Fawry's own spelling)
 *  - Refund: POST {base}/ECommerceWeb/Fawry/payments/refund, signature = SHA256(merchantCode + referenceNumber + refundAmount(2dp) + reason + secureKey)
 */
class FawryPayGateway extends AbstractGateway
{
    public function key(): string
    {
        return 'fawrypay';
    }

    private function fmt(mixed $v): string
    {
        return number_format((float) $v, 2, '.', '');
    }

    public function createPayment(PaymentIntentData $data): PaymentResult
    {
        if (strtoupper($data->currency) !== 'EGP') {
            throw new GatewayException('fawrypay: only EGP is supported');
        }
        $code = $this->credential('merchant_code');
        $profile = (string) ($data->customer['email'] ?? '');
        $price = $this->fmt($data->amount);
        $sig = hash('sha256', $code.$data->orderReference.$profile.($data->returnUrl ?? '').'1'.'1'.$price.$this->credential('secure_key'));

        $res = $this->send(fn ($h) => $h->post($this->baseUrl().'/fawrypay-api/api/payments/init', [
            'merchantCode' => $code, 'merchantRefNum' => $data->orderReference, 'customerName' => $data->customer['name'] ?? '',
            'customerMobile' => $data->customer['phone'] ?? '', 'customerEmail' => $data->customer['email'] ?? '', 'customerProfileId' => $profile,
            'paymentExpiry' => (now()->addHours(24)->getTimestamp()) * 1000, 'language' => app()->getLocale() === 'ar' ? 'ar-eg' : 'en-gb',
            'chargeItems' => [['itemId' => '1', 'description' => 'Order '.$data->orderReference, 'price' => (float) $price, 'quantity' => 1]],
            'returnUrl' => $data->returnUrl, 'orderWebHookUrl' => $data->webhookUrl, 'signature' => $sig,
        ]));
        $body = trim((string) $res->body());
        $url = str_starts_with($body, 'http') ? trim($body, "\"' ") : ($res->json('redirectUrl') ?? $res->json('url'));
        if (! $url) {
            throw new GatewayException('fawrypay: no checkout url');
        }

        return new PaymentResult('pending', $data->orderReference, $url, ['sandbox' => $this->method->is_sandbox, 'order_reference' => $data->orderReference]);
    }

    public function verifyWebhook(Request $request): bool
    {
        $key = $this->credential('secure_key');
        $given = (string) $request->input('messageSignature', '');
        if ($key === '' || $given === '' || ! $request->filled('fawryRefNumber')) {
            return false;
        }

        return $this->constantEquals($this->webhookSignature($request->all(), $key), strtolower($given));
    }

    public static function signatureFor(array $p, string $key): string
    {
        $f = fn ($v) => number_format((float) $v, 2, '.', '');

        return hash('sha256', ($p['fawryRefNumber'] ?? '').($p['merchantRefNumber'] ?? '').$f($p['paymentAmount'] ?? 0).$f($p['orderAmount'] ?? 0)
            .($p['orderStatus'] ?? '').($p['paymentMethod'] ?? '').($p['paymentRefrenceNumber'] ?? '').$key);
    }

    private function webhookSignature(array $p, string $key): string
    {
        return self::signatureFor($p, $key);
    }

    public function orderReference(Request $request): ?string
    {
        $ref = $request->input('merchantRefNumber');

        return $this->stripRetry(is_scalar($ref) ? (string) $ref : null);
    }

    public function parseWebhook(Request $request, Order $order, string $expectedAmount): PaymentResult
    {
        if ($this->orderReference($request) !== $order->number) {
            throw new PaymentMismatchException('fawrypay: order reference mismatch');
        }
        if (strtoupper((string) $order->currency) !== 'EGP') {
            throw new PaymentMismatchException('fawrypay: currency mismatch');
        }
        if (! $this->amountEquals($request->input('orderAmount'), $expectedAmount)) {
            throw new PaymentMismatchException('fawrypay: amount mismatch');
        }
        $provider = strtoupper((string) $request->input('orderStatus'));
        $status = match ($provider) {
            'PAID', 'DELIVERED' => 'paid',
            'NEW', 'UNPAID' => 'pending',
            'CANCELED', 'CANCELLED' => 'cancelled',
            'EXPIRED', 'FAILED' => 'failed',
            'REFUNDED', 'PARTIAL_REFUNDED' => 'refunded',
            default => throw new PaymentMismatchException('fawrypay: unknown status '.$provider),
        };
        if ($status === 'paid' && (float) $request->input('paymentAmount') + 0.001 < (float) $expectedAmount) {
            throw new PaymentMismatchException('fawrypay: paid amount below order amount');
        }
        $ref = (string) $request->input('fawryRefNumber');

        return new PaymentResult($status, $ref, null, ['order_reference' => $order->number], $ref.':'.$provider);
    }

    public function refund(string $gatewayReference, ?string $amount = null): PaymentResult
    {
        $code = $this->credential('merchant_code');
        $amt = $this->fmt($amount ?? 0);
        $reason = 'Refund';
        try {
            $res = $this->send(fn ($h) => $h->post($this->baseUrl().'/ECommerceWeb/Fawry/payments/refund', [
                'merchantCode' => $code, 'referenceNumber' => $gatewayReference, 'refundAmount' => (float) $amt, 'reason' => $reason,
                'signature' => hash('sha256', $code.$gatewayReference.$amt.$reason.$this->credential('secure_key')),
            ]));
        } catch (GatewayException $e) {
            return new PaymentResult('failed', $gatewayReference, null, ['error' => $e->getMessage()]);
        }

        return new PaymentResult((int) $res->json('statusCode') === 200 ? 'refunded' : 'failed', $gatewayReference, null, ['amount' => $amount, 'description' => $res->json('statusDescription')]);
    }
}
