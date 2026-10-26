<?php

namespace App\Http\Requests\Central\Ai;

use App\Enums\AiModelKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AiModelRequest extends FormRequest
{
    public const CAPABILITIES = ['tools', 'vision', 'web_search', 'json_mode'];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ai_provider_id' => ['required', Rule::exists('ai_providers', 'id')->whereNull('deleted_at')],
            'model_id' => ['required', 'string', 'max:150'],
            'label' => ['required', 'string', 'max:150'],
            'kind' => ['required', Rule::enum(AiModelKind::class)],
            'capabilities' => ['nullable', 'array'],
            'capabilities.*' => [Rule::in(self::CAPABILITIES)],
            'input_cost' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'output_cost' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'max_output' => ['nullable', 'integer', 'min:1', 'max:2000000'],
            'is_active' => ['nullable', 'boolean'],
            'is_default' => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return trans('ai.fields');
    }
}
