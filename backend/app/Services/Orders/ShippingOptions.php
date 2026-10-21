<?php

namespace App\Services\Orders;

use App\DTOs\Orders\CheckoutData;
use App\Models\ShippingRate;

/** Shipping options for a city, each with the order total it would produce (OrderCalculator via the quote service). */
class ShippingOptions
{
    public function __construct(private CartService $cart, private OrderQuoteService $quotes) {}

    public function forCity(int $cityId): array
    {
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

        return $out;
    }
}
