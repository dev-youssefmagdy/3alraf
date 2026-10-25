<?php

namespace App\Models;

use App\Enums\AffiliateStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Str;

class Affiliate extends Authenticatable
{
    use BelongsToTenant, HasApiTokens, \App\Models\Concerns\RevokesSessionsOnLockout;

    protected $fillable = ['name', 'email', 'phone', 'password', 'status', 'approved_at'];

    protected $hidden = ['password', 'remember_token'];

    protected static function booted(): void
    {
        static::creating(fn (self $a) => $a->uuid ??= (string) Str::uuid());
    }

    /** Single place where affiliate e-mails are normalised (trim + lowercase) - every write goes through this mutator. */
    public static function normalizeEmail(?string $email): string
    {
        return mb_strtolower(trim((string) $email));
    }

    public function setEmailAttribute($value): void
    {
        $this->attributes['email'] = self::normalizeEmail($value);
    }

    /** Validation rules for an affiliate e-mail: unique per tenant (the unique index is case-insensitive), optionally ignoring one row. */
    public static function emailRules(?int $ignoreId = null): array
    {
        return ['required', 'email', 'max:190', \Illuminate\Validation\Rule::unique('affiliates', 'email')->where('tenant_id', current_tenant()->id)->ignore($ignoreId)];
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

    /** Must not stay logged in anywhere (RevokesSessionsOnLockout + EnsureAccountActive). */
    public function isLockedOut(): bool
    {
        return ! $this->isApproved();
    }
}
