<?php

namespace App\Http\Requests\Api\Integration;

use Illuminate\Foundation\Http\FormRequest;

class TrackedLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['product_id' => ['required', 'integer', 'min:1'], 'channel' => ['nullable', 'string', 'max:20'], 'interaction_id' => ['nullable', 'integer', 'min:1'], 'campaign' => ['nullable', 'string', 'max:60']];
    }
}
