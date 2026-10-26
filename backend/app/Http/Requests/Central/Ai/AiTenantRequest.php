<?php

namespace App\Http\Requests\Central\Ai;

use App\Enums\AiFeature;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AiTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ai_enabled' => ['nullable', 'boolean'],
            'monthly_token_quota' => ['nullable', 'integer', 'min:0', 'max:100000000000'],
            'monthly_image_quota' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'monthly_video_quota' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'allowed_model_ids' => ['nullable', 'array'],
            'allowed_model_ids.*' => ['integer', Rule::exists('ai_models', 'id')->whereNull('deleted_at')],
            'features' => ['nullable', 'array'],
            'features.*' => [Rule::in(AiFeature::values())],
        ];
    }

    public function attributes(): array
    {
        return trans('ai.fields');
    }
}
