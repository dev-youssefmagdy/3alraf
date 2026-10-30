<?php

namespace App\Http\Controllers\Whatsapp;

use App\Events\Whatsapp\SessionUpdated;
use App\Http\Controllers\Controller;
use App\Models\WhatsappSession;
use App\Services\Auth\AuthSettings;
use App\Services\Whatsapp\WhatsappService;
use App\Services\Whatsapp\WhatsappUnavailable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * WhatsApp connection management for BOTH panels. The owner is always taken from the authenticated panel
 * (central, or the current tenant), never from request input, so one tenant can never reach another's session.
 */
class ConnectionController extends Controller implements HasMiddleware
{
    public function __construct(private WhatsappService $wa) {}

    public static function middleware(): array
    {
        return [
            new Middleware('can:whatsapp.view', only: ['index', 'status']),
            new Middleware('can:whatsapp.manage', except: ['index', 'status']),
        ];
    }

    private function guard(Request $r): string
    {
        return str_starts_with($r->route()->getName(), 'central.') ? 'central' : 'tenant';
    }

    private function owner(Request $r): ?int
    {
        return $this->guard($r) === 'tenant' ? current_tenant()->id : null;
    }

    private function payload(WhatsappSession $s): array
    {
        return [
            'status' => $s->status, 'phone' => $s->phone, 'error' => $s->last_error, 'enabled' => $s->is_enabled,
            'last_connected_at' => $s->last_connected_at?->toDayDateTimeString(), 'last_event_at' => $s->last_event_at?->diffForHumans(),
        ];
    }

    public function index(Request $request)
    {
        $guard = $this->guard($request);
        $owner = $this->owner($request);
        $s = $this->wa->refresh($owner);

        return view('whatsapp.connection', [
            'guard' => $guard, 'session' => $s, 'logs' => $s->logs()->latest('id')->limit(30)->get(),
            'settings' => AuthSettings::for($owner)->all(), 'channel' => SessionUpdated::channelName($owner),
        ]);
    }

    public function status(Request $request): JsonResponse
    {
        return response()->json($this->payload($this->wa->refresh($this->owner($request))));
    }

    public function connect(Request $request): JsonResponse
    {
        return $this->run($request, fn ($o) => $this->wa->connect($o), 'whatsapp.connecting');
    }

    public function reconnect(Request $request): JsonResponse
    {
        return $this->run($request, fn ($o) => $this->wa->reconnect($o), 'whatsapp.connecting');
    }

    public function disconnect(Request $request): JsonResponse
    {
        return $this->run($request, fn ($o) => $this->wa->disconnect($o), 'whatsapp.disconnected');
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $v = $request->validate([
            'whatsapp_enabled' => ['boolean'],
            'otp_length' => ['required', 'integer', 'between:4,10'],
            'otp_ttl_seconds' => ['required', 'integer', 'between:60,1800'],
            'otp_resend_cooldown_seconds' => ['required', 'integer', 'between:10,600'],
            'otp_max_attempts' => ['required', 'integer', 'between:1,10'],
            'otp_max_resends' => ['required', 'integer', 'between:0,10'],
            'otp_message' => ['required', 'string', 'max:500', 'regex:/\{code\}/'],
            'login_max_failures' => ['required', 'integer', 'between:3,20'],
            'login_lockout_seconds' => ['required', 'integer', 'between:60,86400'],
        ], ['otp_message.regex' => __('whatsapp.message_needs_code')], trans('whatsapp.fields'));

        $s = AuthSettings::for($this->owner($request));
        foreach ([
            'whatsapp.enabled' => (bool) ($v['whatsapp_enabled'] ?? false), 'otp.length' => (int) $v['otp_length'], 'otp.ttl_seconds' => (int) $v['otp_ttl_seconds'],
            'otp.resend_cooldown_seconds' => (int) $v['otp_resend_cooldown_seconds'], 'otp.max_attempts' => (int) $v['otp_max_attempts'],
            'otp.max_resends' => (int) $v['otp_max_resends'], 'otp.message' => $v['otp_message'],
            'login.max_failures' => (int) $v['login_max_failures'], 'login.lockout_seconds' => (int) $v['login_lockout_seconds'],
        ] as $k => $val) {
            $s->set($k, $val);
        }

        return $this->ok(__('admin.saved'));
    }

    private function run(Request $request, \Closure $fn, string $messageKey): JsonResponse
    {
        try {
            $s = $fn($this->owner($request));
        } catch (WhatsappUnavailable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return $this->ok(__($messageKey), null, $this->payload($s));
    }
}
