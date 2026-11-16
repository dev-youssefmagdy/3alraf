<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Concerns\GuardsLogins;
use App\Http\Controllers\Controller;
use App\Http\Requests\AdminLoginRequest;
use App\Models\Admin;
use App\Models\Marketer;
use App\Services\Auth\AuthSettings;
use App\Services\Auth\OtpRefused;
use App\Services\Auth\OtpService;
use App\Services\Whatsapp\WhatsappUnavailable;
use App\Support\AdminPhone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Staff panel login, shared by /admin (AdminLoginController, guard `admin`) and /marketer (MarketerLoginController, guard `marketer`):
 * phone + password, then a WhatsApp OTP for every account without `otp_exempt`. The session is created only after the OTP is verified.
 * Each panel has its own guard, table, routes, session key and lockout bucket, so one panel's login never opens the other.
 */
abstract class PanelLoginController extends Controller
{
    use GuardsLogins;

    /** Valid bcrypt hash of a random unknown string: compared when the phone is unknown so timing does not reveal accounts. */
    private const DUMMY_HASH = '$2y$12$LPJAEj.6JtuurhwDwriVo.DBWKyYj/LtJfD1BgPB1Rc/rrwFjhiim';

    public function __construct(protected OtpService $otp) {}

    /** Auth guard of the panel (`admin` | `marketer`). */
    abstract protected function guard(): string;

    /** Eloquent query of the panel's accounts. */
    abstract protected function query(): Builder;

    /** Route-name prefix of the panel (`admin.` | `marketer.`). */
    abstract protected function routePrefix(): string;

    /** Route the user lands on after logging in. */
    abstract protected function homeRoute(): string;

    /** Label shown above the login card. */
    abstract protected function panelLabel(): string;

    protected function route(string $name): string
    {
        return route($this->routePrefix().$name);
    }

    private function sessionKey(): string
    {
        return $this->guard().'_otp';
    }

    public function show(Request $request)
    {
        return view('auth.admin-login', [
            'guard' => $this->guard(), 'panelLabel' => $this->panelLabel(),
            'action' => $this->route('login.submit'), 'validateUrl' => $this->route('login.validate'),
            'countries' => AdminPhone::countries(), 'defaultCountry' => strtoupper((string) setting('country', 'EG')) ?: 'EG',
        ]);
    }

    public function validateForm(AdminLoginRequest $request): JsonResponse
    {
        return $this->ok();
    }

    public function login(AdminLoginRequest $request): JsonResponse
    {
        $guard = $this->guard();
        $settings = AuthSettings::for();
        $digits = AdminPhone::digits($request->input('country'), $request->input('phone'));

        $this->throttle($digits ?? 'invalid:'.$request->ip(), $settings);

        $user = $digits ? $this->query()->where('phone', $digits)->where('is_active', true)->first() : null;
        // always hash-compare so an unknown phone costs the same as a wrong password (no enumeration by timing or message)
        $ok = Hash::check($request->input('password'), $user?->password ?? self::DUMMY_HASH);
        if (! $user || ! $ok) {
            $this->recordLoginFailure($guard, $digits ?? 'invalid:'.$request->ip());
            $this->audit('login.failed', $user, $digits);
            throw ValidationException::withMessages(['phone' => __('auth.failed')]);
        }
        $this->clearLoginFailures($guard, $digits);

        if ($user->otp_exempt) { // set per account (OTP bypass flag on the row)
            return $this->finish($request, $user, true, 'login.success');
        }

        try {
            $this->otp->issue($user, $request->ip());
        } catch (OtpRefused $e) {
            // a still-valid code exists: let the user continue to the code screen instead of locking them out
            if ($e->reason !== 'cooldown') {
                throw ValidationException::withMessages(['phone' => __('whatsapp.otp_limit', ['seconds' => $e->retryAfter])]);
            }
        } catch (WhatsappUnavailable) {
            $this->audit('otp.delivery_failed', $user, $digits);
            throw ValidationException::withMessages(['phone' => __('whatsapp.otp_undeliverable')]);
        }

        $request->session()->regenerate();
        $request->session()->put($this->sessionKey(), ['guard' => $guard, 'id' => $user->getKey(), 'remember' => true]);
        $this->audit('otp.sent', $user, $digits);

        return $this->ok(null, $this->route('login.otp'));
    }

