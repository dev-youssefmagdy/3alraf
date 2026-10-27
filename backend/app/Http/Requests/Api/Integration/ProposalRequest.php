<?php

namespace App\Http\Requests\Api\Integration;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProposalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['type' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9_.]+$/', Rule::in((array) config('growth.approvals.api_types'))], 'title' => ['required', 'string', 'max:200'], 'payload' => ['nullable', 'array'], 'payload.*' => ['nullable']];
    }
}
