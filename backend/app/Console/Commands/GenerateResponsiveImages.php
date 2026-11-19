<?php

namespace App\Console\Commands;

use App\Services\ImageUploadService;
use App\Services\Images\ImageOptimizer;
use App\Services\Images\InvalidImageException;
use App\Support\MediaPaths;
use Illuminate\Console\Command;

class GenerateResponsiveImages extends Command
{
    protected $signature = 'images:responsive {--dry-run : only count what is missing}';

    protected $description = 'Create the small AVIF sizes (srcset) missing beside every stored thumbnail. Safe to run again: existing files are skipped.';

    public function handle(ImageOptimizer $optimizer): int
    {
        $disk = ImageUploadService::disk();
        $dry = (bool) $this->option('dry-run');
        $made = $failed = $bytes = 0;
        foreach (['catalog', 'tenants'] as $root) {
            foreach ($disk->allFiles($root) as $thumb) {
                if (! str_ends_with($thumb, '_thumb.avif')) {
                    continue;
                }
                foreach (MediaPaths::WIDTHS as $width) {
                    $target = MediaPaths::variantOf($thumb, $width);
                    if ($disk->exists($target)) {
                        continue;
                    }
                    if ($dry) {
                        $made++;

                        continue;
                    }
                    try {
                        $image = $optimizer->optimize($disk->path($thumb), "thumb_$width");
                        $disk->put($target, $image->bytes) || throw new \RuntimeException('write failed');
                        $made++;
                        $bytes += $image->size();
                    } catch (InvalidImageException|\RuntimeException $e) {
                        $failed++;
                        $this->warn("skipped $target: ".$e->getMessage());
                    }
                }
            }
        }
        $this->info(($dry ? 'Would create ' : 'Created ')."{$made} file(s), ".round($bytes / 1048576, 1)." MB, {$failed} failed.");

        return self::SUCCESS;
    }
}
