<?php

namespace App\Repositories\Contracts;

interface GrowthEventRepositoryInterface extends RepositoryInterface
{
    public function lockForUpdate(int $id): ?\App\Models\GrowthEvent;

    public function dueIds(int $limit = 500): \Illuminate\Support\Collection;

    public function prune(int $days): int;
}
