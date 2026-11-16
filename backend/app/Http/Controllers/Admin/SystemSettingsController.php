<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SystemSettingsRequest;
use App\Services\SystemSettingsService;
use App\Support\SystemSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/** Settings → System: the former .env keys (URLs, media, mail, Google, WhatsApp, Reverb, Meta) + a test e-mail. */
class SystemSettingsController extends Controller implements HasMiddleware
{
    public function __construct(private SystemSettingsService $service) {}

    public static function middleware(): array
    {
        return [
            new Middleware('can:general_settings.view', only: ['edit']),
            new Middleware('can:general_settings.edit', only: ['update', 'validateForm', 'testMail']),
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

    /** Sends a plain test e-mail with the SAVED mail settings (save first), so the owner sees the real SMTP error if any. */
    public function testMail(Request $request): JsonResponse
    {
        $data = $request->validate(['to' => ['required', 'email', 'max:255']]);
        try {
            Mail::raw(__('admin.system_settings.mail_test_body', ['app' => setting('name') ?: config('app.name')]), function ($m) use ($data) {
                $m->to($data['to'])->subject(__('admin.system_settings.mail_test_subject'));
            });
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => __('admin.system_settings.mail_test_failed', ['error' => mb_substr($e->getMessage(), 0, 300)])], 422);
        }
        activity('settings')->causedBy(auth('admin')->user())->withProperties(['mailer' => config('mail.default')])->log('system_settings.mail_test');

        return $this->ok(config('mail.default') === 'log' ? __('admin.system_settings.mail_test_logged') : __('admin.system_settings.mail_test_sent', ['to' => $data['to']]));
    }

    public function update(SystemSettingsRequest $request): JsonResponse
    {
        $this->service->update($request->validated(), auth('admin')->id());

        return $this->ok(__('admin.saved'), route('admin.system-settings.edit'));
    }
}
