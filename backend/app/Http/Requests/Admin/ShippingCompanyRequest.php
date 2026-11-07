<?php

namespace App\Http\Requests\Tenant;

class ShippingCompanyRequest extends BaseRequest
{
    public function rules(): array
    {
        return $this->translatable('name') + [
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'rates' => ['nullable', 'array'],
            'rates.*.city_id' => ['required', 'distinct', $this->tenantExists('cities', true)],
            'rates.*.price' => ['required', 'numeric', 'min:0', 'max:999999999999'],
            'rates.*.cost' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'rates.*.free_over' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'rates.*.eta_min_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'rates.*.eta_max_days' => ['nullable', 'integer', 'min:0', 'max:365', 'gte:rates.*.eta_min_days'],
            'rates.*.supports_cod' => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return parent::attributes() + [
            'rates.*.city_id' => __('ops.fields.city'), 'rates.*.price' => __('ops.fields.price'), 'rates.*.cost' => __('ops.fields.carrier_cost'),
            'rates.*.free_over' => __('ops.fields.free_over'), 'rates.*.eta_min_days' => __('ops.fields.eta_min'), 'rates.*.eta_max_days' => __('ops.fields.eta_max'),
        ];
    }
}
