<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminLoginRequest;
use App\Models\CentralAdmin;
use App\Models\TenantAdmin;
use App\Services\Auth\AuthSettings;
use App\Services\Auth\OtpRefused;
use App\Services\Auth\OtpService;
use App\Services\Whatsapp\WhatsappUnavailable;
use App\Support\AdminPhone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Central + tenant panel login: phone + password, plus a WhatsApp OTP for everyone except the (single) super admin.
 * The session is created only after the OTP is verified; the guard comes from the route-name prefix (central./tenant.).
 */
class AdminLoginController extends Controller
{
    use \App\Http\Controllers\Concerns\GuardsLogins;

    /** Valid bcrypt hash of a random unknown string: compared when the phone is unknown so timing does not reveal accounts. */
    private const DUMMY_HASH = '$2y$12$LPJAEj.6JtuurhwDwriVo.DBWKyYj/LtJfD1BgPB1Rc/rrwFjhiim';

    public function __construct(private OtpService $otp) {}

    private function guard(Request $r): string
    {
        return str_starts_with($r->route()->getName(), 'central.') ? 'central' : 'tenant';
    }

    private function tenantId(string $guard): ?int
    {
        return $guard === 'tenant' ? current_tenant()?->id : null;
    }

    private function route(string $guard, string $name): string
    {
        return route("$guard.$name");
    }

    public function show(Request $request)
    {
        $guard = $this->guard($request);

        return view('auth.admin-login', [
            'guard' => $guard, 'action' => $this->route($guard, 'login.submit'), 'validateUrl' => $this->route($guard, 'login.validate'),
            'countries' => AdminPhone::countries(), 'defaultCountry' => $guard === 'tenant' ? (current_tenant()?->country ?? 'EG') : 'EG',
        ]);
    }

    public function validateForm(AdminLoginRequest $request): JsonResponse
    {
        return $this->ok();
    }

    public function login(AdminLoginRequest $request): JsonResponse
    {
        $guard = $this->guard($request);
        $tenantId = $this->tenantId($guard);
        $settings = AuthSettings::for($tenantId);
        $digits = AdminPhone::digits($request->input('country'), $request->input('phone'));

        $this->throttle($guard, $digits ?? 'invalid:'.$request->ip(), $settings);

        $admin = $digits ? $this->query($guard)->where('phone', $digits)->where('is_active', true)->first() : null;
        // always hash-compare so an unknown phone costs the same as a wrong password (no enumeration by timing or message)
        $ok = Hash::check($request->input('password'), $admin?->password ?? self::DUMMY_HASH);
        if (! $admin || ! $ok) {
            $this->recordLoginFailure($guard, $digits ?? 'invalid:'.$request->ip());
            $this->audit($guard, 'login.failed', $admin, $digits);
            throw ValidationException::withMessages(['phone' => __('auth.failed')]);
        }
        $this->clearLoginFailures($guard, $digits);

        if (($guard === 'central' && $admin->isSuperAdmin()) || $admin->otp_exempt) { // tenant owners are NOT exempt by default; otp_exempt is set per admin
            return $this->finish($request, $guard, $admin, true, 'login.success');
        }

        try {
            $this->otp->issue($admin, $request->ip());
        } catch (OtpRefused $e) {
            // a still-valid code exists: let the user continue to the code screen instead of locking them out
            if ($e->reason !== 'cooldown') {
                throw ValidationException::withMessages(['phone' => __('whatsapp.otp_limit', ['seconds' => $e->retryAfter])]);
            }
        } catch (WhatsappUnavailable) {
            $this->audit($guard, 'otp.delivery_failed', $admin, $digits);
            throw ValidationException::withMessages(['phone' => __('whatsapp.otp_undeliverable')]);
        }

        $request->session()->regenerate();
        $request->session()->put('admin_otp', ['guard' => $guard, 'id' => $admin->getKey(), 'tenant' => $tenantId, 'remember' => true]);
        $this->audit($guard, 'otp.sent', $admin, $digits);

        return $this->ok(null, $this->route($guard, 'login.otp'));
    }

