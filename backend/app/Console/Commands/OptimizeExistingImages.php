<?php

namespace App\Console\Commands;

use App\Jobs\OptimizeStoredImage;
use App\Models\ImageOptimization;
use App\Services\Images\ExistingImageMigrator;
use App\Services\Images\ImageOptimizer;
use App\Support\MediaPaths;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class OptimizeExistingImages extends Command
{
    protected $signature = 'images:optimize-existing
        {--dry-run : inventory + report only; writes nothing (files, ledger or DB)}
        {--sample=0 : with --dry-run, really encode N files in memory to estimate savings}
        {--category=* : images, brands, categories, bundles, packaging, bundle_ab, banners, ai (default: all)}
        {--disk= : storage disk (default: config media.disk)}
        {--chunk=100 : sources processed per progress batch}
        {--limit=0 : stop after N sources (pilot runs)}
        {--apply : after verifying each AVIF, repoint DB references to it (originals are kept)}
        {--retry-failed : re-run sources previously failed/exceeds_limit/invalid/missing}
        {--queue : dispatch one job per source instead of converting inline}
        {--rollback : restore DB references to the originals for ledger rows that were applied}';

    protected $description = 'Convert every DB-referenced stored image to AVIF (resumable, idempotent, originals kept) and optionally repoint references; --rollback undoes it.';

    public function handle(ExistingImageMigrator $migrator): int
    {
        if (! ImageOptimizer::avifSupported()) {
            $this->error('This PHP build cannot encode AVIF (GD needs libavif; see docs/IMAGES.md).');

            return self::FAILURE;
        }
        $disk = $this->option('disk') ?: config('media.disk');
        $cats = $this->option('category') ?: ExistingImageMigrator::categories();
        if ($bad = array_diff($cats, ExistingImageMigrator::categories())) {
            $this->error('Unknown category: '.implode(', ', $bad));

            return self::FAILURE;
        }
        $lock = Cache::lock('images:optimize-existing', 6 * 3600);
        if (! $lock->get()) {
            $this->error('Another images:optimize-existing run is in progress.');

            return self::FAILURE;
        }
        try {
            if ($this->option('rollback')) {
                return $this->rollback($migrator, $disk, $cats);
            }

            return $this->option('dry-run') ? $this->dryRun($migrator, $disk, $cats) : $this->convertAll($migrator, $disk, $cats);
        } finally {
            $lock->release();
        }
    }

    private function convertAll(ExistingImageMigrator $migrator, string $disk, array $cats): int
    {
        $seen = [];
        $n = 0;
        $limit = (int) $this->option('limit');
        $chunk = max(1, (int) $this->option('chunk'));
        $stats = ['optimized' => 0, 'skipped' => 0, 'missing' => 0, 'invalid' => 0, 'exceeds_limit' => 0, 'failed' => 0, 'queued' => 0];
        $refs = 0;
        foreach ($migrator->sources($cats) as $s) {
            if (isset($seen[$s['path']])) {
                continue;
            }
            $seen[$s['path']] = true;
            if ($limit && $n >= $limit) {
                break;
            }
            $n++;
            if ($this->option('queue')) {
                OptimizeStoredImage::dispatch($s['category'], $s['path'], $disk, (bool) $this->option('apply'));
                $stats['queued']++;
            } else {
                $row = $migrator->convert($s['category'], $s['path'], $disk, (bool) $this->option('retry-failed'));
                $stats[$row->status] = ($stats[$row->status] ?? 0) + 1;
                $row->status === 'optimized' && $this->option('apply') && $refs += $migrator->apply($row);
                in_array($row->status, ['failed', 'exceeds_limit', 'invalid', 'missing'], true) && $this->warn("{$row->status}: {$s['path']} — {$row->reason}");
            }
            $n % $chunk === 0 && $this->line("… $n sources processed");
        }

        $this->report($disk, $stats, $refs);

        return self::SUCCESS;
    }

    private function report(string $disk, array $stats, int $refs): void
    {
        $this->newLine();
        $this->table(['status', 'count'], collect($stats)->map(fn ($c, $k) => [$k, $c])->values()->all());
        $ok = ImageOptimization::where('disk', $disk)->where('status', 'optimized');
        $src = (int) (clone $ok)->sum('source_bytes');
        $out = (int) (clone $ok)->sum('output_bytes');
        $this->info('Optimized (ledger total): '.(clone $ok)->count()." files, {$this->kb($src)} → {$this->kb($out)}".($src ? sprintf(' (%.1f%% saved)', ($src - $out) / $src * 100) : ''));
        $this->info("DB references updated this run: $refs".($this->option('apply') ? '' : ' (no --apply: references untouched)'));
    }

    private function dryRun(ExistingImageMigrator $migrator, string $disk, array $cats): int
    {
        $d = Storage::disk($disk);
        $byCat = [];
        $seen = [];
        $bytes = 0;
        $missing = [];
        $ref = [];
        foreach ($migrator->sources($cats) as $s) {
            $p = $s['path'];
            $byCat[$s['category']] ??= ['refs' => 0, 'files' => 0, 'bytes' => 0, 'missing' => 0, 'skip' => 0];
            $byCat[$s['category']]['refs']++;
            if (isset($seen[$p])) {
                continue;
            }
            $seen[$p] = true;
            $ref[MediaPaths::stem($p)] = true;
            $ext = strtolower(pathinfo($p, PATHINFO_EXTENSION));
            if (preg_match('#^https?://#', $p) || $ext === 'avif' || ! in_array($ext, ['webp', 'jpg', 'jpeg', 'png', 'gif', 'bmp'], true)) {
                $byCat[$s['category']]['skip']++;

                continue;
            }
            if (! $d->exists($p)) {
                $byCat[$s['category']]['missing']++;
                $missing[] = $p;

                continue;
            }
            $size = (int) $d->size($p);
            $byCat[$s['category']]['files']++;
            $byCat[$s['category']]['bytes'] += $size;
            $bytes += $size;
        }
        $this->table(['category', 'references', 'convertible files', 'bytes', 'missing', 'skipped (external/avif/other)'],
            collect($byCat)->map(fn ($v, $k) => [$k, $v['refs'], $v['files'], $this->kb($v['bytes']), $v['missing'], $v['skip']])->values()->all());
        $this->info('Convertible: '.array_sum(array_column($byCat, 'files'))." distinct files, {$this->kb($bytes)} total.");
        foreach (array_slice($missing, 0, 20) as $m) {
            $this->warn("missing: $m");
        }

        // filesystem side: files nothing in the DB points at (reported, never converted or deleted)
        $orphans = 0;
        $other = [];
        foreach ($d->allFiles() as $f) {
            if (isset($ref[MediaPaths::stem($f)])) {
                continue;
            }
            $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
            if (str_contains($f, '/qr/') || ! in_array($ext, ['webp', 'jpg', 'jpeg', 'png', 'gif'], true)) {
                $other[$ext ?: '(none)'] = ($other[$ext ?: '(none)'] ?? 0) + 1;
            } else {
                $orphans++;
            }
        }
        $this->info("Unreferenced image files on '$disk': $orphans (not touched; see media:prune-orphans). Excluded (QR codes / non-raster): ".json_encode($other));

        if ($sample = (int) $this->option('sample')) {
            $this->sample($d, array_keys($seen), $sample);
        }
        $this->comment('[dry-run] nothing was written. Savings figures above are estimates only when --sample is used.');

        return self::SUCCESS;
    }

    private function sample($d, array $paths, int $n): void
    {
        $opt = app(ImageOptimizer::class);
        $src = $out = $done = 0;
        foreach (array_slice(array_filter($paths, fn ($p) => $d->exists($p) && preg_match('/\.(webp|jpe?g|png)$/i', $p)), 0, $n) as $p) {
            $tmp = tempnam(sys_get_temp_dir(), 'imgs');
            file_put_contents($tmp, $d->get($p));
            try {
                $r = $opt->optimize($tmp, ImageOptimizer::profileForPath($p));
                $src += $r->sourceBytes;
                $out += $r->size();
                $done++;
            } catch (\Throwable) {
            } finally {
                @unlink($tmp);
            }
        }
        $done && $this->info(sprintf('Sample of %d: %s → %s (est. %.1f%% saved)', $done, $this->kb($src), $this->kb($out), ($src - $out) / max(1, $src) * 100));
    }

    private function rollback(ExistingImageMigrator $migrator, string $disk, array $cats): int
    {
        $n = 0;
        ImageOptimization::where('disk', $disk)->whereNotNull('applied_at')->whereIn('category', $cats)->each(function ($row) use ($migrator, &$n) {
            $n += $migrator->revert($row);
        });
        $this->info("Rolled back $n reference(s). Original files were never deleted; AVIF files remain on disk.");

        return self::SUCCESS;
    }

    private function kb(int $b): string
    {
        return $b >= 1048576 ? round($b / 1048576, 2).' MB' : round($b / 1024, 1).' KB';
    }
}
