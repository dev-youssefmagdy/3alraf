<?php

namespace App\Http\Controllers\Tenant;

use App\DTOs\Ai\AiPreferencesData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\AiSettingsRequest;
use App\Services\Ai\AiAccess;
use App\Services\Ai\AiGateway;
use App\Services\Ai\AiTenantSettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class AiSettingsController extends Controller implements HasMiddleware
{
    public function __construct(private AiTenantSettingService $service, private AiAccess $access) {}

    public static function middleware(): array
    {
        return [new Middleware('can:ai_settings.view', only: ['edit']), new Middleware('can:ai_settings.edit', only: ['update', 'validateForm'])];
    }

    public function edit()
    {
        $tenant = current_tenant();

        return view('tenant.ai.settings', ['setting' => $this->service->get($tenant), 'models' => $this->access->modelsFor($tenant, 'text'),
            'usage' => app(AiGateway::class)->usage($tenant->id)]);
    }

    public function update(AiSettingsRequest $request): JsonResponse
    {
        $this->service->updatePreferences(current_tenant(), AiPreferencesData::fromRequest($request));

        return $this->ok(__('admin.saved'), route('tenant.ai.settings.edit'));
    }

    public function validateForm(AiSettingsRequest $request): JsonResponse
    {
        return $this->ok();
    }
}
