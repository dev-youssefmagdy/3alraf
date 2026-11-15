<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SystemSettingsRequest;
use App\Services\SystemSettingsService;
use App\Support\SystemSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/** Settings → System: the former .env keys (URLs, media, Google, WhatsApp, Reverb, Meta). */
class SystemSettingsController extends Controller implements HasMiddleware
{
    public function __construct(private SystemSettingsService $service) {}

    public static function middleware(): array
    {
        return [
            new Middleware('can:general_settings.view', only: ['edit']),
            new Middleware('can:general_settings.edit', only: ['update', 'validateForm']),
        ];
    }

    public function edit()
    {
        $secretSet = collect(SystemSettings::flat())->filter(fn ($d) => $d[0] === 'secret')->map(fn ($d, $k) => SystemSettings::isSecretSet($k));

        return view('admin.settings.system', ['schema' => SystemSettings::schema(), 'values' => SystemSettings::current(), 'secretSet' => $secretSet]);
    }

    public function validateForm(SystemSettingsRequest $request): JsonResponse
    {
        return $this->ok();
    }

    public function update(SystemSettingsRequest $request): JsonResponse
    {
        $this->service->update($request->validated(), auth('admin')->id());

        return $this->ok(__('admin.saved'), route('admin.system-settings.edit'));
    }
}
