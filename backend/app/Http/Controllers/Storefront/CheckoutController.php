<?php

namespace App\Http\Controllers\Storefront;

use App\DTOs\Orders\CheckoutData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\CheckoutRequest;
use App\Services\Orders\CartService;
use App\Services\Orders\OrderPlacementService;
use App\Services\Orders\OrderQuoteService;
use App\Models\City;
use App\Models\PaymentMethod;
use App\Models\ShippingRate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CheckoutController extends Controller
{
    public function __construct(private CartService $cart, private OrderPlacementService $placement, private OrderQuoteService $quotes) {}

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
            'addresses' => $customer ? $customer->addresses()->with('city')->get() : collect(),
            'customer' => $customer,
        ]);
    }

    /** Shipping options for a city, each with the order total it would produce (OrderCalculator via the quote service). */
    public function shipping(Request $r): JsonResponse
    {
        $cityId = (int) $r->query('city_id');
        $lines = $this->cart->lines();
        $out = [];
        if ($cityId && $lines) {
            $rates = ShippingRate::with('company')->where('city_id', $cityId)->whereHas('company', fn ($q) => $q->where('is_active', true))->get()->sortBy('company.sort_order');
            foreach ($rates as $rate) {
                $q = $this->quotes->quote($lines, new CheckoutData(cityId: $cityId, shippingCompanyId: $rate->shipping_company_id));
                $out[] = ['id' => $rate->shipping_company_id, 'name' => $rate->company->name, 'eta' => $rate->etaLabel(), 'supports_cod' => $rate->supports_cod,
                    'fee' => $q['totals']->shippingNet, 'free' => bccomp($q['totals']->shippingNet, '0', 2) === 0, 'total' => $q['totals']->total];
            }
        }

        return $this->ok(null, null, ['options' => $out]);
    }

    /** Pre-validate: request rules + a full quote (stock/discount/shipping errors surface here too). */
    public function validateForm(CheckoutRequest $r): JsonResponse
    {
        $this->quotes->assertPrepay($this->quotes->quote($this->cart->lines(), CheckoutData::fromRequest($r)));

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
