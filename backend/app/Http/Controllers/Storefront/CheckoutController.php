<?php

namespace App\Http\Controllers\Storefront;

use App\DTOs\Orders\CheckoutData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\CheckoutRequest;
use App\Services\CustomerAddressService;
use App\Services\Orders\CartService;
use App\Services\Orders\OrderPaymentService;
use App\Services\Orders\OrderPlacementService;
use App\Services\Orders\OrderQuoteService;
use App\Services\Orders\ShippingOptions;
use App\Models\City;
use App\Models\PaymentMethod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CheckoutController extends Controller
{
    public function __construct(private CartService $cart, private OrderPlacementService $placement, private OrderQuoteService $quotes, private ShippingOptions $shipping, private OrderPaymentService $payments, private CustomerAddressService $addresses) {}

    public function create(string $locale)
    {
        if (! $this->cart->lines()) {
            return redirect()->route('storefront.cart.show');
        }
        $customer = auth('web')->user();

        return view('storefront.checkout', [
            'cities' => City::where('is_active', true)->orderBy('sort_order')->get(),
            'methods' => PaymentMethod::available()->orderBy('sort_order')->get(),
            'codUpfront' => current_tenant()->codCollectsShippingUpfront(),
            'addresses' => $this->addresses->forCustomer($customer->id),
            'selectedAddress' => null,
            'customer' => $customer,
        ]);
    }

    /** Re-rendered address list (after the add/edit modal saved); `select` picks the address to tick. */
    public function addresses(Request $r)
    {
        return view('storefront._checkout-addresses', [
            'addresses' => $this->addresses->forCustomer(auth('web')->id()), 'selectedAddress' => $r->integer('select') ?: null,
        ]);
    }

    /** Shipping options for a city, each with the order total it would produce (OrderCalculator via the quote service). */
    public function shipping(Request $r): JsonResponse
    {
        return $this->ok(null, null, ['options' => $this->shipping->forCity((int) $r->query('city_id'))]);
    }

    /** Pre-validate: request rules + a full quote (stock/discount/shipping errors surface here too). */
    public function validateForm(CheckoutRequest $r): JsonResponse
    {
        $d = CheckoutData::fromRequest($r);
        $q = $this->quotes->quote($this->cart->lines(), $d);
        $this->quotes->assertPrepay($q);
        $this->payments->precheckCheckoutProof($q, $d->proof);

        return $this->ok();
    }

    public function store(CheckoutRequest $r): JsonResponse
    {
        $res = $this->placement->place(CheckoutData::fromRequest($r), $this->cart->lines());
        $this->cart->clear();
        $order = $res['order'];

        return $this->ok(__('orders.order_placed'), $res['payment']?->redirectUrl ?: route('storefront.orders.show', ['locale' => app()->getLocale(), 'token' => $order->public_token]), [
            'number' => $order->number, 'token' => $order->public_token, 'total' => (string) $order->total, 'pay_url' => $res['payment']?->redirectUrl,
        ]);
    }
}
