<?php

namespace App\Models;

use App\Models\Concerns\RevokesSessionsOnLockout;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

/** Admin panel account (guard `admin`, login by phone + OTP). One table for every admin; `super-admin` role / flag = everything. */
class Admin extends Authenticatable
{
    use HasRoles, Notifiable, RevokesSessionsOnLockout;

    protected string $guard_name = 'admin';

    // is_super_admin is deliberately NOT fillable: it is only set by the provisioning command (forceFill)
    protected $fillable = ['name', 'email', 'phone', 'password', 'is_active', 'otp_exempt'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['password' => 'hashed', 'is_active' => 'bool', 'is_super_admin' => 'bool', 'otp_exempt' => 'bool'];
    }

    public function isSuperAdmin(): bool
    {
        return (bool) $this->is_super_admin;
    }

    /** Must not stay logged in anywhere (RevokesSessionsOnLockout + EnsureAccountActive). */
    public function isLockedOut(): bool
    {
        return $this->is_active === false; // null = unsaved default (column defaults to active)
    }
}
