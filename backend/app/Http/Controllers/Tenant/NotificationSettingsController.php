<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\NotificationSettingsRequest;
use App\Services\NotificationSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class NotificationSettingsController extends Controller implements HasMiddleware
{
    public function __construct(private NotificationSettingsService $service) {}

    public static function middleware(): array
    {
        return [new Middleware('can:settings.view', only: ['edit']), new Middleware('can:settings.edit', only: ['update'])];
    }

    public function edit()
    {
        return view('tenant.settings.notifications', ['flags' => $this->service->get(current_tenant())]);
    }

    public function update(NotificationSettingsRequest $request): JsonResponse
    {
        $this->service->update(current_tenant(), $request->validated());

        return $this->ok(__('admin.saved'), route('tenant.notification-settings.edit'));
    }
}
