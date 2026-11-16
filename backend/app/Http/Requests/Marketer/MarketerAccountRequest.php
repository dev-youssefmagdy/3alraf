<?php

namespace App\Http\Requests\Marketer;

use App\Http\Requests\Admin\BaseRequest;
use App\Models\Marketer;
use App\Support\AdminPhone;
use App\Support\Marketing\MarketingPermissions;
use Illuminate\Validation\Rule;

/** Marketer account create / edit (marketer panel → Team → Marketers, perm marketers.manage). One role per account. */
class MarketerAccountRequest extends BaseRequest
{
    public function rules(): array
    {
        $id = $this->id();

        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:190', Rule::unique('marketers', 'email')->ignore($id)],
            'country' => ['required', Rule::in(array_keys(AdminPhone::countries()))],
            'phone' => ['required', 'string', 'max:30', function ($attr, $v, $fail) use ($id) {
                $d = AdminPhone::digits((string) $this->input('country'), $v);
                if (! $d) {
                    return $fail(__('validation.regex', ['attribute' => $attr]));
                }
                if (Marketer::query()->where('phone', $d)->when($id, fn ($w) => $w->whereKeyNot($id))->exists()) {
                    $fail(__('validation.unique', ['attribute' => $attr]));
                }
            }],
            'password' => [$id ? 'nullable' : 'required', 'string', 'min:8'],
            'role' => ['required', Rule::in([MarketingPermissions::ROLE, MarketingPermissions::OWNER_ROLE])],
            'is_active' => ['boolean'],
            'otp_exempt' => ['boolean'],
        ];
    }

    public function attributes(): array
    {
        return trans('access.fields') + ['role' => __('access.fields.roles')];
    }
}
