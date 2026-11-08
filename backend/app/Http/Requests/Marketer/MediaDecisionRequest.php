<?php

namespace App\Http\Requests\Marketer;

use App\Http\Requests\Admin\BaseRequest;

/** Library approve / reject note; Meta draft approve / reject note; Meta publish idempotency key. Permissions: route middleware + services. */
class MediaDecisionRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'note' => ['nullable', 'string', 'max:1000'],
            'idempotency_key' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9\-]+$/'],
        ];
    }
}
