<?php

namespace App\Repositories\Contracts;

use App\Models\TenantProduct;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface TenantProductRepositoryInterface extends RepositoryInterface
{
    /** Active central products NOT yet enabled for the current tenant. */
    public function available(?string $search, int $perPage = 50): LengthAwarePaginator;

    public function findByProductId(int $productId): ?TenantProduct;
}
