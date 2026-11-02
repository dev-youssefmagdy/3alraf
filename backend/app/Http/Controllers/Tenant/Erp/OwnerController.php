<?php

namespace App\Http\Controllers\Tenant\Erp;

use App\Models\Branch;
use App\Models\Erp\Owner;
use App\Models\Erp\OwnerTransaction;
use App\Models\TenantAdmin;
use App\Services\Erp\Owners\OwnerService;
use App\Services\Erp\Parties\PartyService;
use Illuminate\Http\Request;

/** Owners & equity. Everything here needs erp.owners.manage. */
class OwnerController extends ErpController
{
    public function index(OwnerService $svc)
    {
        $this->allow('erp.owners.manage');
        $this->ensureSetup();

        return view('tenant.erp.owners', ['owners' => Owner::orderBy('name')->get(), 'equity' => $svc->equity(), 'percent' => $svc->totalPercent(), 'branches' => Branch::orderBy('id')->get(),
            'users' => TenantAdmin::orderBy('name')->get(['id', 'name']), 'cashRoles' => PartyService::CASH_ROLES,
            'recent' => OwnerTransaction::with(['owner', 'branch'])->latest('txn_date')->latest('id')->limit(15)->get()]);
    }

    private function rules(): array
    {
        return ['name' => ['required', 'string', 'max:120'], 'phone' => ['nullable', 'string', 'max:32'], 'ownership_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'is_eligible' => ['nullable', 'boolean'], 'tenant_admin_id' => ['nullable', 'integer', $this->tenantExists('tenant_admins')]];
    }

    public function store(Request $r, OwnerService $svc)
    {
        $this->allow('erp.owners.manage');
        $d = $r->validate($this->rules());

        return $this->guarded(function () use ($svc, $d) {
            $svc->save($d);

            return back()->with('status', __('erp.saved'));
        });
    }

    public function update(Request $r, int $id, OwnerService $svc)
    {
        $this->allow('erp.owners.manage');
        $owner = Owner::findOrFail($id);
        $d = $r->validate($this->rules());

        return $this->guarded(function () use ($svc, $d, $owner) {
            $svc->save($d, $owner);

            return back()->with('status', __('erp.saved'));
        });
    }

    public function show(Request $r, int $id, OwnerService $svc)
    {
        $this->allow('erp.owners.manage');
        $this->ensureSetup();
        $r->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date']]);
        $owner = Owner::findOrFail($id);
        $from = $r->from ?: now()->startOfYear()->toDateString();
        $to = $r->to ?: now()->toDateString();

        return view('tenant.erp.owner-show', ['owner' => $owner, 'from' => $from, 'to' => $to, 'st' => $svc->statement($owner, $from, $to), 'equity' => $svc->equity()[$owner->id] ?? null,
            'branches' => Branch::orderBy('id')->get(), 'cashRoles' => PartyService::CASH_ROLES,
            'dists' => auth('tenant')->user()->can('erp.profits.view') ? app(\App\Services\Erp\Equity\ProfitDistributionService::class)->ownerLines($owner) : collect()]);
    }

    public function transact(Request $r, int $id, OwnerService $svc)
    {
        $this->allow('erp.owners.manage');
        $owner = Owner::findOrFail($id);
        $d = $r->validate(['type' => ['required', 'in:'.implode(',', OwnerTransaction::TYPES)], 'amount' => ['required', 'numeric', 'gt:0'], 'date' => ['required', 'date'],
            'account' => ['required', 'in:'.implode(',', PartyService::CASH_ROLES)], 'branch_id' => ['nullable', 'integer', $this->tenantExists('branches', true)], 'note' => ['nullable', 'string', 'max:300'], 'ref' => ['required', 'string', 'max:60']]);

        return $this->guarded(function () use ($svc, $owner, $d) {
            $svc->transact($owner, $d['type'], (string) $d['amount'], $d['date'], $d['account'], $d['branch_id'] ?? null, $d['note'] ?? null, $d['ref']);

            return back()->with('status', __('erp.posted'));
        });
    }

    public function allocate(Request $r, OwnerService $svc)
    {
        $this->allow('erp.owners.manage');
        $d = $r->validate(['branch_id' => ['required', 'integer', $this->tenantExists('branches', true)], 'amount' => ['required', 'numeric', 'gt:0'], 'date' => ['required', 'date'], 'note' => ['nullable', 'string', 'max:300'], 'ref' => ['required', 'string', 'max:60']]);

        return $this->guarded(function () use ($svc, $d) {
            $svc->allocateToBranch((int) $d['branch_id'], (string) $d['amount'], $d['date'], $d['note'] ?? null, $d['ref']);

            return back()->with('status', __('erp.posted'));
        });
    }
}
