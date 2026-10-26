<?php

namespace App\Http\Controllers\Central\Ai;

use App\DataTables\AiTenantDataTable;
use App\DTOs\Ai\AiTenantAiData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Ai\AiTenantRequest;
use App\Models\AiModel;
use App\Models\Tenant;
use App\Services\Ai\AiGateway;
use App\Services\Ai\AiTenantSettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class TenantAiController extends Controller implements HasMiddleware
{
    public function __construct(private AiTenantSettingService $service) {}

    public static function middleware(): array
    {
        return [new Middleware('can:ai_tenants.view', only: ['index', 'datatable']), new Middleware('can:ai_tenants.edit', only: ['edit', 'update', 'validateForm'])];
    }

    public function index()
    {
        return view('central.ai.tenants.index', ['table' => new AiTenantDataTable]);
    }

    public function datatable(AiTenantDataTable $table): JsonResponse
    {
        return $table->ajax();
    }

    public function edit(Tenant $tenant)
    {
        return view('central.ai.tenants.form', ['tenant' => $tenant, 'setting' => $this->service->get($tenant), 'models' => AiModel::with('provider')->where('is_active', true)->orderBy('kind')->orderBy('label')->get(),
            'usage' => app(AiGateway::class)->usage($tenant->id)]);
    }

    public function update(AiTenantRequest $request, Tenant $tenant): JsonResponse
    {
        $this->service->updatePlatform($tenant, AiTenantAiData::fromRequest($request));

        return $this->ok(__('admin.saved'), route('central.ai.tenants.index'));
    }

    public function validateForm(AiTenantRequest $request, ?Tenant $tenant = null): JsonResponse
    {
        return $this->ok();
    }
}
