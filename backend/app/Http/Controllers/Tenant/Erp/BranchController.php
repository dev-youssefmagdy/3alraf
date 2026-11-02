<?php

namespace App\Http\Controllers\Tenant\Erp;

use App\Models\Branch;
use App\Services\Erp\Accounting\BranchTransferService;
use App\Services\Erp\Reports\BranchReports;
use App\Services\Erp\Reports\ErpReportFilter;
use Illuminate\Http\Request;

/** Branch Accounting card: per-branch P&L, cash, inventory, transfers + branch-to-branch cash transfer. Needs erp.branches.view. */
class BranchController extends ErpController
{
    public function index(Request $r, BranchReports $svc)
    {
        $this->allow('erp.branches.view');
        $this->ensureSetup();
        $r->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date']]);
        $f = ErpReportFilter::fromRequest($r, true);
        $f->branchId = null;

        return view('tenant.erp.branches', ['rows' => $svc->overview($f), 'f' => $f, 'branches' => Branch::orderBy('id')->get(), 'canTransfer' => (bool) auth('tenant')->user()->can('erp.journals.create')]);
    }

    public function transfer(Request $r, BranchTransferService $svc)
    {
        $this->allow('erp.branches.view');
        $this->allow('erp.journals.create');
        $this->ensureSetup();
        $v = $r->validate(['from_branch' => ['required', 'integer'], 'to_branch' => ['required', 'integer'], 'amount' => ['required', 'numeric', 'gt:0', 'max:999999999999'],
            'date' => ['nullable', 'date'], 'note' => ['nullable', 'string', 'max:200'], 'role' => ['nullable', 'in:cash,bank,pos_cash'], 'ref' => ['nullable', 'string', 'max:60']]);

        return $this->guarded(function () use ($svc, $v) {
            $svc->cash((int) $v['from_branch'], (int) $v['to_branch'], number_format((float) $v['amount'], 2, '.', ''), $v['date'] ?? null, $v['note'] ?? null, $v['role'] ?? 'cash', $v['ref'] ?? null, auth('tenant')->id());

            return redirect()->route('tenant.erp.branches.index')->with('status', __('erp_rep.bt.done'));
        });
    }
}
