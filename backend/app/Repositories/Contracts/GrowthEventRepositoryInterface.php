<?php

namespace App\Repositories\Contracts;

interface GrowthEventRepositoryInterface extends RepositoryInterface
{
    public function createForTenant(array $data): \App\Models\GrowthEvent;

    public function lockForUpdate(int $id): ?\App\Models\GrowthEvent;

    public function dueIds(iterable $tenantIds, int $limit = 500): \Illuminate\Support\Collection;

    public function prune(int $days): int;
}
