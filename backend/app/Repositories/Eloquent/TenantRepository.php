<?php

namespace App\Repositories\Eloquent;

use App\Models\Tenant;
use App\Repositories\Contracts\TenantRepositoryInterface;

class TenantRepository extends BaseRepository implements TenantRepositoryInterface
{
    protected string $model = Tenant::class;

    public function findBySlug(string $slug, bool $onlyActive = true): ?Tenant
    {
        return $this->query()->where('slug', $slug)->when($onlyActive, fn ($q) => $q->where('is_active', true))->first();
    }
}
