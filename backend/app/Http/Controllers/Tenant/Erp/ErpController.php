<?php

namespace App\Http\Controllers\Tenant\Erp;

use App\Exceptions\AccountingException;
use App\Http\Controllers\Controller;
use App\Models\Erp\Account;
use App\Services\Erp\Accounting\CoaTemplate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/** Shared guards for the ERP pages: permission + lazy first-use setup of the tenant's chart of accounts. */
abstract class ErpController extends Controller
{
    protected function allow(string $perm): void
    {
        abort_unless(auth('tenant')->user()?->can($perm), 403);
    }

    /** Any of the given permissions (e.g. view-only OR manage). */
    protected function allowAny(string ...$perms): void
    {
        $u = auth('tenant')->user();
        abort_unless($u && collect($perms)->contains(fn ($p) => $u->can($p)), 403);
    }

    /** `exists` rule limited to the current tenant (the bare `exists:table,id` rule bypasses the model tenant scope). */
    protected function tenantExists(string $table, bool $softDeletes = false): Exists
    {
        return Rule::exists($table, 'id')->where(function ($q) use ($softDeletes) {
            $q->where('tenant_id', current_tenant()->id);
            if ($softDeletes) {
                $q->whereNull('deleted_at');
            }
        });
    }

    protected function ensureSetup(): void
    {
        if (! Account::exists()) {
            app(CoaTemplate::class)->seed(current_tenant());
        }
    }

    protected function guarded(callable $fn)
    {
        try {
            return $fn();
        } catch (AccountingException $e) {
            return back()->withInput()->withErrors(['erp' => $e->getMessage()]);
        }
    }
}
