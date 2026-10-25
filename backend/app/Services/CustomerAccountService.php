<?php

namespace App\Services;

use App\Models\Customer;
use App\Services\Auth\SessionRevoker;
use Laravel\Sanctum\PersonalAccessToken;

class CustomerAccountService
{
    public function register(array $data): Customer
    {
        return Customer::create($data + ['is_active' => true]);
    }

    public function __construct(private SessionRevoker $sessions) {}

    /**
     * Credential change (e-mail or password; current_password checked by ProfileRequest): a new e-mail must be re-verified, and every
     * other login dies — other API tokens and other web sessions; the request's own token/session stays signed in.
     */
    public function updateProfile(Customer $c, array $data): Customer
    {
        unset($data['current_password'], $data['password_confirmation']);
        if (empty($data['password'])) {
            unset($data['password']);
        }
        $c->fill($data);
        $emailChanged = $c->isDirty('email') && strtolower((string) $c->getOriginal('email')) !== strtolower((string) $c->email);
        if ($emailChanged) {
            $c->email_verified_at = null;
        }
        $c->save();

        if ($emailChanged || $c->wasChanged('password')) {
            $current = $c->currentAccessToken();
            $c->tokens()->when($current instanceof PersonalAccessToken, fn ($q) => $q->whereKeyNot($current->getKey()))->delete();
            if (! $c->wasChanged('password')) {   // a password change already revoked the other sessions (RevokesSessionsOnLockout)
                $this->sessions->revoke($c, keepCurrentSession: true);
            }
        }

        return $c;
    }
}
