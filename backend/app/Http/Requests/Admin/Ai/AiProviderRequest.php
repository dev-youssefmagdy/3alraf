<?php

namespace App\Http\Requests\Central\Ai;

use App\Enums\AiDriverType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AiProviderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $creating = ! $this->route('ai_provider');

        return [
            'driver' => ['required', Rule::enum(AiDriverType::class)],
            'name' => ['required', 'string', 'max:120'],
            'api_key' => [$creating ? 'required' : 'nullable', 'string', 'max:500'],
            'base_url' => ['nullable', 'url:https,http', 'max:255', Rule::requiredIf(fn () => $this->input('driver') === 'custom_openai')],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return trans('ai.fields');
    }
}
