<?php

namespace App\Console\Commands;

use App\Services\ImageUploadService;
use App\Support\MediaPaths;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneOrphanMedia extends Command
{
    protected $signature = 'media:prune-orphans {--dry-run : only list} {--min-age=24 : ignore files younger than N hours (in-flight uploads)}';

    protected $description = 'Find (and delete) files on the media disk under catalog/, qr/ and the legacy per-store root that no DB row references (images, product videos, brand logos, category images, banners, QR codes).';

    public function handle(): int
    {
        $disk = ImageUploadService::disk();
        $used = array_flip(array_map(MediaPaths::stem(...), $this->referenced())); // a family (avif + webp, main + thumb) lives or dies together
        $cut = now()->subHours((int) $this->option('min-age'))->getTimestamp();
        $n = 0;
        $bytes = 0;
        // catalog/ + qr/ = current upload roots; the legacy per-store root still holds every file uploaded before the single-store conversion
        foreach (['catalog', 'qr', 'tenants'] as $root) {
            foreach ($disk->allFiles($root) as $file) {
                if (isset($used[MediaPaths::stem($file)]) || $disk->lastModified($file) > $cut) {
                    continue;
                }
                $n++;
                $bytes += (int) $disk->size($file);
                $this->line(($this->option('dry-run') ? 'orphan: ' : 'deleted: ').$file);
                if (! $this->option('dry-run')) {
                    $disk->delete($file);
                }
            }
        }
        $this->info(($this->option('dry-run') ? 'Would delete ' : 'Deleted ')."{$n} file(s), ".round($bytes / 1024, 1).' KB.');

        return self::SUCCESS;
    }

    /** @return string[] */
    private function referenced(): array
    {
        $paths = [];
        foreach ([['images', 'path'], ['images', 'thumb_path'], ['brands', 'logo'], ['categories', 'image'], ['qr_codes', 'path'], ['product_videos', 'path'], ['product_videos', 'poster_path'], ['product_videos', 'poster_thumb_path']] as [$table, $col]) {
            DB::table($table)->whereNotNull($col)->pluck($col)->each(function ($p) use (&$paths) {
                $paths[] = $p;
            });
        }
        DB::table('banners')->whereNotNull('images')->pluck('images')->each(function ($json) use (&$paths) {
            foreach ((array) json_decode($json, true) as $p) {
                $paths[] = $p;
            }
        });

        return $paths;
    }
}
