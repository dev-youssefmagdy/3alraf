<?php

namespace App\Http\Requests\Api\Integration;

use Illuminate\Foundation\Http\FormRequest;

class AdResultsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['rows' => ['required', 'array', 'min:1', 'max:500'], 'rows.*.date' => ['required', 'date', 'before_or_equal:today'], 'rows.*.spend' => ['required', 'numeric', 'min:0', 'max:99999999'], 'rows.*.impressions' => ['nullable', 'integer', 'min:0'], 'rows.*.clicks' => ['nullable', 'integer', 'min:0'], 'rows.*.platform' => ['nullable', 'string', 'max:30'], 'rows.*.campaign_id' => ['nullable', 'string', 'max:80'], 'rows.*.ad_id' => ['nullable', 'string', 'max:80'], 'rows.*.creative' => ['nullable', 'string', 'max:80']];
    }
}
