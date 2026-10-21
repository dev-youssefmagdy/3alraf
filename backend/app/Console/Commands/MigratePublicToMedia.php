<?php

namespace App\Console\Commands;

use App\Models\Banner;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Expense;
use App\Models\Image;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class MigratePublicToMedia extends Command
{
    protected $signature = 'media:migrate-public-to-media {--move : delete the source after copying} {--dry-run}';

    protected $description = 'Copy files from storage/app/public to the media disk (expense receipts go to the private disk). Paths in the DB stay valid.';

    public function handle(): int
    {
        $src = Storage::disk('public');
        $media = Storage::disk(config('media.disk'));
        $private = Storage::disk(config('media.private_disk'));
        $receipts = Expense::withoutGlobalScopes()->whereNotNull('attachment')->pluck('attachment')->flip();
        $n = $skipped = 0;
        foreach ($src->allFiles() as $file) {
            $dest = isset($receipts[$file]) ? $private : $media;
            if ($dest->exists($file)) {
                $skipped++;
            } elseif (! $this->option('dry-run')) {
                $stream = $src->readStream($file);
                $dest->writeStream($file, $stream);
                is_resource($stream) && fclose($stream);
                $n++;
            } else {
                $n++;
            }
            if ($this->option('move') && ! $this->option('dry-run') && $dest->exists($file)) {
                $src->delete($file);
            }
        }
        $this->info(($this->option('dry-run') ? '[dry-run] would copy ' : 'copied ')."$n file(s), $skipped already present.");
        // referenced-but-missing report
        $missing = collect([Image::withoutGlobalScopes()->pluck('path'), Brand::withoutGlobalScopes()->whereNotNull('logo')->pluck('logo'), Category::withoutGlobalScopes()->whereNotNull('image')->pluck('image')])
            ->flatten()->filter(fn ($p) => ! $media->exists($p))->count();
        $missing && $this->warn("$missing referenced image(s) not found on the media disk.");

        return self::SUCCESS;
    }
}
