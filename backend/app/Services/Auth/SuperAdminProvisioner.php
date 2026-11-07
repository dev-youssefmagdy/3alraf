<?php

namespace App\Services\Auth;

use App\Models\Admin;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

/** The only code path that can create a super admin (never public registration, never mass-assignment). One super admin, also enforced by a DB unique index. */
class SuperAdminProvisioner
{
    public function provision(string $name, string $phoneDigits, string $password): Admin
    {
        $existing = Admin::query()->where('is_super_admin', true)->first();
        if ($existing && $existing->phone !== $phoneDigits) {
            throw ValidationException::withMessages(['phone' => 'A super admin already exists.']);
        }
        $admin = $existing ?? Admin::query()->where('phone', $phoneDigits)->first() ?? new Admin;
        $admin->fill(['name' => $name, 'phone' => $phoneDigits, 'password' => $password, 'is_active' => true]);
        $admin->email ??= null;
        // the former platform super admin signed in without an OTP (pre-2026-11 AdminLoginController bypass by role);
        // kept as the per-account flag, also because a fresh install has no WhatsApp session to deliver a code yet (plan §1.12)
        $admin->forceFill(['is_super_admin' => true, 'otp_exempt' => true])->save();
        if ($role = Role::where(['name' => 'super-admin', 'guard_name' => 'admin'])->first()) {
            $admin->assignRole($role);
        }

        return $admin;
    }
}
