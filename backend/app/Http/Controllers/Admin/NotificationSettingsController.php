<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\NotificationSettingsRequest;
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
        return view('admin.settings.notifications', ['flags' => $this->service->get()]);
    }

    public function update(NotificationSettingsRequest $request): JsonResponse
    {
        $this->service->update($request->validated());

        return $this->ok(__('admin.saved'), route('admin.notification-settings.edit'));
    }
}
