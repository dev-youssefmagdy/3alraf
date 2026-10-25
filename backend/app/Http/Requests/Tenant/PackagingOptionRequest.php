<?php

namespace App\Http\Requests\Tenant;

class PackagingOptionRequest extends BaseRequest
{
    public function rules(): array
    {
        return $this->translatable('name') + $this->translatable('description', false, 2000) + [
            'price' => ['required', 'numeric', 'min:0', 'max:999999999999'],
            'cost' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'image' => ['nullable', 'image', 'max:4096'],
            'remove_image' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return parent::attributes() + ['price' => __('orders.packaging.price'), 'cost' => __('orders.packaging.cost')];
    }
}
