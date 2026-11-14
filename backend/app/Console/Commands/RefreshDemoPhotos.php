<?php

namespace App\Console\Commands;

use App\Models\Banner;
use App\Models\Category;
use App\Models\Image;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\Support\PlaceholderImage;
use Database\Seeders\Support\SeedImage;
use Illuminate\Console\Command;

/**
 * Existing installs seeded before the bundled photos existed still show gradient name cards for the demo catalog.
 * Rewrites only those seeded files, in place (same paths, so no database change): demo products `catalog/products/{slug}-{n}`,
 * categories `catalog/categories/{slug}` and demo banners `banners/demo-{i}-{locale}` that have a photo in
 * database/seeders/assets/stock. Admin uploads and the real gift catalog use other paths and are never touched.
 */
class RefreshDemoPhotos extends Command
{
    protected $signature = 'catalog:demo-photos {--dry-run : List what would change}';

    protected $description = 'Replace the seeded demo catalog gradient cards with the bundled free photos';

    public function handle(): int
    {
        $targets = [];
        foreach (Image::query()->where('path', 'like', 'catalog/products/%.avif')->where('path', 'not like', 'catalog/products/%/%')->pluck('path') as $p) {
            $targets[] = ['catalog/products', basename($p, '.avif'), 'product', true];
        }
        // Real catalogs reuse slugs like "audio" or "moisturizers" (even under a "skin-care" parent): only a category whose whole
        // slug path matches the demo tree (e.g. beauty/skin-care/moisturizers) is a demo one.
        $demoPaths = self::demoPaths(CatalogSeeder::TREE);
        $slugs = Category::query()->pluck('slug', 'id');
        foreach (Category::query()->where('image', 'like', 'catalog/categories/%.avif')->get(['id', 'slug', 'path', 'image']) as $c) {
            $path = collect(explode('/', (string) $c->path))->map(fn ($id) => $slugs[(int) $id] ?? '?')->implode('/');
            if (basename($c->image, '.avif') === $c->slug && ($demoPaths[$c->slug] ?? null) === $path) {
                $targets[] = ['catalog/categories', $c->slug, 'product', true];
            }
        }
        foreach (Banner::query()->get(['images']) as $b) {
            foreach ((array) $b->images as $p) {
                if (is_string($p) && preg_match('#^banners/(demo-\d+-[a-z-]+)\.avif$#', $p, $m)) {
                    $targets[] = ['banners', $m[1], 'banner', false];
                }
            }
        }

        $done = 0;
        foreach (array_unique($targets, SORT_REGULAR) as [$dir, $key, $profile, $thumb]) {
            $img = PlaceholderImage::stock($dir, $key);
            if (! $img) {
                continue;
            }
            $this->line(($this->option('dry-run') ? 'would replace ' : 'replaced ')."$dir/$key");
            if (! $this->option('dry-run')) {
                SeedImage::save($img, $dir, $key, $profile, $thumb);
            }
            $done++;
        }
        $this->info($done.($this->option('dry-run') ? ' would be replaced.' : ' replaced.'));

        return self::SUCCESS;
    }

    /** @return array<string, string> demo category slug => slug path from the top, e.g. beauty/skin-care/moisturizers */
    private static function demoPaths(array $nodes, string $prefix = ''): array
    {
        $out = [];
        foreach ($nodes as $slug => $n) {
            $out[$slug] = $prefix.$slug;
            $out += isset($n[2]) ? self::demoPaths($n[2], $prefix.$slug.'/') : [];
        }

        return $out;
    }
}
