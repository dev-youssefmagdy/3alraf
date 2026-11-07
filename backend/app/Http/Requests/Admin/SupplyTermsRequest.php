<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class SupplyTermsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['product_id' => ['required', 'integer'], 'supplier_id' => ['nullable', 'integer'], 'lead_time_days' => ['required', 'integer', 'min:0', 'max:365'], 'moq' => ['required', 'integer', 'min:1', 'max:1000000'], 'shelf_life_days' => ['nullable', 'integer', 'min:1', 'max:3650']];
    }
}
