<?php

namespace App\Http\Controllers\Admin;

use App\DTOs\GeneralSettingsData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GeneralSettingsRequest;
use App\Models\Language;
use App\Services\GeneralSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/** Settings → General (shop profile + growth toggle + AI master switch). Reachable while AI / Growth are off. */
class GeneralSettingsController extends Controller implements HasMiddleware
{
    public function __construct(private GeneralSettingsService $service) {}

    public static function middleware(): array
    {
        return [
            new Middleware('can:general_settings.view', only: ['edit']),
            new Middleware('can:general_settings.edit', only: ['update', 'validateForm']),
        ];
    }

    public function edit()
    {
        return view('admin.settings.general', ['s' => $this->service->current(), 'languages' => Language::active()->get()]);
    }

    public function validateForm(GeneralSettingsRequest $request): JsonResponse
    {
        return $this->ok();
    }

    public function update(GeneralSettingsRequest $request): JsonResponse
    {
        $this->service->update(GeneralSettingsData::fromRequest($request), auth('admin')->id());

        return $this->ok(__('admin.saved'), route('admin.general-settings.edit'));
    }
}
