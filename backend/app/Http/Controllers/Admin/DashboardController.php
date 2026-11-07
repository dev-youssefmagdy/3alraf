<?php

namespace App\Http\Controllers\Admin;

use App\DTOs\Reports\ReportFilter;
use App\Http\Controllers\Controller;
use App\Services\Reports\ReportService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request, ReportService $reports)
    {
        $f = ReportFilter::fromRequest($request);
        $user = auth('admin')->user();
        $data = $user->can('reports.view') ? $reports->dashboard($f) : null;
        // ERP-030: month-to-date ledger KPIs (the same figures as the ERP dashboard / P&L) once the ledger exists
        $erp = null;
        if ($user->can('erp.dashboard.view') && \App\Models\Erp\Account::exists()) {
            $ef = new \App\Services\Erp\Reports\ErpReportFilter(now()->startOfMonth()->toDateString(), now()->toDateString(), null);
            $erp = app(\App\Services\Erp\Reports\DashboardService::class)->kpis($ef);
        }

        return view('admin.dashboard', ['f' => $f, 'd' => $data, 'erp' => $erp]);
    }
}
