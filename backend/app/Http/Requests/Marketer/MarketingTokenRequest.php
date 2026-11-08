<?php

namespace App\Http\Requests\Marketer;

use App\Http\Requests\Admin\BaseRequest;
use App\Services\Marketing\MarketingTokenService;
use Illuminate\Validation\Rule;

/** Marketer panel → API tokens → create (perm marketing.tokens.manage on the route). Only enabled scopes are grantable. */
class MarketingTokenRequest extends BaseRequest
{
    public function rules(): array
    {
        $days = array_values(array_filter((array) config('marketing.token_expiry_days'), fn ($d) => $d !== null));

        return [
            'name' => ['required', 'string', 'max:100'],
            'scopes' => ['required', 'array', 'min:1'],
            'scopes.*' => ['string', Rule::in(MarketingTokenService::grantableScopes())],
            'expires_in_days' => ['nullable', Rule::in(array_merge(array_map('strval', $days), $days, ['never']))],
        ];
    }

    public function attributes(): array
    {
        return ['name' => __('marketing.tokens.name'), 'scopes' => __('marketing.tokens.scopes'), 'expires_in_days' => __('marketing.tokens.expires')];
    }
}
