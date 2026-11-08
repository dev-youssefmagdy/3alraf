<?php

namespace App\Http\Requests\Admin;

use App\Enums\ReturnReasonCode;
use Illuminate\Validation\Rule;

class OrderReturnRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.order_item_id' => ['required', 'integer'],
            'items.*.quantity' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'reason' => ['nullable', 'string', 'max:255'],
            'reason_code' => ['nullable', Rule::enum(ReturnReasonCode::class)],
            'restock' => ['nullable', 'boolean'],
            'refund_shipping' => ['nullable', 'boolean'],
            'branch_id' => ['nullable', $this->existsRule('branches', true)],
            'return_shipping_cost' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
        ];
    }
}
