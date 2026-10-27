<?php

namespace App\Repositories\Contracts;

interface GrowthApprovalRepositoryInterface extends RepositoryInterface
{
    public function lockForUpdate(int $id): \App\Models\GrowthApproval;

    /** Expire pending approvals past their deadline for the given tenants (scheduled housekeeping). */
    public function expireDue(iterable $tenantIds): int;

    public function markExpired(int $id): void;
}
