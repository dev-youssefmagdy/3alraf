<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Services\Orders\OrderPaymentService;
use App\Services\Payments\PaymentGatewayFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/** POST /webhooks/payments/{method} on the tenant host. Verifies signature, then settles the order (paid -> confirmed / failed). */
class PaymentWebhookController extends Controller
{
    public function __invoke(Request $request, PaymentGatewayFactory $factory, OrderPaymentService $payments, int $method): JsonResponse
    {
        $pm = PaymentMethod::where('is_active', true)->findOrFail($method); // tenant-scoped

        try {
            $gateway = $factory->make($pm);
        } catch (\InvalidArgumentException) {
            abort(404);
        }

        if (! $gateway->verifyWebhook($request)) {
            Log::warning('Payment webhook signature rejected', ['method' => $pm->id, 'provider' => $pm->provider->value]);

            return response()->json(['success' => false, 'message' => 'invalid signature'], 400);
        }

        $result = $gateway->parseWebhook($request);
        Log::info('Payment webhook accepted', ['method' => $pm->id, 'status' => $result->status, 'ref' => $result->gatewayReference]);

        $order = Order::where('number', $result->raw['order_reference'] ?? '')->where('payment_method_id', $pm->id)->first();
        if (! $order) {
            return response()->json(['success' => true, 'ignored' => true]); // unknown reference: ack so the gateway stops retrying
        }
        $payments->settleGateway($order, $result);

        return response()->json(['success' => true]);
    }
}
