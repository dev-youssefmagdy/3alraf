<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Concerns\GuardsLogins;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/** One login controller for the three guards; guard comes from the route name prefix (central./tenant./storefront.). */
class LoginController extends Controller
{
    use GuardsLogins;

    private function guard(Request $r): string
    {
        return match (explode('.', $r->route()->getName())[0]) {
            'central' => 'central',
            'tenant' => 'tenant',
            default => 'web',
        };
    }

    public function show(Request $request)
    {
        $guard = $this->guard($request);

        return view('auth.login', [
            'guard' => $guard,
            'action' => route($this->routePrefix($guard).'login.submit'),
            'validateUrl' => route($this->routePrefix($guard).'login.validate'),
        ]);
    }

    public function validateForm(LoginRequest $request): JsonResponse
    {
        return $this->ok();
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $guard = $this->guard($request);
        $creds = $request->only('email', 'password') + ['is_active' => true];

        $this->assertNotLockedOut($guard, $creds['email']);
        if (! Auth::guard($guard)->attempt($creds, $request->boolean('remember'))) {
            $this->recordLoginFailure($guard, $creds['email']);
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }
        $this->clearLoginFailures($guard, $creds['email']);
        $request->session()->regenerate();

        return $this->ok(__('admin.welcome_back'), redirect()->intended($this->home($guard))->getTargetUrl());
    }

    public function logout(Request $request)
    {
        $guard = $this->guard($request);
        $this->signOut($request, $guard);

        return redirect()->route($this->routePrefix($guard).'login');
    }

    private function routePrefix(string $guard): string
    {
        return ['central' => 'central.', 'tenant' => 'tenant.', 'web' => 'storefront.'][$guard];
    }

    private function home(string $guard): string
    {
        return match ($guard) {
            'central' => route('central.dashboard'),
            'tenant' => route('tenant.dashboard'),
            default => route('storefront.home'),
        };
    }
}
