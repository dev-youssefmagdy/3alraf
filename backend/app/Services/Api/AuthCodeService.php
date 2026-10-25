<?php

namespace App\Services\Api;

use App\Models\Customer;
use App\Notifications\AuthCodeNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/** One-time 6-digit codes (hashed, 60 min, max 5 wrong tries) for password reset and e-mail verification of mobile customers. */
class AuthCodeService
{
    private const MINUTES = 60;

    private const MAX_ATTEMPTS = 5;

    /** Per e-mail + type: one code per COOLDOWN seconds and at most MAX_ISSUES codes per hour. */
    public const COOLDOWN = 60;

    public const MAX_ISSUES = 5;

    /** Always silent about whether the e-mail exists. */
    public function sendReset(string $email): void
    {
        if ($c = Customer::where('email', strtolower(trim($email)))->where('is_active', true)->first()) {
            $this->issue($c, 'reset', silent: true);
        }
    }

    public function sendVerification(Customer $c): void
    {
        if ($c->email_verified_at) {
            throw ValidationException::withMessages(['email' => __('store.api.already_verified')]);
        }
        $this->issue($c, 'verify');
    }

    public function verify(Customer $c, string $code): Customer
    {
        $this->consume($c->email, 'verify', $code);
        $c->forceFill(['email_verified_at' => now()])->save();

        return $c;
    }

    public function reset(string $email, string $code, string $password): void
    {
        $email = strtolower(trim($email));
        $c = Customer::where('email', $email)->where('is_active', true)->first();
        $this->consume($email, 'reset', $code, $c === null);
        $c->forceFill(['password' => $password])->save();
        $c->tokens()->delete(); // sign out every device after a reset
    }

    /**
     * Re-issuing replaces the code but keeps the wrong-attempt counter of a still-valid code (a fresh code never buys 5 new guesses);
     * the counter only restarts once the previous code has expired. Cooldown / hourly cap: silent for reset (no enumeration), 422 for verify.
     */
    private function issue(Customer $c, string $type, bool $silent = false): void
    {
        $email = strtolower($c->email);
        $key = 'auth-code:'.current_tenant()->id.':'.$type.':'.$email;
        if (RateLimiter::tooManyAttempts($key.':cool', 1) || RateLimiter::tooManyAttempts($key.':hour', self::MAX_ISSUES)) {
            if ($silent) {
                return;
            }
            throw ValidationException::withMessages(['email' => __('store.api.too_many')]);
        }
        RateLimiter::hit($key.':cool', self::COOLDOWN);
        RateLimiter::hit($key.':hour', 3600);

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        DB::transaction(function () use ($email, $type, $code) {
            $q = DB::table('customer_auth_codes')->where('tenant_id', current_tenant()->id)->where('email', $email)->where('type', $type);
            $row = (clone $q)->lockForUpdate()->first();
            $fields = ['code_hash' => Hash::make($code), 'expires_at' => now()->addMinutes(self::MINUTES), 'updated_at' => now()];
            if ($row) {
                (clone $q)->update($fields + (now()->gt($row->expires_at) ? ['attempts' => 0] : []));
            } else {
                DB::table('customer_auth_codes')->insert($fields + ['tenant_id' => current_tenant()->id, 'email' => $email, 'type' => $type, 'attempts' => 0, 'created_at' => now()]);
            }
        });
        $c->notify(new AuthCodeNotification($type, $code, self::MINUTES));
    }

    private function consume(string $email, string $type, string $code, bool $forceFail = false): void
    {
        $q = DB::table('customer_auth_codes')->where('tenant_id', current_tenant()->id)->where('email', strtolower($email))->where('type', $type);
        $row = (clone $q)->lockForUpdate()->first();
        if (! $row || $forceFail || $row->attempts >= self::MAX_ATTEMPTS || now()->gt($row->expires_at) || ! Hash::check($code, $row->code_hash)) {
            if ($row) {
                (clone $q)->increment('attempts');
            }
            throw ValidationException::withMessages(['code' => __('store.api.invalid_code')]);
        }
        (clone $q)->delete();
    }
}
