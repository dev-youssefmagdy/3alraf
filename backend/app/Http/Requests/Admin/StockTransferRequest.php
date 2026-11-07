<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Admin\Concerns\HasStockItems;

class StockTransferRequest extends BaseRequest
{
    use HasStockItems;

    public function rules(): array
    {
        return [
            'from_branch_id' => ['required', $this->existsRule('branches', true)],
            'to_branch_id' => ['required', 'different:from_branch_id', $this->existsRule('branches', true)],
            'note' => ['nullable', 'string', 'max:2000'],
        ] + $this->itemRules();
    }

    public function attributes(): array
    {
        return parent::attributes() + $this->itemAttributes();
    }
}
