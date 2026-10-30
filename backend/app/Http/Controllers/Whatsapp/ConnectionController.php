<?php

namespace App\Http\Controllers\Whatsapp;

use App\Events\Whatsapp\SessionUpdated;
use App\Http\Controllers\Controller;
use App\Models\WhatsappSession;
use App\Services\Auth\AuthSettings;
use App\Services\Whatsapp\WhatsappService;
use App\Services\Whatsapp\WhatsappUnavailable;
use App\Support\AdminPhone;
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
            'status' => $s->status, 'qr' => $this->wa->qr($s), 'phone' => $s->phone, 'error' => $s->last_error, 'enabled' => $s->is_enabled,
            'last_connected_at' => $s->last_connected_at?->toDayDateTimeString(), 'last_event_at' => $s->last_event_at?->diffForHumans(),
        ];
    }

    public function index(Request $request)
    {
        $guard = $this->guard($request);
        $owner = $this->owner($request);
        $s = $this->wa->refresh($owner);

        return view('whatsapp.connection', [
            'guard' => $guard, 'session' => $s,
            'settings' => AuthSettings::for($owner)->all(),
            'sentToday' => $this->wa->sent($s, 'daily'), 'sentHour' => $this->wa->sent($s, 'hourly'), 'channel' => SessionUpdated::channelName($owner),
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

    public function sendTest(Request $request): JsonResponse
    {
        $v = $request->validate(['country' => ['required', 'string', 'size:2'], 'phone' => ['required', 'string', 'max:30']], [], trans('whatsapp.fields'));
        $digits = AdminPhone::digits($v['country'], $v['phone']);
        if (! $digits) {
            return response()->json(['success' => false, 'message' => __('whatsapp.invalid_phone')], 422);
        }

        return $this->run($request, function ($owner) use ($digits) {
            $this->wa->sendText($owner, $digits, __('whatsapp.test_message'), throttled: true);

            return $this->wa->session($owner);
        }, 'whatsapp.test_sent');
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $v = $request->validate([
            'whatsapp_enabled' => ['boolean'],
            'daily_limit' => ['required', 'integer', 'between:0,100000'],
            'hourly_limit' => ['required', 'integer', 'between:0,10000'],
            'min_gap' => ['required', 'integer', 'between:0,60'],
            'max_gap' => ['required', 'integer', 'between:0,120', 'gte:min_gap'],
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
            'whatsapp.enabled' => (bool) ($v['whatsapp_enabled'] ?? false), 'whatsapp.daily_limit' => (int) $v['daily_limit'],
            'whatsapp.hourly_limit' => (int) $v['hourly_limit'], 'whatsapp.min_gap' => (int) $v['min_gap'], 'whatsapp.max_gap' => (int) $v['max_gap'], 'otp.length' => (int) $v['otp_length'], 'otp.ttl_seconds' => (int) $v['otp_ttl_seconds'],
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
