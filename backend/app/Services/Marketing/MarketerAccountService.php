<?php

namespace App\Services\Marketing;

use App\DTOs\Marketing\MarketerAccountData;
use App\Models\Marketer;
use App\Support\Marketing\MarketingPermissions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Marketer accounts, managed inside the marketer panel by `marketers.manage` (marketer-owner).
 * Rules: you cannot lock yourself out (deactivate / demote / delete your own account) and at least one active owner always remains.
 */
class MarketerAccountService
{
    public function save(MarketerAccountData $d, ?Marketer $m = null): Marketer
    {
        return DB::transaction(function () use ($d, $m) {
            $m ??= new Marketer;
            $self = $m->exists && $m->is(auth(MarketingPermissions::GUARD)->user());
            if ($self && (! $d->isActive || $d->role !== MarketingPermissions::OWNER_ROLE) && $m->isOwner()) {
                throw ValidationException::withMessages(['role' => __('marketing.accounts.cannot_demote_self')]);
            }
            if ($m->exists && $m->isOwner() && ($d->role !== MarketingPermissions::OWNER_ROLE || ! $d->isActive)) {
                $this->assertAnotherOwner($m);
            }
            $m->fill(['name' => $d->name, 'email' => $d->email, 'phone' => $d->phone, 'is_active' => $d->isActive, 'otp_exempt' => $d->otpExempt]);
            if ($d->password) {
                $m->password = $d->password;
            }
            $m->save();
            $m->syncRoles([$d->role]);
            activity('marketing')->event('marketer.saved')->performedOn($m)->causedBy(auth(MarketingPermissions::GUARD)->user())
                ->withProperties(['role' => $d->role, 'active' => $d->isActive])->log('marketer.saved');

            return $m;
        });
    }

    public function delete(Marketer $m): void
    {
        if ($m->is(auth(MarketingPermissions::GUARD)->user())) {
            throw ValidationException::withMessages(['id' => __('marketing.accounts.cannot_delete_self')]);
        }
        if ($m->isOwner()) {
            $this->assertAnotherOwner($m);
        }
        activity('marketing')->event('marketer.deleted')->performedOn($m)->causedBy(auth(MarketingPermissions::GUARD)->user())->log('marketer.deleted');
        $m->delete(); // marketing rows keep their history (FKs null on delete)
    }

    private function assertAnotherOwner(Marketer $m): void
    {
        $others = Marketer::role(MarketingPermissions::OWNER_ROLE, MarketingPermissions::GUARD)->where('is_active', true)->whereKeyNot($m->getKey())->exists();
        if (! $others) {
            throw ValidationException::withMessages(['role' => __('marketing.accounts.last_owner')]);
        }
    }
}
