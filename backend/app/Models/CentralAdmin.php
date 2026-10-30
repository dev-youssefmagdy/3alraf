<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class CentralAdmin extends Authenticatable
{
    use HasRoles, Notifiable, \App\Models\Concerns\RevokesSessionsOnLockout;

    protected string $guard_name = 'central';

    // is_super_admin is deliberately NOT fillable: it is only set by the provisioning command / tenant creation (forceFill)
    protected $fillable = ['name', 'email', 'phone', 'password', 'is_active'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['password' => 'hashed', 'is_active' => 'bool', 'is_super_admin' => 'bool'];
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
