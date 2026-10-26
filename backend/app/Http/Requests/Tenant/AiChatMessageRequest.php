<?php

namespace App\Http\Requests\Tenant;

use App\Services\Ai\AiAccess;
use Illuminate\Validation\Rule;

class AiChatMessageRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:4000'],
            'ai_model_id' => ['nullable', 'integer', Rule::in(app(AiAccess::class)->modelsFor(current_tenant(), 'text')->pluck('id')->all())],   // tenant-allowed active text models only
        ];
    }

    public function attributes(): array
    {
        return trans('ai.fields') + ['message' => __('ai.chat.message')];
    }
}
