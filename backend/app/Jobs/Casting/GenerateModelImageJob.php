<?php

namespace App\Jobs\Casting;

use App\Models\ModelGenerationHistory;
use App\Services\Casting\GenerationRunner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;

/** One ad image / pose reference / ad video (ModelGenerationHistory row). GPU jobs are serialised (WithoutOverlapping) because the local card has 8 GB. */
class GenerateModelImageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 0;

    public int $maxExceptions = 3;

    public int $timeout = 2700;

    public function __construct(public int $historyId, public string $token)
    {
        $this->onQueue(config('casting.job.queue'));
    }

    public function retryUntil(): \DateTimeInterface
    {
        return now()->addHours(2);
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping('casting-gpu'))->releaseAfter(20)->expireAfter(2800)];
    }

    public function handle(GenerationRunner $runner): void
    {
        $tries = (int) config('casting.job.tries');
        $sync = ! $this->job || $this->job->getConnectionName() === 'sync';
        if ($runner->history($this->historyId, $this->token, $sync || $this->attempts() >= $tries)) {
            $this->release((int) (config('casting.job.backoff')[min($this->attempts(), 1)] ?? 60));
        }
    }

    public function failed(\Throwable $e): void
    {
        ModelGenerationHistory::query()->where('id', $this->historyId)->where('job_token', $this->token)->whereIn('status', ['queued', 'running'])
            ->update(['status' => 'failed', 'error' => GenerationRunner::clean($e->getMessage())]);
    }
}
