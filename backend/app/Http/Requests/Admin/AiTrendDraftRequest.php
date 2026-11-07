<?php

namespace App\Http\Requests\Admin;

class AiTrendDraftRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'integer', $this->existsRule('suppliers', true)],
            'branch_id' => ['required', 'integer', $this->existsRule('branches', true)],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100000'],
        ];
    }
}
