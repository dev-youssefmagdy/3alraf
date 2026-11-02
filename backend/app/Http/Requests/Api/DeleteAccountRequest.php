<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/** Account deletion confirmation: the password, or (Google-only accounts without a password) `confirm=true`. */
class DeleteAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return (string) $this->user()?->getAuthPassword() !== ''
            ? ['password' => ['required', 'string', 'current_password:web']]
            : ['confirm' => ['required', 'accepted']];
    }
}
