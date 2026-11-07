<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class MissedUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['status' => ['nullable', 'in:open,accepted,done,dismissed'], 'owner' => ['nullable', 'string', 'max:100'], 'due_on' => ['nullable', 'date'], 'result_note' => ['nullable', 'string', 'max:500']];
    }
}
