<?php

namespace App\Http\Requests\Tenant;

class AiProductEditApplyRequest extends BaseRequest
{
    public function rules(): array
    {
        return ['accept' => ['required', 'array', 'min:1'], 'accept.*' => ['string', 'regex:/^[a-z_]+\.[a-z]{2,5}(-[A-Za-z]{2,4})?$/'], 'reason' => ['nullable', 'string', 'max:300']];
    }
}
