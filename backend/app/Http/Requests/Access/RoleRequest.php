<?php

namespace App\Http\Requests\Access;

use App\Services\Access\AccessService;
use Illuminate\Foundation\Http\FormRequest;

class RoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public static function guardFor(\Illuminate\Http\Request $r): string
    {
        return str_starts_with((string) $r->route()?->getName(), 'central.') ? 'central' : 'tenant';
    }

    public function rules(): array
    {
        $guard = self::guardFor($this);
        $id = $this->route('id') ? (int) $this->route('id') : null;

        return [
            'name' => ['required', 'string', 'max:60', function ($a, $v, $fail) use ($guard, $id) {
                if (app(AccessService::class)->nameTaken($guard, (string) $v, $id)) {
                    $fail(__('validation.unique', ['attribute' => $a]));
                }
            }],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name,guard_name,'.$guard],
        ];
    }

    public function attributes(): array
    {
        return trans('access.fields');
    }
}
