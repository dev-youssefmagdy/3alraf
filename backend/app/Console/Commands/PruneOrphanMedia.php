<?php

namespace App\Console\Commands;

use App\Services\ImageUploadService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneOrphanMedia extends Command
{
    protected $signature = 'media:prune-orphans {--dry-run : only list} {--min-age=24 : ignore files younger than N hours (in-flight uploads)}';

    protected $description = 'Find (and delete) files on the media disk under tenants/ and catalog/ that no DB row references (images, brand logos, category images, banners, QR codes).';

    public function handle(): int
    {
        $disk = ImageUploadService::disk();
        $used = array_flip($this->referenced());
        $cut = now()->subHours((int) $this->option('min-age'))->getTimestamp();
        $n = 0;
        $bytes = 0;
        foreach (['tenants', 'catalog'] as $root) {
            foreach ($disk->allFiles($root) as $file) {
                $base = preg_replace('/_thumb(\.webp)$/', '$1', $file);
                if (isset($used[$file]) || isset($used[$base]) || $disk->lastModified($file) > $cut) {
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
        foreach ([['images', 'path'], ['images', 'thumb_path'], ['brands', 'logo'], ['categories', 'image'], ['qr_codes', 'path']] as [$table, $col]) {
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