    public function otpForm(Request $request)
    {
        $guard = $this->guard($request);
        $admin = $this->pending($request, $guard);
        if (! $admin) {
            return redirect($this->route($guard, 'login'));
        }

        return view('auth.admin-otp', [
            'guard' => $guard, 'masked' => AdminPhone::mask($admin->phone), 'cooldown' => $this->otp->cooldown($admin),
            'length' => (int) AuthSettings::for($this->tenantId($guard))->get('otp.length'),
            'action' => $this->route($guard, 'login.otp.verify'), 'resend' => $this->route($guard, 'login.otp.resend'), 'back' => $this->route($guard, 'login'),
        ]);
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        $guard = $this->guard($request);
        $request->validate(['code' => ['required', 'string', 'regex:/^\d{4,10}$/']]);
        $admin = $this->pending($request, $guard);
        if (! $admin) {
            throw ValidationException::withMessages(['code' => __('whatsapp.otp_expired')]);
        }

        $result = $this->otp->verify($admin, $request->input('code'));
        if ($result !== 'ok') {
            $this->audit($guard, 'otp.'.$result, $admin, $admin->phone);
            if ($result === 'locked' || $result === 'expired') {
                $request->session()->forget('admin_otp');
            }
            throw ValidationException::withMessages(['code' => __("whatsapp.otp_$result")]);
        }
        $remember = (bool) $request->session()->pull('admin_otp')['remember'];

        return $this->finish($request, $guard, $admin, $remember, 'login.success_otp');
    }

    public function resendOtp(Request $request): JsonResponse
    {
        $guard = $this->guard($request);
        $admin = $this->pending($request, $guard);
        if (! $admin) {
            throw ValidationException::withMessages(['code' => __('whatsapp.otp_expired')]);
        }
        try {
            $this->otp->issue($admin, $request->ip());
        } catch (OtpRefused $e) {
            throw ValidationException::withMessages(['code' => __($e->reason === 'cooldown' ? 'whatsapp.otp_wait' : 'whatsapp.otp_limit', ['seconds' => $e->retryAfter])]);
        } catch (WhatsappUnavailable) {
            throw ValidationException::withMessages(['code' => __('whatsapp.otp_undeliverable')]);
        }
        $this->audit($guard, 'otp.resent', $admin, $admin->phone);

        return $this->ok(__('whatsapp.otp_resent'), null, ['cooldown' => $this->otp->cooldown($admin)]);
    }

    public function logout(Request $request)
    {
        $guard = $this->guard($request);
        $this->audit($guard, 'logout', auth($guard)->user(), null);
        $this->signOut($request, $guard);

        return redirect($this->route($guard, 'login'));
    }

    private function finish(Request $request, string $guard, CentralAdmin|TenantAdmin $admin, bool $remember, string $event): JsonResponse
    {
        Auth::guard($guard)->login($admin, true); // admin logins never expire (until logout / deactivation)
        $request->session()->regenerate();
        $this->audit($guard, $event, $admin, $admin->phone);

        $home = route("$guard.dashboard");

        return $this->ok(__('admin.welcome_back'), redirect()->intended($home)->getTargetUrl());
    }

    /** The admin that passed the password step in THIS session, for THIS guard and tenant; anything else is ignored. */
    private function pending(Request $request, string $guard): CentralAdmin|TenantAdmin|null
    {
        $p = $request->session()->get('admin_otp');
        if (! is_array($p) || ($p['guard'] ?? null) !== $guard || ($p['tenant'] ?? null) !== $this->tenantId($guard)) {
            return null;
        }

        return $this->query($guard)->whereKey($p['id'])->where('is_active', true)->first();
    }

    private function query(string $guard)
    {
        return $guard === 'central' ? CentralAdmin::query() : TenantAdmin::query(); // TenantAdmin carries the tenant global scope
    }

    /** Failed-password lockout per phone (+ per IP via the route's throttle middleware), limits from the DB. */
    private function throttle(string $guard, string $key, AuthSettings $s): void
    {
        $this->maxLoginAttempts = (int) $s->get('login.max_failures');
        $this->lockoutSeconds = (int) $s->get('login.lockout_seconds');
        $k = $this->lockoutKey($guard, $key);
        if (RateLimiter::tooManyAttempts($k, $this->maxLoginAttempts)) {
            throw ValidationException::withMessages(['phone' => __('auth.throttle', ['seconds' => RateLimiter::availableIn($k), 'minutes' => (int) ceil(RateLimiter::availableIn($k) / 60)])]);
        }
    }

    private function audit(string $guard, string $event, $admin, ?string $phone): void
    {
        // never log passwords or OTP codes; the phone is masked
        $log = activity('auth')->event($event);
        if ($admin) {
            $log->performedOn($admin)->causedBy($admin);
        }
        $log->withProperties(['guard' => $guard, 'phone' => $phone ? AdminPhone::mask($phone) : null, 'ip' => request()->ip()])
            ->tap(fn ($a) => $a->tenant_id = $guard === 'tenant' ? current_tenant()?->id : null)->log($event);
    }
}
