<?php

namespace App\Repositories\Contracts;

use Carbon\CarbonInterface;
use Closure;
use Illuminate\Database\Eloquent\Model;

/** Cross-tenant, scope-free reads/deletes used only by the soft-delete retention job. */
interface RetentionRepositoryInterface
{
    /** Chunked walk over soft-deleted rows of $modelClass with deleted_at < $cutoff (all tenants). */
    public function eachExpired(string $modelClass, CarbonInterface $cutoff, int $chunk, Closure $callback): void;

    /** True when any row of $table has $column = $id (raw table: includes soft-deleted rows and every tenant). */
    public function referenced(string $table, string $column, int $id): bool;

    /** @param  array<int,array{0:string,1:string}>  $refs  [table, column] pairs */
    public function referencedAny(array $refs, int $id): bool;

    /** @return array<int,string> image paths (main + thumb) of the morph owner; rows are deleted. */
    public function detachImages(Model $owner): array;

    /** Delete rows of $table matching $where, returns affected count. */
    public function deleteWhere(string $table, array $where): int;

    /** @return array<int,string> stored QR file paths of the given owners (rows deleted). */
    public function detachQrCodes(string $ownerType, array $ownerIds): array;

    public function ids(string $table, array $where, string $column = 'id'): array;
}
