<?php

namespace App\Http\Requests\Admin;

class ExpenseRequest extends BaseRequest
{
    /** Ledger roles an expense can be paid from (ERP-013). */
    public const PAID_FROM = ['cash', 'bank', 'pos_cash', 'wallet', 'gateway'];

    public function rules(): array
    {
        return [
            'expense_category_id' => ['required', $this->existsRule('expense_categories')],
            'branch_id' => ['nullable', $this->existsRule('branches', true)],
            'amount' => \App\Support\ErpRules::money(), // QA-004/006: strict decimal, fits decimal(18,4) ledger columns
            'expense_date' => \App\Support\ErpRules::date(),
            'is_paid' => ['nullable', 'boolean'],
            'paid_from' => ['nullable', 'string', 'in:'.implode(',', self::PAID_FROM)],
            // payee of an unpaid bill (party on the payable line, ERP-013): required so the supplier sub-ledger ties to Accounts payable
            'supplier_id' => ['nullable', 'required_if:is_paid,0,false', $this->existsRule('suppliers', true)],
            'note' => ['nullable', 'string', 'max:2000'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:4096'],
        ];
    }
}
