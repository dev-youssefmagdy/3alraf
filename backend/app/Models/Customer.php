<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class Customer extends Authenticatable
{
    use HasRoles, Notifiable, BelongsToTenant;

    protected string $guard_name = 'web';

    protected $fillable = ['name', 'email', 'phone', 'password', 'is_active'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['password' => 'hashed', 'is_active' => 'bool'];
    }

    public function addresses()
    {
        return $this->hasMany(CustomerAddress::class)->latest('is_default')->orderBy('id');
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}
