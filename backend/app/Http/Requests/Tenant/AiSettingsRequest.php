<?php

namespace App\Http\Requests\Tenant;

use App\Models\AiModel;
use App\Services\Ai\AiAccess;
use Illuminate\Validation\Rule;

class AiSettingsRequest extends BaseRequest
{
    public const PLATFORMS = ['tiktok', 'instagram', 'facebook', 'x', 'pinterest', 'whatsapp'];

    public function rules(): array
    {
        $allowed = app(AiAccess::class)->modelsFor(current_tenant(), 'text')->pluck('id')->all();

        return [
            'default_ai_model_id' => ['nullable', 'integer', Rule::in($allowed)],   // only models the platform allowed for this tenant
            'brand_voice' => ['nullable', 'string', 'max:1000'],
            'forbidden_words' => ['nullable', 'string', 'max:2000'],
            'default_platforms' => ['nullable', 'array'],
            'default_platforms.*' => [Rule::in(self::PLATFORMS)],
        ];
    }

    public function attributes(): array
    {
        return trans('ai.fields');
    }
}
