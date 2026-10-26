<?php

namespace App\Repositories\Contracts;

use App\Models\AiGeneration;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface AiGenerationRepositoryInterface
{
    public function create(array $data): AiGeneration;

    public function find(int $id): ?AiGeneration;

    /** @param string[] $types */
    public function history(array $types, int $perPage = 20): LengthAwarePaginator;

    public function delete(AiGeneration $g): void;

    /** Row lock inside a transaction (approve/reject race guard). @param string[] $types */
    public function lock(int $id, array $types): ?AiGeneration;

    /** Branch-summed on_hand - reserved of one stock item. */
    public function available(int $productId, int $variantId): int;

    /** Drafts of these types older than $before -> rejected(expired). Returns rows touched. @param string[] $types */
    public function expireDrafts(array $types, \DateTimeInterface $before): int;
}
