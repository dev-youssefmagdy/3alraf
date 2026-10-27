<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\FreesUniqueOnDelete;
use App\Models\Concerns\RevokesSessionsOnLockout;
use App\Services\Growth\GrowthFeatures;
use App\Services\Growth\GrowthRetentionService;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class Customer extends Authenticatable
{
    // Audited MUST boot before RevokesSessionsOnLockout (its `updated` hook re-saves the row, which would hide the change from the audit log) (alphabetical order keeps it so).
    use Audited, BelongsToTenant, \App\Models\Concerns\HasStorefrontPhone, FreesUniqueOnDelete, HasApiTokens, HasRoles, Notifiable, RevokesSessionsOnLockout, SoftDeletes;

    /** Audit: PII shown masked only. */
    protected array $auditMasked = ['name', 'email', 'phone'];

    protected array $freedOnDelete = ['email', 'google_id'];

    protected string $guard_name = 'web';

    protected $fillable = ['name', 'email', 'phone', 'phone_country_code', 'password', 'is_active'];

    protected $hidden = ['password', 'remember_token', 'google_id'];

    protected static function booted(): void
    {
        // Growth data erasure (gated by the `interactions` / `small_batch_lab` / `ambassadors` switches inside the service call site).
        static::deleted(function (Customer $c) {
            try {
                $t = $c->tenant_id ? Tenant::find($c->tenant_id) : null;
                $f = app(GrowthFeatures::class);
                if ($t && $f->active('interactions', $t) || $t && $f->active('small_batch_lab', $t) || $t && $f->active('ambassadors', $t)) {
                    app(GrowthRetentionService::class)->eraseCustomer($t, $c);
                }
            } catch (\Throwable $e) {
                report($e); // never block a customer deletion
            }
        });
    }

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
