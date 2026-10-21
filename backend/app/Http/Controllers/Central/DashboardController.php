<?php

namespace App\Http\Controllers\Central;

use App\DTOs\Reports\ReportFilter;
use App\Http\Controllers\Controller;
use App\Models\Language;
use App\Models\Tenant;
use App\Services\Reports\ReportService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request, ReportService $reports)
    {
        $f = ReportFilter::fromRequest($request, true);

        return view('central.dashboard', [
            'tenantsCount' => Tenant::count(),
            'activeTenants' => Tenant::where('is_active', true)->count(),
            'languagesCount' => Language::count(),
            'selected' => ($id = selected_tenant_id()) ? Tenant::find($id) : null,
            'f' => $f, 'd' => $reports->dashboard($f),
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
