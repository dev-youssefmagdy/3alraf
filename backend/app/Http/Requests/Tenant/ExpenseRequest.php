<?php

namespace App\Http\Requests\Tenant;

class ExpenseRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'expense_category_id' => ['required', $this->tenantExists('expense_categories')],
            'branch_id' => ['nullable', $this->tenantExists('branches', true)],
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999999'],
            'expense_date' => ['required', 'date'],
            'is_paid' => ['nullable', 'boolean'],
            'paid_from' => ['nullable', 'string', 'in:cash,bank,pos_cash,wallet,gateway'],
            'note' => ['nullable', 'string', 'max:2000'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:4096'],
        ];
    }
}
