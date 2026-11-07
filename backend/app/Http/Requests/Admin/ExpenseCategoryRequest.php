<?php

namespace App\Http\Requests\Tenant;

class ExpenseCategoryRequest extends BaseRequest
{
    public function rules(): array
    {
        return $this->translatable('name') + ['is_active' => ['nullable', 'boolean'],
            // ERP mapping: a postable expense (or supplies/prepaid asset) account of this tenant
            'erp_account_id' => ['nullable', 'integer', \Illuminate\Validation\Rule::exists('erp_accounts', 'id')->where('tenant_id', current_tenant()?->id)->where('is_group', false)->whereIn('type', ['expense', 'asset'])]];
    }
}
