<?php

namespace App\Services\ImageSearch;

use App\Enums\ImageEmbeddingSource;
use App\Enums\ImageEmbeddingStatus;
use App\Jobs\EmbedImageJob;
use App\Models\Bundle;
use App\Models\Image;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ImageEmbedding;
use App\Repositories\Contracts\ImageEmbeddingRepositoryInterface;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Image-search index upkeep (IMAGE_SEARCH_PLAN.md §3.3). Sources = every `images` row of a live product / variant + every live bundle with an image.
 *
 * "Missing" (the only work ever queued) = a source with no row for the current model, a row whose source_path changed, a ready row whose
 * sha256(file) no longer matches source_hash (verify_hashes), a failed row past its retry window, or a pending row whose job got lost
 * (older than pending_timeout_minutes). An up-to-date row is NEVER recomputed: the job itself re-checks the hash before calling the embedder.
 * Queuing marks the row `pending` first, so a second fill-missing while jobs are still waiting queues 0.
 */
class ImageEmbeddingService
{
    public function __construct(private ImageEmbeddingRepositoryInterface $repo, private EmbedderClient $client) {}

    public function model(): string
    {
        return (string) config('image_search.model');
    }

    private function disk(): Filesystem
    {
        return Storage::disk(config('media.disk'));
    }

    // ------------------------------------------------------------------ what needs work

    /**
     * Sources that need an embedding for the current model.
     *
     * @return Collection<int, array{type:ImageEmbeddingSource,id:int,path:string,product_id:?int,variant_id:?int,bundle_id:?int,reason:string}>
     */
    public function missing(bool $verifyHashes = false, ?Collection $sources = null): Collection
    {
        $rows = $this->repo->rowsByKey($this->model(), $sources);
        $pendingCutoff = now()->subMinutes((int) config('image_search.pending_timeout_minutes', 360));
        $failedCutoff = now()->subHours((int) config('image_search.retry_failed_after_hours', 24));

        return ($sources ?? $this->repo->sources())->map(function (array $s) use ($rows, $pendingCutoff, $failedCutoff, $verifyHashes) {
            /** @var ImageEmbedding|null $row */
            $row = $rows->get($s['type']->value.':'.$s['id']);
            $reason = match (true) {
                $row === null => 'new',
                $row->status === ImageEmbeddingStatus::PENDING => $row->queued_at && $row->queued_at->lt($pendingCutoff) ? 'lost_job' : null,
                $row->source_path !== $s['path'] => 'replaced',
                $row->status === ImageEmbeddingStatus::FAILED => $row->failed_at && $row->failed_at->lt($failedCutoff) ? 'retry_failed' : null,
                $verifyHashes && $this->hashOf($s['path']) !== $row->source_hash => 'changed',
                default => null,
            };

            return $reason ? $s + ['reason' => $reason] : null;
        })->filter()->values();
    }

    private function hashOf(string $path): ?string
    {
        try {
            $bytes = $this->disk()->get($path);
        } catch (\Throwable) {
            return null;
        }

        return $bytes === null ? null : hash('sha256', $bytes);
    }

    /**
     * Queue missing embeddings only (plus prune rows whose source is gone). Idempotent: run twice, the second run queues 0.
     *
     * @return array{queued:int, remaining:int, pruned:int, status:array}
     */
    public function fillMissing(?int $limit = null): array
    {
        $limit = max(1, min((int) ($limit ?? config('image_search.fill_limit_max', 5000)), (int) config('image_search.fill_limit_max', 5000)));

        return Cache::lock('image_search:fill-missing', 120)->block(30, function () use ($limit) {
            $pruned = $this->pruneOrphans();
            $missing = $this->missing((bool) config('image_search.verify_hashes', true));
            $batch = $missing->take($limit);
            foreach ($batch->chunk(200) as $chunk) { // one commit per chunk; jobs dispatch after it (afterCommit)
                DB::transaction(fn () => $chunk->each(fn (array $s) => $this->queue($s)));
            }
            Log::info('image_search.fill_missing', ['queued' => $batch->count(), 'remaining' => $missing->count() - $batch->count(), 'pruned' => $pruned]);

            return ['queued' => $batch->count(), 'remaining' => $missing->count() - $batch->count(), 'pruned' => $pruned, 'status' => $this->status()];
        });
    }

