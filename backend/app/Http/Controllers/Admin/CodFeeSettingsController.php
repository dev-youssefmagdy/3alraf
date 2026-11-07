<?php

namespace App\Http\Controllers\Tenant;

use App\DTOs\Orders\CodFeeConfig;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\CodFeeSettingsRequest;
use App\Models\City;
use App\Models\Language;
use App\Services\CodFeeSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class CodFeeSettingsController extends Controller implements HasMiddleware
{
    public function __construct(private CodFeeSettingsService $service) {}

    public static function middleware(): array
    {
        return [new Middleware('can:settings.view', only: ['edit']), new Middleware('can:settings.edit', only: ['update', 'validateForm'])];
    }

    public function edit()
    {
        $cfg = $this->service->get(current_tenant());

        return view('tenant.settings.cod-fees', ['cfg' => $cfg, 'cities' => City::orderBy('sort_order')->get(), 'languages' => Language::activeList()]);
    }

    public function update(CodFeeSettingsRequest $request): JsonResponse
    {
        $this->service->update(current_tenant(), CodFeeConfig::fromRequest($request));

        return $this->ok(__('admin.saved'), route('tenant.cod-fees.edit'));
    }

    public function validateForm(CodFeeSettingsRequest $request): JsonResponse
    {
        return $this->ok();
    }
}
