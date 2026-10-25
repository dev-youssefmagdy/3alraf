<?php

namespace App\Models\Concerns;

use App\Services\Auth\SessionRevoker;

/**
 * Deactivation / suspension / password change → every other login of the account dies (remember_token rotated, DB sessions deleted).
 * The model defines isLockedOut(); a self-service password change keeps the current session.
 */
trait RevokesSessionsOnLockout
{
    abstract public function isLockedOut(): bool;

    public static function bootRevokesSessionsOnLockout(): void
    {
        static::updated(function ($model) {
            $locked = $model->isLockedOut() && ($model->wasChanged('is_active') || $model->wasChanged('status'));
            if (! $locked && ! $model->wasChanged('password')) {
                return;
            }
            $guard = SessionRevoker::guardFor($model);
            $self = ! $locked && $guard && auth()->guard($guard)->user()?->is($model);
            app(SessionRevoker::class)->revoke($model, keepCurrentSession: (bool) $self);
        });
    }
}
