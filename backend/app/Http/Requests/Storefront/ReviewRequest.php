<?php

namespace App\Http\Requests\Storefront;

use Illuminate\Foundation\Http\FormRequest;

class ReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['rating' => ['required', 'integer', 'between:1,5'], 'body' => ['nullable', 'string', 'max:2000']];
    }

    public function attributes(): array
    {
        return trans('growth.fields');
    }
}
