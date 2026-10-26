<?php

namespace App\Http\Requests\Tenant;

class PackagingOptionRequest extends BaseRequest
{
    public function rules(): array
    {
        return $this->translatable('name') + $this->translatable('description', false, 2000) + [
            'price' => ['required', 'numeric', 'min:0', 'max:999999999999'],
            'cost' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            // an uploaded picture is mandatory: on create, and on edit unless the option already has one that is kept
            'image' => [$this->hasKeptImage() ? 'nullable' : 'required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'remove_image' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    private function hasKeptImage(): bool
    {
        $id = $this->id();

        return $id && ! $this->boolean('remove_image') && \App\Models\PackagingOption::whereKey($id)->whereNotNull('image')->exists();
    }

    public function attributes(): array
    {
        return parent::attributes() + ['price' => __('orders.packaging.price'), 'cost' => __('orders.packaging.cost')];
    }
}