    /** Delete rows whose image / bundle image no longer exists. */
    public function pruneOrphans(): int
    {
        $n = 0;
        foreach ($this->repo->orphans() as $type => $ids) {
            $n += $this->repo->deleteSources(ImageEmbeddingSource::from($type), $ids);
        }

        return $n;
    }

    /** Mark pending and dispatch one job (after the surrounding transaction commits). */
    private function queue(array $s): void
    {
        $this->repo->put($s['type'], $s['id'], $this->model(), ['product_id' => $s['product_id'], 'product_variant_id' => $s['variant_id'], 'bundle_id' => $s['bundle_id'],
            'status' => ImageEmbeddingStatus::PENDING, 'queued_at' => now(), 'error' => null]);
        EmbedImageJob::dispatch($s['type']->value, $s['id'])->afterCommit();
    }

    // ------------------------------------------------------------------ hooks (observers)

    /** A source was created / replaced: queue it unless it is already queued or up to date (path check; the job checks the hash). */
    public function sourceChanged(ImageEmbeddingSource $type, int $id): void
    {
        if (! config('image_search.auto_index', true)) {
            return;
        }
        $s = $this->resolve($type, $id);
        if ($s === null) {
            $this->forget($type, [$id]);

            return;
        }
        $this->missing(false, collect([$s]))->each(fn (array $m) => $this->queue($m));
    }

    /** A product was restored: queue its (and its variants') images that are missing. */
    public function productRestored(Product $product): void
    {
        if (! config('image_search.auto_index', true)) {
            return;
        }
        $this->missing(false, $this->repo->sources()->where('product_id', $product->id))->each(fn (array $m) => $this->queue($m));
    }

    /** @param  list<int>  $ids */
    public function forget(ImageEmbeddingSource $type, array $ids): void
    {
        $this->repo->deleteSources($type, $ids);
    }

    /** Search results depend on product / variant / bundle visibility (soft delete): drop the cached index. */
    public function visibilityChanged(): void
    {
        $this->repo->bumpVersion();
    }

    // ------------------------------------------------------------------ the job's work

    /** Current state of a source, or null when it is gone (deleted image, trashed owner, bundle without image). */
    public function resolve(ImageEmbeddingSource $type, int $id): ?array
    {
        if ($type === ImageEmbeddingSource::BUNDLE) {
            $b = Bundle::query()->find($id);

            return $b && $b->image && ! preg_match('#^https?://#i', $b->image)
                ? ['type' => $type, 'id' => $b->id, 'path' => $b->image, 'product_id' => null, 'variant_id' => null, 'bundle_id' => $b->id] : null;
        }
        $img = Image::query()->find($id);
        if (! $img || ! $img->path) {
            return null;
        }
        $owner = $img->imageable; // soft-deleted owners resolve to null (default scope)
        $product = match (true) {
            $owner instanceof Product => $owner,
            $owner instanceof ProductVariant => $owner->product,
            default => null,
        };

        return $product ? ['type' => $type, 'id' => $img->id, 'path' => $img->path, 'product_id' => $product->id,
            'variant_id' => $owner instanceof ProductVariant ? $owner->id : null, 'bundle_id' => null] : null;
    }

