<?php

namespace App\Services\Orders;

use App\DTOs\Orders\CalculatorInput;
use App\DTOs\Orders\CheckoutData;
use App\DTOs\Orders\OrderTotals;
use App\Enums\PaymentMethodType;
use App\Enums\AffiliateStatus;
use App\Models\Affiliate;
use App\Models\PaymentMethod;
use App\Services\Affiliates\AffiliateCommissionService;
use App\Models\ShippingRate;
use Illuminate\Validation\ValidationException;

/**
 * Turns cart lines + checkout selections into priced lines and OrderTotals. Prices come from PriceResolver,
 * discount from DiscountValidator, every number from OrderCalculator (the only math).
 */
class OrderQuoteService
{
    public function __construct(private PriceResolver $prices, private DiscountValidator $discounts, private OrderCalculator $calculator, private AffiliateCommissionService $affiliates) {}

    /**
     * @param  array<int,array{product_id:int,variant_id:int,qty:int}>  $cartLines
     * @return array{affiliate:?Affiliate,affiliate_percent:string,lines:array,totals:OrderTotals,discount:?\App\Models\DiscountCode,rate:?ShippingRate,method:?PaymentMethod}
     */
    public function quote(array $cartLines, CheckoutData $d, bool $lock = false): array
    {
        if (! $cartLines) {
            throw ValidationException::withMessages(['cart' => __('orders.cart_empty')]);
        }

        $lines = [];
        foreach ($cartLines as $c) {
            $item = $this->prices->item($c['product_id'], $c['variant_id']);
            if (! $item) {
                throw ValidationException::withMessages(['cart' => __('orders.item_unavailable')]);
            }
            foreach ($this->prices->segments($item, $c['qty'], $lock) as $seg) {
                $lines[] = ['key' => count($lines), 'product_id' => $item['product']->id, 'variant_id' => $item['variant']?->id ?? 0, 'name' => $item['name'],
                    'sku' => $item['sku'], 'barcode' => $item['barcode'], 'unit_price' => $seg['unit_price'], 'unit_cost' => $item['cost'], 'qty' => $seg['qty'],
                    'flash' => $seg['flash'], 'eligible' => true];
            }
        }

        $discountModel = null;
        $rule = null;
        if ($d->discountCode) {
            $res = $this->discounts->validate($d->discountCode, $lines, $d->customerId, $lock, $d->email, $d->phone);
            $discountModel = $res['model'];
            $rule = $res['rule'];
            foreach ($lines as &$l) {
                $l['eligible'] = $res['eligible'][$l['key']] ?? false;
            }
            unset($l);
        }

        $rate = null;
        if ($d->shippingCompanyId && $d->cityId) {
            $rate = ShippingRate::where('shipping_company_id', $d->shippingCompanyId)->where('city_id', $d->cityId)
                ->whereHas('company', fn ($q) => $q->where('is_active', true))->first();
            if (! $rate) {
                throw ValidationException::withMessages(['shipping_company_id' => __('orders.shipping_unavailable')]);
            }
        }

        $method = null;
        if ($d->paymentMethodId) {
            $method = PaymentMethod::where('is_active', true)->find($d->paymentMethodId);
            if (! $method) {
                throw ValidationException::withMessages(['payment_method_id' => __('orders.payment_unavailable')]);
            }
            if ($method->type === PaymentMethodType::COD && $rate && ! $rate->supports_cod) {
                throw ValidationException::withMessages(['payment_method_id' => __('orders.cod_unavailable')]);
            }
        }

        $affiliate = $d->affiliateId ? Affiliate::where('status', AffiliateStatus::APPROVED->value)->find($d->affiliateId) : null;
        $affiliatePercent = $affiliate ? $this->affiliates->percentFor($affiliate) : '0';

        $settings = current_tenant()?->settings ?? [];
        $totals = $this->calculator->calculate(new CalculatorInput(
            lines: array_map(fn ($l) => ['key' => $l['key'], 'unit_price' => $l['unit_price'], 'unit_cost' => $l['unit_cost'], 'qty' => $l['qty'], 'eligible' => $l['eligible']], $lines),
            discount: $rule,
            shipping: $rate ? ['price' => (string) $rate->price, 'cost' => $rate->cost !== null ? (string) $rate->cost : null, 'free_over' => $rate->free_over !== null ? (string) $rate->free_over : null] : null,
            taxRate: (string) ($settings['tax_rate'] ?? '0'),
            pricesIncludeTax: (bool) ($settings['prices_include_tax'] ?? false),
            feePercent: $method ? (string) $method->fee_percent : '0',
            feeFixed: $method ? (string) $method->fee_fixed : '0',
            feeBearer: $method ? $method->fee_bearer->value : 'customer',
            affiliatePercent: $affiliatePercent,
        ));

        return ['lines' => $lines, 'totals' => $totals, 'discount' => $discountModel, 'rate' => $rate, 'method' => $method, 'affiliate' => $affiliate, 'affiliate_percent' => $affiliatePercent];
    }
}
