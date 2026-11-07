<?php

namespace App\Http\Requests\Tenant;

use App\Enums\AdjustmentReason;
use App\Enums\AdjustmentType;
use App\Http\Requests\Tenant\Concerns\HasStockItems;
use Illuminate\Validation\Rule;

class StockAdjustmentRequest extends BaseRequest
{
    use HasStockItems;

    public function rules(): array
    {
        return [
            'branch_id' => ['required', $this->tenantExists('branches', true)],
            'type' => ['required', Rule::enum(AdjustmentType::class)],
            'reason' => ['required', Rule::enum(AdjustmentReason::class)],
            'note' => ['nullable', 'string', 'max:2000'],
        ] + $this->itemRules(true);
    }

    public function attributes(): array
    {
        return parent::attributes() + $this->itemAttributes();
    }
}
