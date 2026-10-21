<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class TenantAdmin extends Authenticatable
{
    use HasRoles, Notifiable, BelongsToTenant;

    protected string $guard_name = 'tenant';

    protected $fillable = ['name', 'email', 'password', 'is_active'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['password' => 'hashed', 'is_active' => 'bool'];
    }
}
