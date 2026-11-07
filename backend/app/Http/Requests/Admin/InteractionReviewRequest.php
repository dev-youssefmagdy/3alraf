<?php

namespace App\Http\Requests\Tenant;

use App\Services\Growth\InteractionService as I;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InteractionReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['category' => ['nullable', Rule::in(I::CATEGORIES)], 'intent' => ['required', Rule::in(I::INTENTS)], 'sentiment' => ['required', Rule::in(I::SENTIMENT)]];
    }
}
