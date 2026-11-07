<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Admin\Concerns\HasStockItems;

class PurchaseOrderRequest extends BaseRequest
{
    use HasStockItems;

    public function rules(): array
    {
        return [
            'supplier_id' => ['required', $this->existsRule('suppliers', true)],
            'branch_id' => ['required', $this->existsRule('branches', true)],
            'ordered_at' => ['required', 'date'],
            'shipping_cost' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'other_cost' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'note' => ['nullable', 'string', 'max:2000'],
            'receive' => ['nullable', 'boolean'],
        ] + $this->itemRules(true, true);
    }

    public function attributes(): array
    {
        return parent::attributes() + $this->itemAttributes();
    }
}
