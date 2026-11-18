<?php

namespace App\Repositories\Contracts;

use App\Enums\ImageEmbeddingSource;
use App\Models\ImageEmbedding;
use Illuminate\Support\Collection;

interface ImageEmbeddingRepositoryInterface extends RepositoryInterface
{
    public function findSource(ImageEmbeddingSource $type, int $id, string $model): ?ImageEmbedding;

    /** Upsert the row of a source for a model (a write that includes `vector` bumps the index version). */
    public function put(ImageEmbeddingSource $type, int $id, string $model, array $values): ImageEmbedding;

    /** @param  list<int>  $ids */
    public function deleteSources(ImageEmbeddingSource $type, array $ids): int;

    /** Every catalog source that should have a vector: [type, id, path, product_id, variant_id, bundle_id] rows (non-deleted owners only). */
    public function sources(): Collection;

    /** Rows of the given model keyed "type:id" (only those of the $only sources when given). */
    public function rowsByKey(string $model, ?Collection $only = null): Collection;

    /** Rows (any model) whose source no longer exists: [type => [ids]]. */
    public function orphans(): array;

    /** Index for search: ['blob' => concatenated float32, 'map' => list<[type, source_id, product_id, variant_id, bundle_id]>, 'dim' => int]. */
    public function searchIndex(string $model, int $dim): array;

    /** Random token changed on every write; part of the search-index cache key. */
    public function version(): string;

    public function bumpVersion(): void;
}
