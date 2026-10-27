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
        public ?\Illuminate\Http\UploadedFile $proof = null, // transfer proof: mandatory when the chosen method (or COD prepay method) is offline
        public ?int $packagingOptionId = null, // special packaging option (one per order)
        public ?string $phoneCode = null, // dial code snapshot for the phone (server-decided)
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
            $r->file('proof'),
            $r->exists('packaging_option_id') ? ($r->filled('packaging_option_id') ? (int) $r->input('packaging_option_id') : null) : app(\App\Services\Orders\CartService::class)->packagingOptionId(),
            $addr ? $addr->phone_country_code : ($r->filled('phone') ? $r->input('phone_country_code') : $customer?->phone_country_code),
        );
    }
}
