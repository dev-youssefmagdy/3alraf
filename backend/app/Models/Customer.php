<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\FreesUniqueOnDelete;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class Customer extends Authenticatable
{
    use HasApiTokens, HasRoles, Notifiable, BelongsToTenant, FreesUniqueOnDelete, SoftDeletes, \App\Models\Concerns\RevokesSessionsOnLockout;

    protected array $freedOnDelete = ['email'];

    protected string $guard_name = 'web';

    protected $fillable = ['name', 'email', 'phone', 'password', 'is_active'];

    protected $hidden = ['password', 'remember_token'];

    /** Hide the soft-delete tombstone suffix (see FreesUniqueOnDelete) from every display. */
    public function getEmailAttribute($v): ?string
    {
        return $v === null ? null : preg_replace('/#del\d+$/', '', $v);
    }

    protected function casts(): array
    {
        return ['password' => 'hashed', 'is_active' => 'bool', 'email_verified_at' => 'datetime'];
    }

    public function addresses()
    {
        return $this->hasMany(CustomerAddress::class)->latest('is_default')->orderBy('id');
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    /** Must not stay logged in anywhere (RevokesSessionsOnLockout + EnsureAccountActive). */
    public function isLockedOut(): bool
    {
        return $this->is_active === false; // null = unsaved default (column defaults to active)
    }
}
