<?php

namespace App\Http\Requests\Tenant;

class CityRequest extends BaseRequest
{
    public function rules(): array
    {
        return $this->translatable('name') + ['sort_order' => ['nullable', 'integer', 'min:0'], 'is_active' => ['nullable', 'boolean']];
    }
}
