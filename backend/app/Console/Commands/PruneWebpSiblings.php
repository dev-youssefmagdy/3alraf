<?php

namespace App\Console\Commands;

use App\Services\ImageUploadService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneWebpSiblings extends Command
{
    protected $signature = 'images:prune-webp {--dry-run : only list}';

    protected $description = 'Delete the .webp fallback files that sit beside an .avif of the same name. A .webp that the database still references, or that has no .avif beside it, is kept.';

    public function handle(): int
    {
        $disk = ImageUploadService::disk();
        $used = array_flip($this->referenced());
        $n = 0;
        $bytes = 0;
        foreach (['catalog', 'tenants'] as $root) {
            foreach ($disk->allFiles($root) as $file) {
                if (! str_ends_with($file, '.webp') || isset($used[$file]) || ! $disk->exists(substr($file, 0, -5).'.avif')) {
                    continue;
                }
                $n++;
                $bytes += (int) $disk->size($file);
                if (! $this->option('dry-run')) {
                    $disk->delete($file);
                }
            }
        }
        $this->info(($this->option('dry-run') ? 'Would delete ' : 'Deleted ')."{$n} .webp file(s), ".round($bytes / 1048576, 1).' MB.');

        return self::SUCCESS;
    }

    /** @return string[] webp paths still referenced by the database */
    private function referenced(): array
    {
        $paths = [];
        foreach ([['images', 'path'], ['images', 'thumb_path'], ['brands', 'logo'], ['categories', 'image']] as [$table, $col]) {
            DB::table($table)->where($col, 'like', '%.webp')->pluck($col)->each(function ($p) use (&$paths) {
                $paths[] = $p;
            });
        }
        DB::table('banners')->whereNotNull('images')->pluck('images')->each(function ($json) use (&$paths) {
            foreach ((array) json_decode($json, true) as $p) {
                is_string($p) && str_ends_with($p, '.webp') && $paths[] = $p;
            }
        });

        return $paths;
    }
}
