<?php

namespace App\Jobs;

use App\Enums\ImageEmbeddingSource;
use App\Services\ImageSearch\EmbedderUnavailableException;
use App\Services\ImageSearch\ImageEmbeddingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Embeds one image-search source (queue `embeddings`, IMAGE_SEARCH_PLAN.md §3.3). Idempotent: an up-to-date row is never recomputed.
 * Embedder down ⇒ released with growing backoff and retried for up to image_search.job_retry_hours (the row stays `pending`, nothing is lost);
 * after that, or on an unusable file, the row is marked `failed` with the reason (fill-missing retries it after retry_failed_after_hours).
 */
class EmbedImageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 60;

    public int $maxExceptions = 3;

    private const DELAYS = [10, 30, 60, 120, 300, 600];

    public function __construct(public string $sourceType, public int $sourceId)
    {
        $this->onQueue((string) config('image_search.queue', 'embeddings'));
        if ($c = config('image_search.queue_connection')) {
            $this->onConnection($c);
        }
    }

    public function retryUntil(): \DateTimeInterface
    {
        return now()->addHours((int) config('image_search.job_retry_hours', 12));
    }

    public function backoff(): array
    {
        return self::DELAYS;
    }

    public function handle(ImageEmbeddingService $svc): void
    {
        $type = ImageEmbeddingSource::from($this->sourceType);
        try {
            $svc->embed($type, $this->sourceId);
        } catch (EmbedderUnavailableException $e) {
            $svc->noteRetry($type, $this->sourceId, $e->getMessage());
            $this->release(self::DELAYS[min(max(0, $this->attempts() - 1), count(self::DELAYS) - 1)]);
        }
    }

    public function failed(?Throwable $e): void
    {
        app(ImageEmbeddingService::class)->markFailed(ImageEmbeddingSource::from($this->sourceType), $this->sourceId, $e?->getMessage() ?: 'job failed');
    }
}
