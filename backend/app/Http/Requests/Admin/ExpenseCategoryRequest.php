<?php

namespace App\Http\Requests\Admin;

class ExpenseCategoryRequest extends BaseRequest
{
    public function rules(): array
    {
        return $this->translatable('name') + ['is_active' => ['nullable', 'boolean'],
            // ERP mapping: a postable expense (or supplies/prepaid asset)
            'erp_account_id' => ['nullable', 'integer', \Illuminate\Validation\Rule::exists('erp_accounts', 'id')->where('is_group', false)->whereIn('type', ['expense', 'asset'])]];
    }
}
