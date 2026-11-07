<?php

namespace App\Http\Requests\Tenant;

use App\Http\Requests\Tenant\Concerns\HasStockItems;

class StockTransferRequest extends BaseRequest
{
    use HasStockItems;

    public function rules(): array
    {
        return [
            'from_branch_id' => ['required', $this->tenantExists('branches', true)],
            'to_branch_id' => ['required', 'different:from_branch_id', $this->tenantExists('branches', true)],
            'note' => ['nullable', 'string', 'max:2000'],
        ] + $this->itemRules();
    }

    public function attributes(): array
    {
        return parent::attributes() + $this->itemAttributes();
    }
}
