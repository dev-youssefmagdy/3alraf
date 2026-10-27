<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class SupplierPriceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['product_id' => ['required', 'integer'], 'supplier_id' => ['required', 'integer'], 'unit_cost' => ['required', 'numeric', 'gt:0', 'max:99999999'], 'moq' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'lead_time_days' => ['nullable', 'integer', 'min:0', 'max:365'], 'valid_from' => ['nullable', 'date'], 'valid_to' => ['nullable', 'date', 'after_or_equal:valid_from'], 'is_preferred' => ['nullable', 'boolean'], 'note' => ['nullable', 'string', 'max:255']];
    }
}
