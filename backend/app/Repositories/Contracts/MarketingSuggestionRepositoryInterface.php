<?php

namespace App\Repositories\Contracts;

use App\Models\MarketingSuggestion;

interface MarketingSuggestionRepositoryInterface extends RepositoryInterface
{
    public function lockForUpdate(int $id): MarketingSuggestion;

    /** Open suggestion with the same type + content hash (duplicate prevention). */
    public function openDuplicate(string $type, string $contentHash, ?int $exceptId = null): ?MarketingSuggestion;

    /** Rejected suggestion with the same type + content hash rejected within $days days. */
    public function recentlyRejected(string $type, string $contentHash, int $days): ?MarketingSuggestion;

    /** Open suggestions whose expires_at has passed. */
    public function dueForExpiry(): \Illuminate\Support\Collection;

    /** KPIs: by_status, by_type, total. */
    public function counts(array $types = []): array;

    /** Filtered query (API list + panel tables): types[], status, priority, target_entity_type/id, token_id, search, from/to (created), product_id, category_id, sort. */
    public function filtered(array $f): \Illuminate\Database\Eloquent\Builder;
}
