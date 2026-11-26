<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SystemSettingsRequest;
use App\Services\Llm\FreeLlmClient;
use App\Services\Llm\LlmUnavailableException;
use App\Services\SystemSettingsService;
use App\Support\SystemSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Mail;

/** Settings → System: the former .env keys (URLs, media, mail, Google, WhatsApp, Reverb, Meta, AI) + a test e-mail and an AI gateway test. */
class SystemSettingsController extends Controller implements HasMiddleware
{
    public function __construct(private SystemSettingsService $service) {}

    public static function middleware(): array
    {
        return [
            new Middleware('can:general_settings.view', only: ['edit']),
            new Middleware('can:general_settings.edit', only: ['update', 'validateForm', 'testMail', 'testAi']),
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

    /** Checks the SAVED AI settings: the gateway answers, the key is valid, and the text model chain gives an answer. */
    public function testAi(FreeLlmClient $llm): JsonResponse
    {
        try {
            $models = $llm->availableModels();
            $answer = $llm->chat([['role' => 'user', 'content' => 'Reply with the single word OK.']], 'text', ['max_tokens' => 10, 'budget' => 30]);
        } catch (LlmUnavailableException $e) {
            $reason = $e->getMessage() === 'key_rejected' ? 'key rejected' : $e->userMessage();

            return response()->json(['success' => false, 'message' => __('admin.system_settings.ai_test_failed', ['error' => $reason]), 'code' => $e->errorCode], 422);
        }
        activity('settings')->causedBy(auth('admin')->user())->withProperties(['answered_by' => $answer->answeredBy()])->log('system_settings.ai_test');

        return $this->ok(__('admin.system_settings.ai_test_ok', ['count' => count($models), 'model' => $answer->answeredBy()]));
    }

    public function update(SystemSettingsRequest $request): JsonResponse
    {
        $this->service->update($request->validated(), auth('admin')->id());

        return $this->ok(__('admin.saved'), route('admin.system-settings.edit'));
    }
}
