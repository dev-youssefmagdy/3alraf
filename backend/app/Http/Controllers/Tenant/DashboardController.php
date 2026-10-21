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
        $data = auth('tenant')->user()->can('reports.view') ? $reports->dashboard($f) : null;

        return view('tenant.dashboard', ['tenant' => current_tenant(), 'f' => $f, 'd' => $data]);
    }
}
