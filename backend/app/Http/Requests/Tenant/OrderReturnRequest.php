<?php

namespace App\Http\Requests\Tenant;

class OrderReturnRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.order_item_id' => ['required', 'integer'],
            'items.*.quantity' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'reason' => ['nullable', 'string', 'max:255'],
            'reason_code' => ['nullable', 'in:'.implode(',', \App\Services\Growth\ReturnIntelService::CODES)],
            'restock' => ['nullable', 'boolean'],
            'refund_shipping' => ['nullable', 'boolean'],
            'branch_id' => ['nullable', $this->tenantExists('branches', true)],
            'return_shipping_cost' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
        ];
    }
}
