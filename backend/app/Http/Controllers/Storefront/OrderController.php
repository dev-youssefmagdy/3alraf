<?php

namespace App\Http\Controllers\Storefront;

use App\DTOs\Orders\ReturnRequestData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\ProofRequest;
use App\Http\Requests\Storefront\ReturnRequest;
use App\Models\Order;
use App\Services\Orders\OrderPaymentService;
use App\Services\Orders\ReturnService;
use Illuminate\Http\JsonResponse;

/** Customer / guest order pages addressed by the unguessable public token. */
class OrderController extends Controller
{
    public function __construct(private OrderPaymentService $payments, private ReturnService $returns) {}

    public function show(string $locale, string $token)
    {
        $view = request()->boolean('invoice') ? 'tenant.orders.invoice' : 'storefront.order';

        return view($view, ['order' => $this->order($token), 'public' => true]);
    }

    public function uploadProof(ProofRequest $r, string $locale, string $token): JsonResponse
    {
        $this->payments->uploadProof($this->order($token), $r->file('proof'));

        return $this->ok(__('orders.proof_uploaded'), route('storefront.orders.show', compact('locale', 'token')));
    }

    /** Proof file for the order's owner (unguessable public token); streamed from the private disk. */
    public function proofFile(string $locale, string $token)
    {
        return $this->payments->proofResponse($this->order($token));
    }

    /** New payment link for an unpaid online order (first attempt failed / expired / customer came back). */
    public function pay(string $locale, string $token): JsonResponse
    {
        $order = $this->order($token);
        $payment = $this->payments->startOnline($order);
        if (! $payment?->redirectUrl) {
            throw \Illuminate\Validation\ValidationException::withMessages(['payment' => __('orders.payment_start_failed')]);
        }

        return $this->ok(null, $payment->redirectUrl, ['pay_url' => $payment->redirectUrl]);
    }

    public function requestReturn(ReturnRequest $r, string $locale, string $token): JsonResponse
    {
        $order = $this->order($token);
        abort_unless($order->customer_id && $order->customer_id === auth('web')->id(), 403);
        $this->returns->request($order, ReturnRequestData::fromArray(['items' => $r->input('items'), 'reason' => $r->input('reason'), 'restock' => true]));

        return $this->ok(__('orders.return_requested'));
    }

    /** Browser return from the gateway (non-authoritative; the webhook settles the payment). */
    public function paymentReturn(string $locale, string $token)
    {
        return redirect()->route('storefront.orders.show', compact('locale', 'token'));
    }

    private function order(string $token): Order
    {
        return Order::with(['items', 'histories', 'city', 'paymentMethod', 'shippingCompany'])->where('public_token', $token)->firstOrFail();
    }
}
