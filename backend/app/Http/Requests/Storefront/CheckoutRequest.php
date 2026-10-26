<?php

namespace App\Http\Requests\Storefront;

use App\Models\PaymentMethod;
use App\Services\Orders\OfflineCheckoutThrottle;
use App\Services\Orders\OrderPaymentService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    private function isQuote(): bool
    {
        return $this->routeIs('storefront.cart.quote', 'api.v1.cart.quote', 'api.v1.h.cart.quote');
    }

    /** True when the chosen payment method (or the wallet prepay leg of a COD order) is offline = needs a transfer proof file. */
    private function isOffline(): bool
    {
        $find = fn ($key) => $this->filled($key) ? PaymentMethod::query()->find((int) $this->input($key)) : null;

        return app(OrderPaymentService::class)->offlineAtCheckout($find('payment_method_id'), $find('prepay_method_id'), 1);
    }

    /** Order-placing routes only (not the live quote / pre-validate): count the attempt against the offline limiter before anything else. */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('phone'))) { // Arabic-Indic digits -> ASCII: one canonical phone for the limiter, the open-order cap and the stored order
            $this->merge(['phone' => OfflineCheckoutThrottle::asciiDigits($this->input('phone'))]);
        }
        if (! $this->routeIs('storefront.checkout.store', 'api.v1.checkout.place', 'api.v1.h.checkout.place') || ! $this->isOffline()) {
            return;
        }
        $customer = auth('web')->user();
        $addr = ($customer && $this->filled('address_id')) ? $customer->addresses()->find($this->input('address_id')) : null;
        app(OfflineCheckoutThrottle::class)->attempt($this, $addr?->phone ?? ($this->input('phone') ?: $customer?->phone), $this->input('email') ?: $customer?->email, $customer?->id);
    }

    public function messages(): array
    {
        return ['proof.required' => __('orders.proof_required'), 'proof.mimes' => __('orders.proof_invalid'), 'proof.file' => __('orders.proof_invalid'),
            'proof.max' => __('orders.proof_too_large', ['kb' => config('checkout.proof.max_kb')]), 'proof.uploaded' => __('orders.proof_invalid'),
            'address_id.required' => __('store.address_required'), 'address_id.exists' => __('store.address_required')];
    }

    public function rules(): array
    {
        $tid = current_tenant()->id;
        $loggedIn = auth('web')->check() && $this->filled('address_id');
        $quote = $this->isQuote(); // live totals: selections may be incomplete

        return [
            'address_id' => [auth('web')->check() && ! $quote ? 'required' : 'nullable', 'integer', Rule::exists('customer_addresses', 'id')->where('customer_id', auth('web')->id() ?? 0)],
            'name' => [$loggedIn || $quote ? 'nullable' : 'required', 'string', 'max:120'],
            'phone' => [$loggedIn || $quote ? 'nullable' : 'required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:190'],
            'city_id' => [$loggedIn || $quote ? 'nullable' : 'required', Rule::exists('cities', 'id')->where('tenant_id', $tid)->where('is_active', true)->whereNull('deleted_at')],
            'address' => [$loggedIn || $quote ? 'nullable' : 'required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:255'],
            'shipping_company_id' => [$quote ? 'nullable' : 'required', Rule::exists('shipping_companies', 'id')->where('tenant_id', $tid)->whereNull('deleted_at')],
            'payment_method_id' => [$quote ? 'nullable' : 'required', Rule::exists('payment_methods', 'id')->where('tenant_id', $tid)->whereNull('deleted_at')],
            'prepay_method_id' => ['nullable', Rule::exists('payment_methods', 'id')->where('tenant_id', $tid)->whereNull('deleted_at')],
            'discount_code' => ['nullable', 'string', 'max:50'],
            'packaging_option_id' => ['nullable', 'integer', Rule::exists('packaging_options', 'id')->where('tenant_id', $tid)->where('is_active', true)->whereNull('deleted_at')],
            'proof' => [! $quote && $this->isOffline() ? 'required' : 'nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:'.(int) config('checkout.proof.max_kb')],
        ];
    }
}
