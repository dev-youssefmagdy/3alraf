<?php

namespace App\Http\Requests\Tenant;

use App\Enums\DiscountScope;
use App\Enums\DiscountType;
use Illuminate\Validation\Rule;

class DiscountCodeRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9_\-]+$/',
                Rule::unique('discount_codes', 'code')->where('tenant_id', current_tenant()->id)->ignore($this->id())],
            'type' => ['required', Rule::in(DiscountType::values())],
            'value' => ['required_unless:free_shipping,1', 'nullable', 'numeric', 'min:0', Rule::when($this->input('type') === 'percent', ['max:100'])],
            'min_order' => ['nullable', 'numeric', 'min:0'],
            'max_discount' => ['nullable', 'numeric', 'min:0'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'per_customer_limit' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'scope' => ['required', Rule::in(DiscountScope::values())],
            'product_ids' => ['required_if:scope,products', 'nullable', 'array'],
            'product_ids.*' => ['integer', Rule::exists('tenant_products', 'product_id')->where('tenant_id', current_tenant()->id)],
            'category_ids' => ['required_if:scope,categories', 'nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
            'free_shipping' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
