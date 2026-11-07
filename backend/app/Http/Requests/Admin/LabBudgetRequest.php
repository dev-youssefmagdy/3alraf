<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class LabBudgetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['budget_cap' => ['required', 'numeric', 'gt:0', 'max:99999999'], 'reason' => ['nullable', 'string', 'max:300']];
    }
}
