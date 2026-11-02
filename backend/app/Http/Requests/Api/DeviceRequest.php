<?php

namespace App\Http\Requests\Api;

use App\Models\Device;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'min:20', 'max:512'],
            'platform' => ['required', Rule::in(Device::PLATFORMS)],
            'locale' => ['nullable', 'string', 'max:5'],
            'app_version' => ['nullable', 'string', 'max:20'],
        ];
    }
}
