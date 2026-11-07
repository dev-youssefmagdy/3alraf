<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class AmbassadorIdeaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['title' => ['required', 'string', 'max:150'], 'body' => ['required', 'string', 'max:2000'], 'channel' => ['nullable', 'string', 'max:20']];
    }
}
