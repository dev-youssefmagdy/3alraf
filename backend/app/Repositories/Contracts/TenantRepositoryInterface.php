<?php

namespace App\Repositories\Contracts;

use App\Models\Tenant;

interface TenantRepositoryInterface extends RepositoryInterface
{
    public function findBySlug(string $slug, bool $onlyActive = true): ?Tenant;
}
