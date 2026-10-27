<?php

namespace App\Http\Controllers\Tenant\Growth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\GrowthSettingsRequest;
use App\Services\Growth\ActionLogger;
use App\Services\Growth\GrowthConfig;
use App\Services\Growth\GrowthFeatures;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class SettingsController extends Controller implements HasMiddleware
{
    public function __construct(private GrowthConfig $config, private ActionLogger $log, private GrowthFeatures $features) {}

    public static function middleware(): array
    {
        return [new Middleware('can:growth.view', only: ['edit']), new Middleware('can:growth.manage', only: ['update', 'updateFeatures'])];
    }

    public function edit()
    {
        return view('tenant.growth.settings', ['registry' => $this->features->registry(), 'switched' => collect($this->features->registry())->map(fn ($f, $k) => $this->features->switchedOn($k))->all(), 'active' => $this->features->states(), 'cfg' => $this->config->all(), 'overrides' => $this->config->overrides(), 'defaults' => config('growth.guardrails'), 'limits' => GrowthConfig::OVERRIDABLE]);
    }

    public function updateFeatures(\Illuminate\Http\Request $r): JsonResponse
    {
        $in = $r->validate(['features' => ['nullable', 'array'], 'features.*' => ['boolean']])['features'] ?? [];
        $switches = collect($this->features->registry())->map(fn ($f, $k) => (bool) ($in[$k] ?? false))->all();
        $this->features->update(current_tenant(), $switches, auth('tenant')->id());

        return $this->ok(__('admin.saved'), route('tenant.growth.settings.edit'));
    }

    public function update(GrowthSettingsRequest $r): JsonResponse
    {
        $cfg = $this->config->update(current_tenant(), $r->validated());
        $this->log->log('settings.updated', 'admin', auth('tenant')->id(), null, current_tenant(), 'ok', ['config' => $cfg]);

        return $this->ok(__('admin.saved'), route('tenant.growth.settings.edit'));
    }
}
