<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/** Per-email login lockout and a logout that really ends the session when no guard is left signed in. */
trait GuardsLogins
{
    protected int $maxLoginAttempts = 5;

    protected int $lockoutSeconds = 900;

    protected function lockoutKey(string $guard, string $email): string
    {
        return 'login-lock:'.(current_tenant()?->id ?? 'central').':'.$guard.':'.mb_strtolower(trim($email));
    }

    protected function assertNotLockedOut(string $guard, string $email): void
    {
        $key = $this->lockoutKey($guard, $email);
        if (RateLimiter::tooManyAttempts($key, $this->maxLoginAttempts)) {
            throw ValidationException::withMessages(['email' => __('auth.throttle', ['seconds' => RateLimiter::availableIn($key), 'minutes' => (int) ceil(RateLimiter::availableIn($key) / 60)])]);
        }
    }

    protected function recordLoginFailure(string $guard, string $email): void
    {
        RateLimiter::hit($this->lockoutKey($guard, $email), $this->lockoutSeconds);
    }

    protected function clearLoginFailures(string $guard, string $email): void
    {
        RateLimiter::clear($this->lockoutKey($guard, $email));
    }

    /** Sign out one guard; invalidate the whole session when no other guard remains authenticated. */
    protected function signOut(Request $request, string $guard): void
    {
        Auth::guard($guard)->logout();

        $others = collect(['web', 'tenant', 'central', 'affiliate'])->reject(fn ($g) => $g === $guard)->contains(fn ($g) => Auth::guard($g)->check());
        if ($others) {
            $request->session()->regenerateToken();
        } else {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }
    }
}
