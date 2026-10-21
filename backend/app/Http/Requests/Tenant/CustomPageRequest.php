<?php

namespace App\Http\Requests\Tenant;

use App\Support\ReservedSlugs;
use Illuminate\Validation\Rule;

class CustomPageRequest extends BaseRequest
{
    public function rules(): array
    {
        return $this->translatable('title') + [
            'slug' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('custom_pages', 'slug')->where('tenant_id', current_tenant()->id)->ignore($this->id()),
                function ($a, $v, $fail) {
                    if (ReservedSlugs::isReserved((string) $v)) {
                        $fail(__('growth.slug_reserved'));
                    }
                }],
            'is_active' => ['nullable', 'boolean'],
            'filters' => ['nullable', 'array'],
            'filters.categories' => ['nullable', 'array'],
            'filters.categories.*' => ['integer', 'exists:categories,id'],
            'filters.brands' => ['nullable', 'array'],
            'filters.brands.*' => ['integer', 'exists:brands,id'],
            'filters.attribute_values' => ['nullable', 'array'],
            'filters.attribute_values.*' => ['integer', 'exists:attribute_values,id'],
            'filters.price_min' => ['nullable', 'numeric', 'min:0'],
            'filters.price_max' => ['nullable', 'numeric', 'min:0', Rule::when($this->filled('filters.price_min'), ['gte:filters.price_min'])],
            'filters.flash_sale' => ['nullable', 'boolean'],
            'filters.sort' => ['nullable', Rule::in(['newest', 'price_asc', 'price_desc', 'best_selling'])],
            'product_ids' => ['nullable', 'array'],
            'product_ids.*' => ['integer', 'distinct', Rule::exists('tenant_products', 'product_id')->where('tenant_id', current_tenant()->id)],
        ];
    }

    public function attributes(): array
    {
        return parent::attributes() + trans('growth.fields');
    }
}
