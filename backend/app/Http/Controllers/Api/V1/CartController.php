<?php

namespace App\Http\Controllers\Api\V1;

use App\DTOs\Orders\CheckoutData;
use App\Http\Requests\Storefront\CartItemRequest;
use App\Http\Requests\Storefront\CheckoutRequest;
use App\Http\Resources\Api\CartResource;
use App\Services\Orders\CartService;
use App\Services\Orders\CartSummary;
use Illuminate\Http\JsonResponse;

/** Cart for guests (X-Cart-Token header, returned on every response) and customers (bearer token). */
class CartController extends ApiController
{
    public function __construct(private CartService $cart, private CartSummary $summary) {}

    public function show(): JsonResponse
    {
        return $this->cartResponse();
    }

    public function add(CartItemRequest $r): JsonResponse
    {
        $this->cart->add((int) $r->input('product_id'), (int) $r->input('variant_id', 0), max(1, (int) $r->input('quantity')));

        return $this->cartResponse(__('orders.added_to_cart'));
    }

    public function update(CartItemRequest $r, int $item): JsonResponse
    {
        $this->cart->setQuantity($item, (int) $r->input('quantity'));

        return $this->cartResponse();
    }

    public function remove(int $item): JsonResponse
    {
        $this->cart->setQuantity($item, 0);

        return $this->cartResponse();
    }

    /** Totals for the current selections (city / shipping / payment / code optional). */
    public function quote(CheckoutRequest $r): JsonResponse
    {
        return $this->cartResponse(null, CheckoutData::fromRequest($r));
    }

    /** After login: merges the guest cart (X-Cart-Token) into the customer's cart. */
    public function merge(): JsonResponse
    {
        $this->cart->cart(false);

        return $this->cartResponse();
    }

    private function cartResponse(?string $message = null, ?CheckoutData $d = null): JsonResponse
    {
        return $this->respond(new CartResource($this->summary->build($d)), $message);
    }
}
