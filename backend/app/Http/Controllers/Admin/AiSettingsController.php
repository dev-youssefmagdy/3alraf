<?php

namespace App\Http\Controllers\Admin;

use App\DTOs\Ai\AiPlatformData;
use App\DTOs\Ai\AiPreferencesData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Ai\AiPlatformRequest;
use App\Http\Requests\Admin\AiSettingsRequest;
use App\Models\AiModel;
use App\Services\Ai\AiAccess;
use App\Services\Ai\AiGateway;
use App\Services\Ai\AiSettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/** AI settings: shop preferences + platform card (quotas / allowed models / features, permission ai_platform.edit). */
class AiSettingsController extends Controller implements HasMiddleware
{
    public function __construct(private AiSettingService $service, private AiAccess $access) {}

    public static function middleware(): array
    {
        return [
            new Middleware('can:ai_settings.view', only: ['edit']),
            new Middleware('can:ai_settings.edit', only: ['update', 'validateForm']),
            new Middleware('can:ai_platform.edit', only: ['updatePlatform', 'validatePlatform']),
        ];
    }

    public function edit()
    {
        return view('admin.ai.settings', [
            'setting' => $this->service->get(), 'models' => $this->access->modelsFor('text'), 'usage' => app(AiGateway::class)->usage(),
            'allModels' => auth('admin')->user()?->can('ai_platform.edit') ? AiModel::with('provider')->where('is_active', true)->orderBy('kind')->orderBy('label')->get() : collect(),
        ]);
    }

    public function update(AiSettingsRequest $request): JsonResponse
    {
        $this->service->updatePreferences(AiPreferencesData::fromRequest($request));

        return $this->ok(__('admin.saved'), route('admin.ai.settings.edit'));
    }

    public function validateForm(AiSettingsRequest $request): JsonResponse
    {
        return $this->ok();
    }

    public function updatePlatform(AiPlatformRequest $request): JsonResponse
    {
        $this->service->updatePlatform(AiPlatformData::fromRequest($request));

        return $this->ok(__('admin.saved'), route('admin.ai.settings.edit'));
    }

    public function validatePlatform(AiPlatformRequest $request): JsonResponse
    {
        return $this->ok();
    }
}
