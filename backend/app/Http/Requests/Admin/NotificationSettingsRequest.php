<?php

namespace App\Http\Requests\Tenant;

use App\Support\CustomerMailPolicy;

class NotificationSettingsRequest extends BaseRequest
{
    public function rules(): array
    {
        return array_fill_keys(CustomerMailPolicy::GROUPS, ['nullable', 'boolean']);
    }
}
