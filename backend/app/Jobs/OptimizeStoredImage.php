<?php

namespace App\Jobs;

use App\Services\Images\ExistingImageMigrator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Queue form of `images:optimize-existing --queue`: one source file per job, unique per (disk, path), bounded retries. Idempotent via the ledger. */
class OptimizeStoredImage implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $uniqueFor = 900;

    public function __construct(public string $category, public string $path, public string $disk, public bool $apply = false) {}

    public function uniqueId(): string
    {
        return "$this->disk:$this->path";
    }

    public function backoff(): array
    {
        return [30, 120];
    }

    public function handle(ExistingImageMigrator $migrator): void
    {
        $row = $migrator->convert($this->category, $this->path, $this->disk);
        if ($row->status === 'failed' && $this->attempts() < $this->tries) {
            $row->delete(); // let the retry run the conversion again instead of hitting the idempotent short-circuit
            $this->release(30);

            return;
        }
        $this->apply && $migrator->apply($row);
    }
}
