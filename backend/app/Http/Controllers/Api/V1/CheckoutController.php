<?php

namespace App\Http\Controllers\Api\V1;

use App\DTOs\Orders\CheckoutData;
use App\Http\Requests\Storefront\CheckoutRequest;
use App\Http\Resources\Api\CartResource;
use App\Http\Resources\Api\CityResource;
use App\Http\Resources\Api\OrderDetailResource;
use App\Http\Resources\Api\PaymentMethodResource;
use App\Http\Resources\Api\ShippingOptionResource;
use App\Models\City;
use App\Models\PaymentMethod;
use App\Services\Api\ApiOrderService;
use App\Services\Orders\CartService;
use App\Services\Orders\CartSummary;
use App\Services\Orders\OrderPlacementService;
use App\Services\Orders\OrderQuoteService;
use App\Services\Orders\ShippingOptions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Checkout: lookups, strict validation, order placement. Payment rules (admin confirmation, wallet proof, COD prepaid shipping, online redirect) live in the services. */
class CheckoutController extends ApiController
{
    public function __construct(private CartService $cart, private OrderPlacementService $placement, private OrderQuoteService $quotes, private CartSummary $summary,
        private ShippingOptions $shipping, private ApiOrderService $orders) {}

    public function cities(): JsonResponse
    {
        return $this->list(CityResource::collection(City::where('is_active', true)->orderBy('sort_order')->get()));
    }

    public function shipping(Request $r): JsonResponse
    {
        return $this->list(ShippingOptionResource::collection($this->shipping->forCity((int) $r->query('city_id'))));
    }

    public function paymentMethods(): JsonResponse
    {
        return $this->list(PaymentMethodResource::collection(PaymentMethod::available()->orderBy('sort_order')->get()),
            ['cod_collect_shipping_upfront' => current_tenant()->codCollectsShippingUpfront()]);
    }

    /** Full quote with the strict checkout rules (stock, discount, shipping, COD prepay); 422 with field errors when something is wrong. */
    public function validateCheckout(CheckoutRequest $r): JsonResponse
    {
        $d = CheckoutData::fromRequest($r);
        $this->quotes->assertPrepay($this->quotes->quote($this->cart->lines(), $d));

        return $this->respond(new CartResource($this->summary->build($d)));
    }

    public function place(CheckoutRequest $r): JsonResponse
    {
        $res = $this->placement->place(CheckoutData::fromRequest($r), $this->cart->lines());
        $this->cart->clear();
        $order = $this->orders->byToken($res['order']->public_token);
        $payUrl = $res['payment']?->redirectUrl ?: null;

        return $this->respond(new OrderDetailResource($order), __('orders.order_placed'), [
            'next_action' => $this->orders->nextAction($order, $payUrl), 'payment_url' => $payUrl,
        ], 201);
    }
}
