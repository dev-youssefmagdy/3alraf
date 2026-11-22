<?php

namespace App\Jobs\Casting;

use App\Models\CastingCandidate;
use App\Services\Casting\GenerationRunner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;

/** One candidate portrait. GPU jobs are serialised (WithoutOverlapping) because the local card has 8 GB. */
class GenerateCandidatePortraitJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 0;

    public int $maxExceptions = 3;

    public int $timeout = 900;

    public function __construct(public int $candidateId, public string $token)
    {
        $this->onQueue(config('casting.job.queue'));
    }

    public function retryUntil(): \DateTimeInterface
    {
        return now()->addHours(2);
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping('casting-gpu'))->releaseAfter(20)->expireAfter(1200)];
    }

    public function handle(GenerationRunner $runner): void
    {
        $tries = (int) config('casting.job.tries');
        $sync = ! $this->job || $this->job->getConnectionName() === 'sync';
        if ($runner->candidate($this->candidateId, $this->token, $sync || $this->attempts() >= $tries)) {
            $this->release((int) (config('casting.job.backoff')[min($this->attempts(), 1)] ?? 60));
        }
    }

    public function failed(\Throwable $e): void
    {
        CastingCandidate::query()->where('id', $this->candidateId)->where('job_token', $this->token)->whereIn('status', ['queued', 'generating'])
            ->update(['status' => 'failed', 'error' => GenerationRunner::clean($e->getMessage())]);
    }
}
