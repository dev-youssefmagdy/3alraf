<?php

namespace App\Http\Requests\Storefront;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tid = current_tenant()->id;
        $loggedIn = auth('web')->check() && $this->filled('address_id');
        $quote = $this->routeIs('storefront.cart.quote'); // live totals: selections may be incomplete

        return [
            'address_id' => ['nullable', 'integer', Rule::exists('customer_addresses', 'id')->where('customer_id', auth('web')->id() ?? 0)],
            'name' => [$loggedIn || $quote ? 'nullable' : 'required', 'string', 'max:120'],
            'phone' => [$loggedIn || $quote ? 'nullable' : 'required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:190'],
            'city_id' => [$loggedIn || $quote ? 'nullable' : 'required', Rule::exists('cities', 'id')->where('tenant_id', $tid)->whereNull('deleted_at')],
            'address' => [$loggedIn || $quote ? 'nullable' : 'required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:255'],
            'shipping_company_id' => [$quote ? 'nullable' : 'required', Rule::exists('shipping_companies', 'id')->where('tenant_id', $tid)->whereNull('deleted_at')],
            'payment_method_id' => [$quote ? 'nullable' : 'required', Rule::exists('payment_methods', 'id')->where('tenant_id', $tid)->whereNull('deleted_at')],
            'discount_code' => ['nullable', 'string', 'max:50'],
        ];
    }
}
