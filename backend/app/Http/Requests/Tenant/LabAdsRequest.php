<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class LabAdsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['date' => ['required', 'date', 'before_or_equal:today'], 'spend' => ['required', 'numeric', 'min:0', 'max:99999999'], 'impressions' => ['nullable', 'integer', 'min:0'], 'clicks' => ['nullable', 'integer', 'min:0'], 'platform' => ['nullable', 'string', 'max:30'], 'creative' => ['nullable', 'string', 'max:80']];
    }
}
