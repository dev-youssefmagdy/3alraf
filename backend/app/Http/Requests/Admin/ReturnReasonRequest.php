<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class ReturnReasonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['kind' => ['required', 'in:return,cancellation'], 'id' => ['required', 'integer'], 'code' => ['required', 'in:'.implode(',', \App\Services\Growth\ReturnIntelService::CODES)]];
    }
}
