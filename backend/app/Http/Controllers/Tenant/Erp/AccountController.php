<?php

namespace App\Http\Controllers\Tenant\Erp;

use App\Models\Erp\Account;
use App\Models\Erp\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AccountController extends ErpController
{
    public function index()
    {
        $this->allow('erp.accounts.view');
        $this->ensureSetup();

        return view('tenant.erp.accounts', ['accounts' => Account::with('parent')->orderBy('code')->get(), 'types' => Account::TYPES]);
    }

    public function store(Request $r)
    {
        $this->allow('erp.accounts.manage');
        $d = $r->validate([
            'code' => ['required', 'string', 'max:32', Rule::unique('erp_accounts', 'code')->where('tenant_id', current_tenant()->id)],
            'name_en' => ['required', 'string', 'max:150'], 'name_ar' => ['nullable', 'string', 'max:150'], 'description' => ['nullable', 'string', 'max:500'],
            'type' => ['required', Rule::in(Account::TYPES)], 'parent_id' => ['nullable', 'integer'], 'branch_id' => ['nullable', 'integer', $this->tenantExists('branches', true)],
            'is_group' => ['nullable', 'boolean'],
        ]);
        $parent = ! empty($d['parent_id']) ? Account::findOrFail($d['parent_id']) : null; // scoped: foreign ids 404
        if ($parent && $parent->type !== $d['type']) {
            return back()->withInput()->withErrors(['parent_id' => __('erp.errors.parent_type')]);
        }
        Account::create(['code' => $d['code'], 'name' => ['en' => $d['name_en'], 'ar' => $d['name_ar'] ?: $d['name_en']], 'description' => $d['description'] ?? null,
            'type' => $d['type'], 'normal_balance' => Account::normalBalanceFor($d['type']), 'parent_id' => $parent?->id, 'branch_id' => $d['branch_id'] ?? null,
            'currency' => current_tenant()->currency_code, 'is_group' => (bool) ($d['is_group'] ?? false)]);

        return back()->with('status', __('erp.saved'));
    }

    /** Accounts with posted activity (or system roles) are archived, never deleted. */
    public function destroy(int $id)
    {
        $this->allow('erp.accounts.manage');
        $a = Account::findOrFail($id);
        if ($a->is_system && $a->role_key) {
            return back()->withErrors(['erp' => __('erp.errors.system_account')]);
        }
        if ($a->children()->exists()) {
            return back()->withErrors(['erp' => __('erp.errors.has_children')]);
        }
        if ($a->hasPostedActivity()) {
            $a->update(['is_active' => false, 'archived_at' => now()]);
            AuditLog::create(['action' => 'account.archived', 'subject_type' => 'account', 'subject_id' => $a->id, 'user_id' => auth('tenant')->id(), 'meta' => ['code' => $a->code]]);

            return back()->with('status', __('erp.archived'));
        }
        $a->delete();

        return back()->with('status', __('erp.deleted'));
    }

    public function restore(int $id)
    {
        $this->allow('erp.accounts.manage');
        Account::findOrFail($id)->update(['is_active' => true, 'archived_at' => null]);

        return back()->with('status', __('erp.saved'));
    }
}
