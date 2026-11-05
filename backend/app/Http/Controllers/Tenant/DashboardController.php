<?php

namespace App\Http\Controllers\Tenant;

use App\DTOs\Reports\ReportFilter;
use App\Http\Controllers\Controller;
use App\Services\Reports\ReportService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request, ReportService $reports)
    {
        $f = ReportFilter::fromRequest($request, false);
        $user = auth('tenant')->user();
        $data = $user->can('reports.view') ? $reports->dashboard($f) : null;
        // ERP-030: month-to-date ledger KPIs (the same figures as the ERP dashboard / P&L) once the tenant's ledger exists
        $erp = null;
        if ($user->can('erp.dashboard.view') && \App\Models\Erp\Account::exists()) {
            $ef = new \App\Services\Erp\Reports\ErpReportFilter(now()->startOfMonth()->toDateString(), now()->toDateString(), null);
            $erp = app(\App\Services\Erp\Reports\DashboardService::class)->kpis($ef);
        }

        return view('tenant.dashboard', ['tenant' => current_tenant(), 'f' => $f, 'd' => $data, 'erp' => $erp]);
    }
}
