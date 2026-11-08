<?php

namespace App\Http\Requests\Marketer;

use App\Http\Requests\Admin\BaseRequest;

/** approve / reject (note), apply (idempotency key + type apply options), blog publish / schedule (publish_at). Permissions: route middleware + engine. */
class SuggestionActionRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'note' => ['nullable', 'string', 'max:1000'],
            'idempotency_key' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9\-]+$/'],
            'options' => ['nullable', 'array'],
            'options.*' => ['nullable', 'boolean'],
            'publish_at' => ['nullable', 'date', 'after:now'],
        ];
    }
}
