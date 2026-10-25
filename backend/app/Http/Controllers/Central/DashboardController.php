<?php

namespace App\Http\Controllers\Central;

use App\DTOs\Reports\ReportFilter;
use App\Http\Controllers\Controller;
use App\Models\Language;
use App\Models\Tenant;
use App\Services\Reports\ReportService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class DashboardController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        // report data + the tenant switcher are reports.view only; the landing page itself stays reachable
        return [new Middleware('can:reports.view', only: ['switchTenant'])];
    }

    public function index(Request $request, ReportService $reports)
    {
        $f = ReportFilter::fromRequest($request, true);
        $canReports = (bool) auth('central')->user()?->can('reports.view');

        return view('central.dashboard', [
            'tenantsCount' => Tenant::count(),
            'activeTenants' => Tenant::where('is_active', true)->count(),
            'languagesCount' => Language::count(),
            'selected' => $canReports && ($id = selected_tenant_id()) ? Tenant::find($id) : null,
            'f' => $f, 'd' => $canReports ? $reports->dashboard($f) : null,
        ]);
    }

    /** Central manager view: pick a tenant (or all) for dashboards/reports. */
    public function switchTenant(Request $request)
    {
        $id = $request->input('tenant_id');
        $request->session()->put('central_tenant_id', $id && Tenant::whereKey($id)->exists() ? (int) $id : null);

        return back();
    }
}
