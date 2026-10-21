<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Storefront\ProofRequest;
use App\Http\Requests\Storefront\ReturnRequest;
use App\Http\Resources\Api\OrderDetailResource;
use App\Http\Resources\Api\OrderResource;
use App\Http\Resources\Api\ReturnResource;
use App\Services\Api\ApiOrderService;
use App\Services\Orders\OrderPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Orders. List/return need the customer token; show/track/proof/pay/return-confirmation are addressed by the order's unguessable public token (guest-safe). */
class OrderController extends ApiController
{
    public function __construct(private ApiOrderService $orders, private OrderPaymentService $payments) {}

    public function index(Request $r): JsonResponse
    {
        return $this->page($this->orders->paginateFor($r->user(), (int) $r->query('per_page', 10)), OrderResource::class);
    }

    public function show(string $token): JsonResponse
    {
        return $this->respond(new OrderDetailResource($this->orders->byToken($token)));
    }

    public function track(string $token): JsonResponse
    {
        $o = $this->orders->byToken($token);

        return $this->respond(['number' => $o->number, 'status' => $o->status->value, 'payment_status' => $o->payment_status->value,
            'timeline' => (new OrderDetailResource($o))->resolve(request())['timeline']]);
    }

    /** Wallet transfer proof (stays on the private disk; the admin approves it, which is what confirms the payment). */
    public function proof(ProofRequest $r, string $token): JsonResponse
    {
        $this->payments->uploadProof($order = $this->orders->byToken($token), $r->file('proof'));

        return $this->respond(new OrderDetailResource($this->orders->byToken($order->public_token)), __('orders.proof_uploaded'));
    }

    /** New gateway link for an unpaid online order. */
    public function pay(string $token): JsonResponse
    {
        return $this->respond(['payment_url' => $this->orders->payLink($this->orders->byToken($token))]);
    }

    /** Call after the in-app browser comes back from the gateway. Non-authoritative: only the signed webhook marks an order paid, so poll this until `paid` is true. */
    public function paymentReturn(string $token): JsonResponse
    {
        $o = $this->orders->byToken($token);

        return $this->respond(new OrderDetailResource($o), null, ['paid' => $o->payment_status->value === 'paid', 'authoritative' => false]);
    }

    public function requestReturn(ReturnRequest $r, string $token): JsonResponse
    {
        $ret = $this->orders->requestReturn($this->orders->byToken($token), $r->user(), $r->validated());

        return $this->respond(new ReturnResource($ret), __('orders.return_requested'), status: 201);
    }
}
