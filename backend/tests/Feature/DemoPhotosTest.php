<?php

// Bundled free photos for the demo catalog (database/seeders/assets/stock) and `catalog:demo-photos`, which swaps old gradient cards
// for them in place — never touching real catalog files that share a slug (e.g. a real beauty-care/skin-care/moisturizers category).

use App\Models\Banner;
use App\Models\Category;
use App\Models\Image;
use App\Models\Product;
use Database\Seeders\Support\PlaceholderImage;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake(config('media.disk'));
    $this->disk = Storage::disk(config('media.disk'));
    $this->cat = function (string $slug, ?Category $parent = null): Category {
        $c = Category::create(['slug' => $slug, 'name' => ['ar' => $slug, 'en' => $slug], 'parent_id' => $parent?->id, 'depth' => $parent ? $parent->depth + 1 : 0, 'path' => '']);
        $c->forceFill(['path' => ($parent ? $parent->path.'/' : '').$c->id, 'image' => "catalog/categories/$slug.avif"])->save();

        return $c;
    };
});

it('finds a bundled photo only for demo catalog keys, all CC0 or public domain', function () {
    expect(PlaceholderImage::stockFile('catalog/products', 'evening-dress-0'))->toEndWith('stock/products/evening-dress-0.webp')
        ->and(PlaceholderImage::stockFile('catalog/categories', 'fashion'))->toEndWith('stock/categories/fashion.webp')
        ->and(PlaceholderImage::stockFile('banners', 'demo-3-en'))->toEndWith('stock/banners/3.webp')
        ->and(PlaceholderImage::stockFile('catalog/products/gifts', 'placeholder-pure-glycerin-100ml'))->toBeNull()   // real catalog: never a stock photo
        ->and(PlaceholderImage::stockFile('catalog/products', 'no-such-product-0'))->toBeNull();

    $sources = json_decode(file_get_contents(database_path('seeders/assets/stock/SOURCES.json')), true)['images'];
    $files = collect(['products', 'categories', 'banners'])->flatMap(fn ($b) => collect(glob(database_path("seeders/assets/stock/$b/*.webp")))->map(fn ($f) => "$b/".basename($f, '.webp')));
    expect($files->sort()->values()->all())->toBe(collect(array_keys($sources))->sort()->values()->all())
        ->and(collect($sources)->pluck('license')->unique()->diff(['cc0', 'public domain'])->all())->toBe([]);
});

it('seeds a demo product image from the bundled photo through the normal image pipeline', function () {
    $r = PlaceholderImage::make('catalog/products', 'evening-dress-0', 'Evening Dress');
    expect($r)->toBe(['path' => 'catalog/products/evening-dress-0.avif', 'thumb_path' => 'catalog/products/evening-dress-0_thumb.avif'])
        ->and($this->disk->exists($r['path']))->toBeTrue()->and($this->disk->exists($r['thumb_path']))->toBeTrue();
});

it('replaces only demo catalog gradient files, never real catalog files sharing a slug', function () {
    // demo: beauty/skin-care/moisturizers (3 categories), a demo product image and a demo banner (2 locales) = 6 files
    $beauty = ($this->cat)('beauty');
    $demo = ($this->cat)('moisturizers', ($this->cat)('skin-care', $beauty));
    $p = Product::create(['type' => 'simple', 'name' => ['en' => 'Evening Dress'], 'slug' => 'evening-dress', 'sku' => 'ED-1', 'barcode' => '9990000000017']);
    $p->images()->create(['path' => 'catalog/products/evening-dress-0.avif', 'thumb_path' => 'catalog/products/evening-dress-0_thumb.avif', 'sort_order' => 0]);
    $gift = Product::create(['type' => 'simple', 'name' => ['en' => 'Pure Glycerin'], 'slug' => 'pure-glycerin', 'sku' => 'PG-1', 'barcode' => '9990000000024']);
    $gift->images()->create(['path' => 'catalog/products/gifts/placeholder-pure-glycerin-100ml.avif', 'thumb_path' => 'catalog/products/gifts/placeholder-pure-glycerin-100ml_thumb.avif', 'sort_order' => 0]);
    Banner::create(['placement' => 'home_hero', 'title' => ['en' => 'x'], 'images' => ['ar' => 'banners/demo-3-ar.avif', 'en' => 'banners/demo-3-en.avif'], 'is_active' => true]);
    // real: "audio" under a real parent (the demo tree has it under tvs)
    $real = ($this->cat)('audio', ($this->cat)('gadgets'));

    $files = ['catalog/categories/moisturizers.avif', 'catalog/products/evening-dress-0.avif', 'banners/demo-3-ar.avif', 'banners/demo-3-en.avif', 'catalog/categories/audio.avif', 'catalog/products/gifts/placeholder-pure-glycerin-100ml.avif'];
    foreach ($files as $f) {
        $this->disk->put($f, 'old');
    }

    $this->artisan('catalog:demo-photos', ['--dry-run' => true])->expectsOutputToContain('6 would be replaced.')->assertSuccessful();
    expect(collect($files)->map(fn ($f) => $this->disk->get($f))->unique()->all())->toBe(['old']);   // dry run writes nothing

    $this->artisan('catalog:demo-photos')->expectsOutputToContain('6 replaced.')->assertSuccessful();
    foreach (['catalog/categories/moisturizers.avif', 'catalog/products/evening-dress-0.avif', 'banners/demo-3-ar.avif', 'banners/demo-3-en.avif'] as $f) {
        expect($this->disk->get($f))->not->toBe('old');
    }
    expect($this->disk->get('catalog/categories/audio.avif'))->toBe('old')
        ->and($this->disk->get('catalog/products/gifts/placeholder-pure-glycerin-100ml.avif'))->toBe('old')
        ->and(Image::count())->toBe(2)->and($demo->fresh()->image)->toBe('catalog/categories/moisturizers.avif')->and($real->fresh()->image)->toBe('catalog/categories/audio.avif');
});
