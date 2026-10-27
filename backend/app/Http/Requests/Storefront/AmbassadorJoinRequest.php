<?php

namespace App\Http\Requests\Storefront;

use Illuminate\Foundation\Http\FormRequest;

class AmbassadorJoinRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['disclosure' => ['required', 'string', 'max:300'], 'terms_accepted' => ['required', 'accepted']];
    }
}
