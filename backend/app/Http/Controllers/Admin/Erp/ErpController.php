<?php

namespace App\Http\Controllers\Admin\Erp;

use App\Exceptions\AccountingException;
use App\Http\Controllers\Controller;
use App\Models\Erp\Account;
use App\Services\Erp\Accounting\CoaTemplate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/** Shared guards for the ERP pages: permission + lazy first-use setup of the chart of accounts. */
abstract class ErpController extends Controller
{
    protected function allow(string $perm): void
    {
        abort_unless(auth('admin')->user()?->can($perm), 403);
    }

    /** Any of the given permissions (e.g. view-only OR manage). */
    protected function allowAny(string ...$perms): void
    {
        $u = auth('admin')->user();
        abort_unless($u && collect($perms)->contains(fn ($p) => $u->can($p)), 403);
    }

    /** `exists` rule on `id` (optionally ignoring soft-deleted rows). */
    protected function existsRule(string $table, bool $softDeletes = false): Exists
    {
        $rule = Rule::exists($table, 'id');

        return $softDeletes ? $rule->whereNull('deleted_at') : $rule;
    }

    protected function ensureSetup(): void
    {
        if (! Account::exists()) {
            app(CoaTemplate::class)->seed();
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
