<?php

namespace App\Http\Requests\Tenant;

use App\Http\Requests\Concerns\TranslatableRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rule;

/** Shared bits for tenant-admin form requests (authorization is done by route middleware). */
abstract class BaseRequest extends FormRequest
{
    use TranslatableRules;

    public function authorize(): bool
    {
        return true;
    }

    public function attributes(): array
    {
        return trans('ops.fields') + trans('admin.fields') + (array) trans('orders.fields');
    }

    /** exists:{table},id restricted to the current tenant (and non-deleted rows when the table soft-deletes). */
    protected function tenantExists(string $table, bool $softDeletes = false): Exists
    {
        return Rule::exists($table, 'id')->where(function ($q) use ($softDeletes) {
            $q->where('tenant_id', current_tenant()->id);
            if ($softDeletes) {
                $q->whereNull('deleted_at');
            }
        });
    }

    protected function id(): ?int
    {
        $id = $this->route('id');

        return $id ? (int) $id : null;
    }

    /** Nullable decimal string. */
    protected function dec(mixed $v): ?string
    {
        return ($v === null || $v === '') ? null : (string) $v;
    }
}
