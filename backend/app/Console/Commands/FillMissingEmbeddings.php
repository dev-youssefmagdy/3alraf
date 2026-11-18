<?php

namespace App\Console\Commands;

use App\Services\ImageSearch\ImageEmbeddingService;
use Illuminate\Console\Command;

/** Nightly safety net of the image-search index: queues ONLY missing / replaced images (never recomputes up-to-date ones) and prunes orphans. */
class FillMissingEmbeddings extends Command
{
    protected $signature = 'embeddings:fill-missing {--limit= : queue at most this many}';

    protected $description = 'Queue image-search embeddings for images and bundles that have none for the current model (or whose file changed)';

    public function handle(ImageEmbeddingService $embeddings): int
    {
        $limit = $this->option('limit');
        if ($limit !== null && (! ctype_digit((string) $limit) || (int) $limit < 1)) {
            $this->error('--limit must be a positive integer');

            return self::INVALID;
        }
        $r = $embeddings->fillMissing($limit !== null ? (int) $limit : null);
        $s = $r['status'];
        $this->info("Queued {$r['queued']} (remaining {$r['remaining']}, pruned {$r['pruned']}). Model {$s['model']}: indexed {$s['indexed']} / {$s['total']}, "
            ."pending {$s['pending']}, failed {$s['failed']}, missing {$s['missing']}.");

        return self::SUCCESS;
    }
}
