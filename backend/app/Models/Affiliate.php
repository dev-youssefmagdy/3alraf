<?php

namespace App\Models;

use App\Enums\AffiliateStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Str;

class Affiliate extends Authenticatable
{
    use BelongsToTenant;

    protected $fillable = ['name', 'email', 'phone', 'password', 'status', 'approved_at'];

    protected $hidden = ['password', 'remember_token'];

    protected static function booted(): void
    {
        static::creating(fn (self $a) => $a->uuid ??= (string) Str::uuid());
    }

    protected function casts(): array
    {
        return ['password' => 'hashed', 'status' => AffiliateStatus::class, 'approved_at' => 'datetime'];
    }

    public function isApproved(): bool
    {
        return $this->status === AffiliateStatus::APPROVED;
    }

    public function commissions()
    {
        return $this->hasMany(AffiliateCommission::class);
    }

    public function clicks()
    {
        return $this->hasMany(AffiliateClick::class);
    }

    public function payouts()
    {
        return $this->hasMany(AffiliatePayout::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}
