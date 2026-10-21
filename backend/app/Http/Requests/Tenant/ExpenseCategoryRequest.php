<?php

namespace App\Http\Requests\Tenant;

class ExpenseCategoryRequest extends BaseRequest
{
    public function rules(): array
    {
        return $this->translatable('name') + ['is_active' => ['nullable', 'boolean']];
    }
}
