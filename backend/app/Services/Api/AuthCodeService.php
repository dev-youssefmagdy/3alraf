<?php

namespace App\Services\Api;

use App\Models\Customer;
use App\Notifications\AuthCodeNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/** One-time 6-digit codes (hashed, 60 min, max 5 wrong tries) for password reset and e-mail verification of mobile customers. */
class AuthCodeService
{
    private const MINUTES = 60;

    private const MAX_ATTEMPTS = 5;

    /** Always silent about whether the e-mail exists. */
    public function sendReset(string $email): void
    {
        if ($c = Customer::where('email', strtolower(trim($email)))->where('is_active', true)->first()) {
            $this->issue($c, 'reset');
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

    private function issue(Customer $c, string $type): void
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        DB::table('customer_auth_codes')->upsert([[
            'tenant_id' => current_tenant()->id, 'email' => strtolower($c->email), 'type' => $type, 'code_hash' => Hash::make($code), 'attempts' => 0,
            'expires_at' => now()->addMinutes(self::MINUTES), 'created_at' => now(), 'updated_at' => now(),
        ]], ['tenant_id', 'email', 'type'], ['code_hash', 'attempts', 'expires_at', 'updated_at']);
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
