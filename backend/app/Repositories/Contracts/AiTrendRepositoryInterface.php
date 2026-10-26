<?php

namespace App\Repositories\Contracts;

use App\Models\AiTrendSnapshot;
use Illuminate\Support\Collection;

interface AiTrendRepositoryInterface
{
    /**
     * Per product sales over the last $days and the $days before (confirmed-set orders, net of returns), tenant scoped.
     *
     * @return Collection<int,object> product_id, qty, prev_qty, revenue, cost
     */
    public function velocity(int $days, ?int $categoryId = null): Collection;

    /** @return array<int,int> product_id => available (on hand - reserved, all branches) */
    public function availableByProduct(?array $productIds = null): array;

    /** Listed (tenant) products with identity fields for matching. @return Collection<int,object> product_id, tp_id, sku, barcode, names(array), cost */
    public function listedProducts(): Collection;

    /** @return array{categories:array<int,string>,brands:array<int,string>} top names in the tenant's catalog */
    public function catalogMix(int $limit = 8): array;

    public function latestSnapshot(string $kind, ?string $country): ?AiTrendSnapshot;

    public function saveSnapshot(string $kind, ?string $country, ?string $period, array $items): AiTrendSnapshot;

    public function updateItems(AiTrendSnapshot $s, array $items): void;
}
