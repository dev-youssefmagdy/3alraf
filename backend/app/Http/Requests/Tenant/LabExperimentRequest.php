<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class LabExperimentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:150'], 'product_id' => ['required', 'integer'], 'hypothesis' => ['required', 'string', 'max:2000'], 'audience' => ['required', 'string', 'max:200'], 'platform' => ['nullable', 'string', 'max:30'], 'angle' => ['required', 'string', 'max:2000'], 'budget_cap' => ['required', 'numeric', 'gt:0', 'max:99999999'], 'starts_on' => ['nullable', 'date'], 'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on']];
    }
}
