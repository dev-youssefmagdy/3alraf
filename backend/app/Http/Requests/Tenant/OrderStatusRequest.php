<?php

namespace App\Http\Requests\Tenant;

use App\Enums\OrderStatus;
use Illuminate\Validation\Rule;

class OrderStatusRequest extends BaseRequest
{
    public function rules(): array
    {
        return ['status' => ['required', Rule::in(OrderStatus::values())], 'note' => ['nullable', 'string', 'max:255']];
    }
}
