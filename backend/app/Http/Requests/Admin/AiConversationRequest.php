<?php

namespace App\Http\Requests\Tenant;

class AiConversationRequest extends BaseRequest
{
    public function rules(): array
    {
        return ['title' => ['required', 'string', 'max:120']];
    }

    public function attributes(): array
    {
        return ['title' => __('ai.chat.title')];
    }
}