    /**
     * Embed one source now (called by EmbedImageJob). Returns what happened: gone | up_to_date | embedded | failed.
     *
     * @throws EmbedderUnavailableException  the job retries later
     */
    public function embed(ImageEmbeddingSource $type, int $id): string
    {
        $s = $this->resolve($type, $id);
        if ($s === null) {
            $this->forget($type, [$id]);

            return 'gone';
        }
        $model = $this->model();
        $owner = ['product_id' => $s['product_id'], 'product_variant_id' => $s['variant_id'], 'bundle_id' => $s['bundle_id']];
        try {
            $bytes = $this->disk()->get($s['path']);
        } catch (\Throwable) {
            $bytes = null;
        }
        if ($bytes === null || $bytes === '') {
            $this->markFailed($type, $id, 'file not found: '.$s['path'], $s);

            return 'failed';
        }
        $hash = hash('sha256', $bytes);
        $row = $this->repo->findSource($type, $id, $model);
        if ($row && $row->source_hash === $hash && $row->dim === (int) config('image_search.dim') && $row->getRawOriginal('vector') !== null
            && $row->status !== ImageEmbeddingStatus::FAILED) {
            // already embedded from these exact bytes: never recompute
            $this->repo->put($type, $id, $model, $owner + ['source_path' => $s['path'], 'status' => ImageEmbeddingStatus::READY, 'error' => null]);

            return 'up_to_date';
        }

        try {
            $result = $this->client->embed($bytes, basename($s['path']));
        } catch (InvalidImageException $e) {
            $this->markFailed($type, $id, $e->getMessage(), $s, $hash);

            return 'failed';
        }
        $this->repo->put($type, $id, $model, $owner + ['source_path' => $s['path'], 'source_hash' => $hash, 'dim' => $result->dim, 'vector' => $result->vector,
            'status' => ImageEmbeddingStatus::READY, 'error' => null, 'embedded_at' => now(), 'failed_at' => null, 'attempts' => (int) ($row?->attempts) + 1]);

        return 'embedded';
    }

    public function markFailed(ImageEmbeddingSource $type, int $id, string $reason, ?array $source = null, ?string $hash = null): void
    {
        $source ??= $this->resolve($type, $id);
        if ($source === null) {
            $this->forget($type, [$id]);

            return;
        }
        $row = $this->repo->findSource($type, $id, $this->model());
        $this->repo->put($type, $id, $this->model(), ['product_id' => $source['product_id'], 'product_variant_id' => $source['variant_id'], 'bundle_id' => $source['bundle_id'],
            'source_path' => $source['path'], 'source_hash' => $hash ?? $row?->source_hash, 'status' => ImageEmbeddingStatus::FAILED, 'error' => mb_substr($reason, 0, 500),
            'failed_at' => now(), 'attempts' => (int) ($row?->attempts) + 1]);
        Log::warning('image_search.embed_failed', ['type' => $type->value, 'id' => $id, 'reason' => mb_substr($reason, 0, 200)]);
    }

    /** The embedder is down: keep the row pending (with the reason) while the job waits to retry. */
    public function noteRetry(ImageEmbeddingSource $type, int $id, string $reason): void
    {
        if ($row = $this->repo->findSource($type, $id, $this->model())) {
            $this->repo->update($row, ['error' => mb_substr('retrying: '.$reason, 0, 500), 'queued_at' => now()]);
        }
    }

    // ------------------------------------------------------------------ coverage

    /**
     * Coverage of the current model over live sources.
     *
     * @return array{model:string, total:int, indexed:int, pending:int, failed:int, missing:int, waiting:int, percent:float, complete:bool, by_type:array}
     */
    public function status(): array
    {
        $sources = $this->repo->sources();
        $rows = $this->repo->rowsByKey($this->model());
        $c = ['indexed' => 0, 'pending' => 0, 'failed' => 0];
        $byType = [];
        foreach ($sources as $s) {
            $t = $s['type']->value;
            $byType[$t] ??= ['total' => 0, 'indexed' => 0];
            $byType[$t]['total']++;
            $row = $rows->get($t.':'.$s['id']);
            if ($row?->status === ImageEmbeddingStatus::READY && $row->source_path === $s['path']) {
                $c['indexed']++;
                $byType[$t]['indexed']++;
            } elseif ($row?->status === ImageEmbeddingStatus::PENDING) {
                $c['pending']++;
            } elseif ($row?->status === ImageEmbeddingStatus::FAILED) {
                $c['failed']++;
            }
        }
        $total = $sources->count();
        $missing = $total - $c['indexed'] - $c['pending'] - $c['failed'];

        return ['model' => $this->model(), 'total' => $total, 'indexed' => $c['indexed'], 'pending' => $c['pending'], 'failed' => $c['failed'], 'missing' => $missing,
            'waiting' => $total - $c['indexed'], 'percent' => $total ? round($c['indexed'] / $total * 100, 1) : 100.0, 'complete' => $total === $c['indexed'], 'by_type' => $byType];
    }
}