    public function otpForm(Request $request)
    {
        $user = $this->pending($request);
        if (! $user) {
            return redirect($this->route('login'));
        }

        return view('auth.admin-otp', [
            'guard' => $this->guard(), 'panelLabel' => $this->panelLabel(), 'masked' => AdminPhone::mask($user->phone), 'cooldown' => $this->otp->cooldown($user),
            'length' => (int) AuthSettings::for()->get('otp.length'),
            'action' => $this->route('login.otp.verify'), 'resend' => $this->route('login.otp.resend'), 'back' => $this->route('login'),
        ]);
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        $request->validate(['code' => ['required', 'string', 'regex:/^\d{4,10}$/']]);
        $user = $this->pending($request);
        if (! $user) {
            throw ValidationException::withMessages(['code' => __('whatsapp.otp_expired')]);
        }

        $result = $this->otp->verify($user, $request->input('code'));
        if ($result !== 'ok') {
            $this->audit('otp.'.$result, $user, $user->phone);
            if ($result === 'locked' || $result === 'expired') {
                $request->session()->forget($this->sessionKey());
            }
            throw ValidationException::withMessages(['code' => __("whatsapp.otp_$result")]);
        }
        $remember = (bool) $request->session()->pull($this->sessionKey())['remember'];

        return $this->finish($request, $user, $remember, 'login.success_otp');
    }

    public function resendOtp(Request $request): JsonResponse
    {
        $user = $this->pending($request);
        if (! $user) {
            throw ValidationException::withMessages(['code' => __('whatsapp.otp_expired')]);
        }
        try {
            $this->otp->issue($user, $request->ip());
        } catch (OtpRefused $e) {
            throw ValidationException::withMessages(['code' => __($e->reason === 'cooldown' ? 'whatsapp.otp_wait' : 'whatsapp.otp_limit', ['seconds' => $e->retryAfter])]);
        } catch (WhatsappUnavailable) {
            throw ValidationException::withMessages(['code' => __('whatsapp.otp_undeliverable')]);
        }
        $this->audit('otp.resent', $user, $user->phone);

        return $this->ok(__('whatsapp.otp_resent'), null, ['cooldown' => $this->otp->cooldown($user)]);
    }

    public function logout(Request $request)
    {
        $this->audit('logout', auth($this->guard())->user(), null);
        $this->signOut($request, $this->guard());

        return redirect($this->route('login'));
    }

    private function finish(Request $request, Admin|Marketer $user, bool $remember, string $event): JsonResponse
    {
        Auth::guard($this->guard())->login($user, true); // panel logins never expire (until logout / deactivation)
        $request->session()->regenerate();
        if ($user instanceof Marketer) {
            $user->forceFill(['last_login_at' => now()])->saveQuietly();
        }
        $this->audit($event, $user, $user->phone);

        $request->session()->forget('url.intended'); // always land on the panel home after login

        return $this->ok(__('admin.welcome_back'), route($this->homeRoute()));
    }

    /** The account that passed the password step in THIS session on THIS panel; anything else is ignored. */
    private function pending(Request $request): Admin|Marketer|null
    {
        $p = $request->session()->get($this->sessionKey());
        if (! is_array($p) || ($p['guard'] ?? null) !== $this->guard()) {
            return null;
        }

        return $this->query()->whereKey($p['id'])->where('is_active', true)->first();
    }

    /** Failed-password lockout per phone (+ per IP via the route's throttle middleware), limits from the DB. */
    private function throttle(string $key, AuthSettings $s): void
    {
        $this->maxLoginAttempts = (int) $s->get('login.max_failures');
        $this->lockoutSeconds = (int) $s->get('login.lockout_seconds');
        $k = $this->lockoutKey($this->guard(), $key);
        if (RateLimiter::tooManyAttempts($k, $this->maxLoginAttempts)) {
            throw ValidationException::withMessages(['phone' => __('auth.throttle', ['seconds' => RateLimiter::availableIn($k), 'minutes' => (int) ceil(RateLimiter::availableIn($k) / 60)])]);
        }
    }

    private function audit(string $event, $user, ?string $phone): void
    {
        // never log passwords or OTP codes; the phone is masked
        $log = activity('auth')->event($event);
        if ($user) {
            $log->performedOn($user)->causedBy($user);
        }
        $log->withProperties(['guard' => $this->guard(), 'phone' => $phone ? AdminPhone::mask($phone) : null, 'ip' => request()->ip()])->log($event);
    }
}
