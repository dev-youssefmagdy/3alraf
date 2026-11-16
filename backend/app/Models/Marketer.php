<?php

namespace App\Models;

use App\Models\Concerns\RevokesSessionsOnLockout;
use App\Support\Marketing\MarketingPermissions;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

/**
 * Marketer panel account (guard `marketer`, table `marketers`, login by phone + password + WhatsApp OTP).
 * Fully separate from Admin: an admin session never opens /marketer and a marketer session never opens /admin.
 * Role `marketer-owner` (MarketingPermissions::OWNER_ROLE) = every marketing permission (approve / apply / publish / tokens / accounts).
 */
class Marketer extends Authenticatable
{
    use HasRoles, Notifiable, RevokesSessionsOnLockout;

    public const GUARD = 'marketer';

    protected string $guard_name = self::GUARD;

    protected $fillable = ['name', 'email', 'phone', 'password', 'is_active', 'otp_exempt', 'last_login_at'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['password' => 'hashed', 'is_active' => 'bool', 'otp_exempt' => 'bool', 'last_login_at' => 'datetime'];
    }

    public function isOwner(): bool
    {
        return $this->hasRole(MarketingPermissions::OWNER_ROLE);
    }

    /** Must not stay logged in anywhere (RevokesSessionsOnLockout + EnsureAccountActive). */
    public function isLockedOut(): bool
    {
        return $this->is_active === false;
    }
}
