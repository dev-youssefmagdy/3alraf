<?php

namespace App\Services\Orders;

use App\DTOs\Orders\CheckoutData;

/** Cart rows + customer-safe totals (OrderCalculator via OrderQuoteService); shared by the web JSON cart and the mobile API. */
class CartSummary
{
    public function __construct(private CartService $cart, private OrderQuoteService $quotes) {}

    /** @return array{items:array,totals?:array,flash?:bool} */
    public function build(?CheckoutData $d = null): array
    {
        $out = ['items' => $this->cart->view()];
        if ($lines = $this->cart->lines()) {
            $q = $this->quotes->quote($lines, $d ?? new CheckoutData);
            $out['totals'] = $q['totals']->forCustomer() + ['codFeeLabel' => $q['cod_fee_label'][app()->getLocale()] ?? reset($q['cod_fee_label'])];
            $out['flash'] = collect($q['lines'])->contains(fn ($l) => $l['flash']);
        }

        return $out;
    }
}
