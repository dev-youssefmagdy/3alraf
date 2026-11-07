<?php

namespace App\Http\Requests\Tenant;

use App\Http\Requests\Tenant\Concerns\HasStockItems;

class PurchaseOrderRequest extends BaseRequest
{
    use HasStockItems;

    public function rules(): array
    {
        return [
            'supplier_id' => ['required', $this->tenantExists('suppliers', true)],
            'branch_id' => ['required', $this->tenantExists('branches', true)],
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
