<?php

namespace App\Http\Requests\Tenant;

class AiGeneratorRejectRequest extends BaseRequest
{
    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'min:3', 'max:500']];
    }

    public function attributes(): array
    {
        return ['reason' => __('ai.gen.reason')];
    }
}
