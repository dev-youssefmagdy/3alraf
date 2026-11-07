<?php

namespace App\Http\Requests\Admin;

class BranchRequest extends BaseRequest
{
    public function rules(): array
    {
        return $this->translatable('name') + [
            'city_id' => ['nullable', $this->existsRule('cities', true)],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'is_default' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
