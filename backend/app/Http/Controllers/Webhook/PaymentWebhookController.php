<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use App\Services\Orders\OrderPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** POST /webhooks/payments/{method} on the tenant host. All verification/idempotency lives in OrderPaymentService::handleWebhook. */
class PaymentWebhookController extends Controller
{
    public function __invoke(Request $request, OrderPaymentService $payments, int $method): JsonResponse
    {
        $pm = PaymentMethod::where('is_active', true)->findOrFail($method); // tenant-scoped
        [$status, $body] = $payments->handleWebhook($pm, $request);

        return response()->json($body, $status);
    }
}
