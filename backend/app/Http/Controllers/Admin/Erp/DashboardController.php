<?php

namespace App\Http\Controllers\Admin\Erp;

use App\Models\Branch;
use App\Services\Erp\Reports\DashboardService;
use App\Services\Erp\Reports\ErpReportFilter;
use Illuminate\Http\Request;

class DashboardController extends ErpController
{
    public function index(Request $r, DashboardService $svc)
    {
        $this->allow('erp.dashboard.view');
        $this->ensureSetup();
        $r->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date'], 'branch_id' => ['nullable', 'integer']]);
        $canBranch = (bool) auth('admin')->user()->can('erp.branches.view');
        $f = ErpReportFilter::fromRequest($r, $canBranch);
        if ($f->branchId) {
            Branch::findOrFail($f->branchId);
        }

        return view('admin.erp.dashboard', ['d' => $svc->run($f), 'f' => $f, 'canBranch' => $canBranch, 'branches' => $canBranch ? Branch::orderBy('id')->get() : collect()]);
    }
}
