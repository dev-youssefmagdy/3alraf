<?php

namespace App\Http\Controllers\Central;

use App\Services\Growth\ActionLogger;
use App\Services\Growth\GrowthAccess;
use App\DataTables\TenantDataTable;
use App\DTOs\TenantData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Central\TenantRequest;
use App\Models\Language;
use App\Models\Tenant;
use App\Services\TenantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class TenantController extends Controller implements HasMiddleware
{
    public function __construct(private TenantService $service) {}

    public static function middleware(): array
    {
        return [
            new Middleware('can:tenants.view', only: ['index', 'datatable']),
            new Middleware('can:tenants.create', only: ['create', 'store']),
            new Middleware('can:tenants.edit', only: ['edit', 'update', 'growth']),
            new Middleware('can:tenants.delete', only: ['destroy']),
        ];
    }

    public function index()
    {
        return view('central.tenants.index', ['table' => new TenantDataTable]);
    }

    public function datatable(TenantDataTable $table): JsonResponse
    {
        return $table->ajax();
    }

    public function create()
    {
        $tenant = new Tenant(['is_active' => true, 'currency_rate' => 1, 'timezone' => 'Africa/Cairo', 'locales' => ['ar', 'en'], 'default_locale' => 'ar']);

        return view('central.tenants.form', ['tenant' => $tenant, 'languages' => Language::active()->get()]);
    }

    public function store(TenantRequest $request): JsonResponse
    {
        $this->service->create(TenantData::fromRequest($request));

        return $this->ok(__('admin.saved'), route('central.tenants.index'));
    }

    public function edit(Tenant $tenant)
    {
        return view('central.tenants.form', ['tenant' => $tenant, 'languages' => Language::active()->get()]);
    }

    public function update(TenantRequest $request, Tenant $tenant): JsonResponse
    {
        $this->service->update($tenant, TenantData::fromRequest($request));

        return $this->ok(__('admin.saved'), route('central.tenants.index'));
    }

    /** Central admin only: master switch of the Growth system for a tenant (audited). */
    public function growth(\Illuminate\Http\Request $request, Tenant $tenant, GrowthAccess $access, ActionLogger $log): JsonResponse
    {
        $on = $request->boolean('growth_enabled');
        $access->set($tenant, $on);
        $prev = app()->bound('currentTenant') ? app('currentTenant') : null;
        app()->instance('currentTenant', $tenant);
        $log->log($on ? 'growth.enabled' : 'growth.disabled', 'admin', null, null, $tenant, 'ok', ['central_admin_id' => auth('central')->id()]);
        $prev ? app()->instance('currentTenant', $prev) : app()->forgetInstance('currentTenant');

        return $this->ok(__('admin.saved'), route('central.tenants.edit', $tenant));
    }

    public function destroy(Tenant $tenant): JsonResponse
    {
        $this->service->delete($tenant);

        return $this->ok(__('admin.deleted'));
    }

    public function validateForm(TenantRequest $request, ?Tenant $tenant = null): JsonResponse
    {
        return $this->ok();
    }
}
