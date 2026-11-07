<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class SmallBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:150'], 'product_id' => ['nullable', 'integer'], 'mode' => ['required', 'in:interest,survey,limited_stock'], 'description' => ['nullable', 'string', 'max:2000'], 'survey_question' => ['nullable', 'string', 'max:300'], 'preorder' => ['nullable', 'boolean'], 'preorder_terms' => ['nullable', 'string', 'max:2000'], 'limited_stock_qty' => ['nullable', 'integer', 'min:1', 'max:100000'], 'target_signups' => ['required', 'integer', 'min:1', 'max:100000'], 'starts_on' => ['nullable', 'date'], 'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on']];
    }
}
