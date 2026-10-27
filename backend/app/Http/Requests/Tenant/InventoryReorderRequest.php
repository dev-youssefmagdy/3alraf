<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class InventoryReorderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['product_id' => ['required', 'integer'], 'supplier_id' => ['nullable', 'integer'], 'branch_id' => ['nullable', 'integer']];
    }
}
