<?php

namespace App\Http\Controllers\Storefront;

use App\DTOs\Orders\CheckoutData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\CartItemRequest;
use App\Http\Requests\Storefront\CheckoutRequest;
use App\Services\Orders\CartService;
use App\Services\Orders\OrderQuoteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** JSON cart API used by the storefront JS. */
class CartController extends Controller
{
    public function __construct(private CartService $cart, private OrderQuoteService $quotes) {}

    public function show(Request $request)
    {
        if (! $request->expectsJson()) {
            return view('storefront.cart');
        }

        return $this->ok(null, null, $this->payload());
    }

    public function add(CartItemRequest $r): JsonResponse
    {
        $this->cart->add((int) $r->input('product_id'), (int) $r->input('variant_id', 0), max(1, (int) $r->input('quantity')));

        return $this->ok(__('orders.added_to_cart'), null, $this->payload());
    }

    public function update(CartItemRequest $r, int $item): JsonResponse
    {
        $this->cart->setQuantity($item, (int) $r->input('quantity'));

        return $this->ok(null, null, $this->payload());
    }

    public function remove(int $item): JsonResponse
    {
        $this->cart->setQuantity($item, 0);

        return $this->ok(null, null, $this->payload());
    }

    /** Totals for the current selections (shipping / payment / code optional). */
    public function quote(CheckoutRequest $r): JsonResponse
    {
        return $this->ok(null, null, $this->payload(CheckoutData::fromRequest($r)));
    }

    private function payload(?CheckoutData $d = null): array
    {
        $out = ['items' => $this->cart->view()];
        $lines = $this->cart->lines();
        if ($lines) {
            $q = $this->quotes->quote($lines, $d ?? new CheckoutData);
            $out['totals'] = $q['totals']->toArray();
            $out['flash'] = collect($q['lines'])->contains(fn ($l) => $l['flash']);
        }

        return $out;
    }
}
