<?php

namespace App\Http\Requests\Tenant;

use App\Http\Requests\Concerns\ValidatesTenantPhone;
use App\Services\Orders\OrderPaymentService;
use App\Enums\PaymentMethodType;
use App\Models\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** POS order (and its live quote: `tenant.pos.quote` accepts incomplete selections). Online gateways are not offered: no customer to redirect. */
class PosOrderRequest extends FormRequest
{
    use ValidatesTenantPhone;

    private function isQuote(): bool
    {
        return $this->routeIs('tenant.pos.quote', 'tenant.pos.shipping');
    }

    protected function prepareForValidation(): void
    {
        $this->normalizePhone();
        $items = $this->input('items');
        if (is_string($items)) { // multipart (proof upload) sends the cart as JSON
            $this->merge(['items' => json_decode($items, true) ?: []]);
        }
    }

    public function after(): array
    {
        return [fn ($v) => $this->isQuote() ? null : $this->withPhoneValidator($v)];
    }

    public function authorize(): bool
    {
        return $this->user('tenant')?->can('pos.use') ?? false;
    }

    public function isOffline(): bool
    {
        $find = fn ($key) => $this->filled($key) ? PaymentMethod::query()->find((int) $this->input($key)) : null;

        return app(OrderPaymentService::class)->offlineAtCheckout($find('payment_method_id'), $find('prepay_method_id'), 1);
    }

    public function messages(): array
    {
        return ['items.required' => __('orders.cart_empty'), 'items.min' => __('orders.cart_empty'), 'customer_id.required' => __('pos.customer_required'), 'proof.required' => __('orders.proof_required')];
    }

    public function rules(): array
    {
        $tid = current_tenant()->id;
        $q = $this->isQuote();
        $methods = fn (array $types) => Rule::exists('payment_methods', 'id')->where('tenant_id', $tid)->whereIn('type', $types)->where('is_active', true)->whereNull('deleted_at');

        return [
            'customer_id' => [$q ? 'nullable' : 'required', Rule::exists('customers', 'id')->where('tenant_id', $tid)->whereNull('deleted_at')],
            'name' => [$q ? 'nullable' : 'required', 'string', 'max:120'],
            'phone' => [$q ? 'nullable' : 'required', 'string', 'max:30'],
            ...$this->phoneCodeRule(),
            'email' => ['nullable', 'email', 'max:190'],
            'city_id' => [$q ? 'nullable' : 'required', Rule::exists('cities', 'id')->where('tenant_id', $tid)->where('is_active', true)->whereNull('deleted_at')],
            'address' => [$q ? 'nullable' : 'required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.product_id' => ['required', 'integer', 'min:1'],
            'items.*.variant_id' => ['nullable', 'integer', 'min:0'],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:9999'],
            'shipping_company_id' => [$q ? 'nullable' : 'required', Rule::exists('shipping_companies', 'id')->where('tenant_id', $tid)->whereNull('deleted_at')],
            'payment_method_id' => [$q ? 'nullable' : 'required', $methods([PaymentMethodType::COD->value, PaymentMethodType::WALLET->value])],
            'prepay_method_id' => ['nullable', $methods([PaymentMethodType::WALLET->value])],
            'discount_code' => ['nullable', 'string', 'max:50'],
            'packaging_option_id' => ['nullable', 'integer', Rule::exists('packaging_options', 'id')->where('tenant_id', $tid)->where('is_active', true)->whereNull('deleted_at')],
            'proof' => [! $q && $this->isOffline() ? 'required' : 'nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:'.(int) config('checkout.proof.max_kb')],
        ];
    }

    public function attributes(): array
    {
        return trans('admin.fields') + trans('orders.fields');
    }
}
