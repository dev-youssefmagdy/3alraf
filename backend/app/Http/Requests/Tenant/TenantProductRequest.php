<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class TenantProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tp = $this->route('tenantProduct');
        $own = fn () => 'exists:tenant_product_variants,id,tenant_product_id,'.($tp?->id ?? 0);

        return [
            'price' => ['required', 'numeric', 'min:0', 'max:999999999999'],
            'sale_price' => ['nullable', 'numeric', 'min:0', 'lt:price'],
            'cost' => ['required', 'numeric', 'min:0', 'max:999999999999'],
            'min_stock_alert' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'is_active' => ['nullable', 'boolean'],
            'variants' => ['nullable', 'array'],
            'variants.*.id' => ['required', 'integer', $own()],
            'variants.*.price' => ['nullable', 'numeric', 'min:0'],
            'variants.*.sale_price' => ['nullable', 'numeric', 'min:0'],
            'variants.*.cost' => ['nullable', 'numeric', 'min:0'],
            'variants.*.is_active' => ['nullable', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [function ($v) {
            foreach ($this->input('variants', []) as $k => $row) {
                $price = ($row['price'] ?? '') !== '' ? $row['price'] : $this->input('price');
                if (($row['sale_price'] ?? '') !== '' && is_numeric($price) && is_numeric($row['sale_price']) && bccomp((string) $row['sale_price'], (string) $price, 2) >= 0) {
                    $v->errors()->add("variants.$k.sale_price", __('validation.lt.numeric', ['attribute' => __('admin.fields.sale_price'), 'value' => __('admin.fields.price')]));
                }
            }
        }];
    }

    public function attributes(): array
    {
        return trans('admin.fields');
    }
}
