<?php

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;

class AmbassadorEnrollRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['affiliate_id' => ['required', 'integer'], 'disclosure' => ['required', 'string', 'max:300'], 'reward_per_order' => ['nullable', 'numeric', 'min:0', 'max:10000'], 'reward_rule' => ['nullable', 'string', 'max:300'], 'terms_accepted' => ['required', 'accepted']];
    }
}
