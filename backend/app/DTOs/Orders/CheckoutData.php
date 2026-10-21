<?php

namespace App\DTOs\Orders;

/** Checkout / quote selections. Everything except the customer details may be null for a cart quote. */
final readonly class CheckoutData
{
    public function __construct(
        public ?int $customerId = null,
        public string $name = '',
        public string $phone = '',
        public ?string $email = null,
        public ?int $cityId = null,
        public string $address = '',
        public ?string $notes = null,
        public ?int $shippingCompanyId = null,
        public ?int $paymentMethodId = null,
        public ?string $discountCode = null,
        public ?int $affiliateId = null,
        public ?int $prepayMethodId = null, // online/wallet method paying the shipping upfront for COD
    ) {}

    public static function fromRequest(\App\Http\Requests\Storefront\CheckoutRequest $r): self
    {
        $customer = auth('web')->user();
        $addr = ($customer && $r->filled('address_id')) ? $customer->addresses()->find($r->input('address_id')) : null;

        return new self(
            $customer?->id,
            $addr?->name ?? ($r->input('name') ?: $customer?->name ?? ''),
            $addr?->phone ?? ($r->input('phone') ?: $customer?->phone ?? ''),
            $r->input('email') ?: $customer?->email,
            $addr?->city_id ?? ($r->filled('city_id') ? (int) $r->input('city_id') : null),
            $addr?->address ?? (string) $r->input('address', ''),
            $r->input('notes'),
            $r->filled('shipping_company_id') ? (int) $r->input('shipping_company_id') : null,
            $r->filled('payment_method_id') ? (int) $r->input('payment_method_id') : null,
            $r->filled('discount_code') ? trim($r->input('discount_code')) : null,
            app(\App\Services\Affiliates\AffiliateTracker::class)->attributedId($r, $customer?->email, $r->input('email')),
            $r->filled('prepay_method_id') ? (int) $r->input('prepay_method_id') : null,
        );
    }
}
