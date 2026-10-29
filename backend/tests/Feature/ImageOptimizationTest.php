<?php

use App\Models\ImageOptimization;
use App\Services\Images\ExistingImageMigrator;
use App\Services\Images\ImageOptimizer;
use App\Services\Images\InvalidImageException;
use App\Services\ImageUploadService;
use App\Support\MediaPaths;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** Real GD-made fixture. $noise => incompressible pixels (forces extra quality/scale attempts). */
function imgFixture(string $type, int $w, int $h, bool $alpha = false, bool $noise = false): string
{
    $im = imagecreatetruecolor($w, $h);
    if ($alpha) {
        imagealphablending($im, false);
        imagesavealpha($im, true);
        imagefill($im, 0, 0, imagecolorallocatealpha($im, 0, 0, 0, 127));
        imagefilledellipse($im, intdiv($w, 2), intdiv($h, 2), intdiv($w, 2), intdiv($h, 2), imagecolorallocate($im, 200, 30, 90));
    } else {
        imagefill($im, 0, 0, imagecolorallocate($im, 240, 200, 180));
        if ($noise) {
            for ($y = 0; $y < $h; $y++) {
                for ($x = 0; $x < $w; $x++) {
                    imagesetpixel($im, $x, $y, imagecolorallocate($im, random_int(0, 255), random_int(0, 255), random_int(0, 255)));
                }
            }
        }
    }
    $f = tempnam(sys_get_temp_dir(), 'fx');
    match ($type) { 'jpg' => imagejpeg($im, $f, 95), 'png' => imagepng($im, $f), 'webp' => imagewebp($im, $f, 95), 'gif' => imagegif($im, $f) };

    return $f;
}

beforeEach(function () {
    Storage::fake('media');
    $this->opt = new ImageOptimizer;
});

it('has a real AVIF encoder in this PHP build', fn () => expect(ImageOptimizer::avifSupported())->toBeTrue());

it('converts JPEG to a valid AVIF within the profile limit and resizes keeping aspect ratio', function () {
    $r = $this->opt->optimize(imgFixture('jpg', 2400, 1200), 'product');
    expect($r->mime)->toBe('image/avif')->and($r->withinLimit())->toBeTrue()->and($r->size())->toBeLessThanOrEqual(102400)
        ->and([$r->width, $r->height])->toBe([1000, 500]);
    $f = tempnam(sys_get_temp_dir(), 'o');
    file_put_contents($f, $r->bytes);
    expect(getimagesize($f)['mime'])->toBe('image/avif');
});

it('never upscales small images', function () {
    $r = $this->opt->optimize(imgFixture('png', 120, 80), 'product');
    expect([$r->width, $r->height])->toBe([120, 80]);
    $t = $this->opt->optimize(imgFixture('png', 120, 80), 'thumb');
    expect([$t->width, $t->height])->toBe([80, 80]); // coverDown crops to the largest square, never stretches or upscales
});

it('keeps PNG transparency', function () {
    $r = $this->opt->optimize(imgFixture('png', 400, 400, alpha: true), 'logo');
    $im = imagecreatefromstring($r->bytes);
    expect((imagecolorat($im, 2, 2) >> 24) & 0x7F)->toBeGreaterThan(100); // corner still (mostly) transparent
});

it('retries with lower quality/scale until it fits, and flags what cannot fit', function () {
    config(['images.profiles.tight' => ['max_w' => 600, 'max_h' => 600, 'fit' => 'scale', 'max_bytes' => 60000]]);
    $noisy = imgFixture('png', 600, 600, noise: true);
    $r = $this->opt->optimize($noisy, 'tight');
    expect($r->attempts)->toBeGreaterThan(1)->and($r->withinLimit())->toBeTrue()->and($r->size())->toBeLessThanOrEqual(60000);

    config(['images.profiles.impossible' => ['max_w' => 600, 'max_h' => 600, 'fit' => 'scale', 'max_bytes' => 500]]);
    $bad = $this->opt->optimize($noisy, 'impossible');
    expect($bad->withinLimit())->toBeFalse()->and($bad->size())->toBeGreaterThan(500); // reported, never silently "optimized"
});

it('rejects corrupt, non-image, missing and animated input', function () {
    $txt = tempnam(sys_get_temp_dir(), 't');
    file_put_contents($txt, '<?php echo 1;');
    $png = tempnam(sys_get_temp_dir(), 't');
    file_put_contents($png, substr(file_get_contents(imgFixture('png', 50, 50)), 0, 40));
    $anim = tempnam(sys_get_temp_dir(), 't');
    file_put_contents($anim, 'GIF89a'."\x01\x00\x01\x00\x00\x00\x00".str_repeat("\x00\x21\xF9\x04\x00\x00\x00\x00\x00\x2C", 2));
    foreach ([$txt, $png, '/nonexistent.png'] as $bad) {
        expect(fn () => $this->opt->optimize($bad, 'product'))->toThrow(InvalidImageException::class);
    }
    expect(fn () => $this->opt->inspect($anim))->toThrow(InvalidImageException::class);
});

it('picks profiles from the stored path', function () {
    expect(ImageOptimizer::profileForPath('tenants/3/banners/2026/10/a.avif'))->toBe('banner')
        ->and(ImageOptimizer::profileForPath('catalog/categories/2026/10/a.webp'))->toBe('category')
        ->and(ImageOptimizer::profileForPath('catalog/products/2026/10/a_thumb.webp'))->toBe('thumb')
        ->and(ImageOptimizer::profileForPath('brands/x.png'))->toBe('logo')
        ->and(ImageOptimizer::profileForPath('weird/x.png'))->toBe('default');
});

it('uploads: AVIF + thumb + webp fallback for several files, valid fixtures only', function () {
    $svc = new ImageUploadService;
    $paths = collect([1, 2, 3])->map(fn ($i) => $svc->store(UploadedFile::fake()->createWithContent("p$i.jpg", file_get_contents(imgFixture('jpg', 900 + $i, 700))), 'products'));
    expect($paths->pluck('path')->unique())->toHaveCount(3);
    foreach ($paths as $p) {
        Storage::disk('media')->assertExists(MediaPaths::family($p['path']));
        expect(Storage::disk('media')->size($p['path']))->toBeLessThanOrEqual(102400)->and(Storage::disk('media')->size($p['thumb_path']))->toBeLessThanOrEqual(40960);
    }
    expect(fn () => $svc->store(UploadedFile::fake()->createWithContent('x.jpg', 'not an image'), 'products'))->toThrow(\Illuminate\Validation\ValidationException::class);
    expect(Storage::disk('media')->allFiles())->toHaveCount(12); // the rejected upload left nothing behind
});

it('MediaPaths treats avif/webp, main/thumb as one family', function () {
    expect(MediaPaths::family('a/b_thumb.webp'))->toEqualCanonicalizing(['a/b.avif', 'a/b_thumb.avif', 'a/b.webp', 'a/b_thumb.webp'])
        ->and(MediaPaths::thumbOf('a/b.avif'))->toBe('a/b_thumb.avif')
        ->and(MediaPaths::family(null))->toBe([]);
});

function seedLegacy(string $path, int $w = 900, int $h = 600): void
{
    Storage::disk('media')->put($path, file_get_contents(imgFixture('webp', $w, $h)));
}

it('migrates referenced images: original kept, refs untouched until --apply, then switched and rolled back exactly', function () {
    $this->seed(\Database\Seeders\FoundationSeeder::class);
    seedLegacy('catalog/products/2026/10/a.webp');
    seedLegacy('catalog/products/2026/10/a_thumb.webp', 400, 400);
    seedLegacy('tenants/1/banners/2026/10/b.webp', 1600, 700);
    DB::table('images')->insert(['imageable_type' => 'product', 'imageable_id' => 1, 'path' => 'catalog/products/2026/10/a.webp', 'thumb_path' => 'catalog/products/2026/10/a_thumb.webp', 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()]);
    $banner = DB::table('banners')->insertGetId(['tenant_id' => \App\Models\Tenant::first()->id, 'placement' => 'hero', 'title' => '{}', 'images' => json_encode(['ar' => 'tenants/1/banners/2026/10/b.webp', 'en' => 'missing/x.webp'], JSON_UNESCAPED_SLASHES), 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()]);

    $this->artisan('images:optimize-existing', ['--disk' => 'media', '--category' => ['images', 'banners']])->assertSuccessful();
    Storage::disk('media')->assertExists(['catalog/products/2026/10/a.avif', 'catalog/products/2026/10/a_thumb.avif', 'tenants/1/banners/2026/10/b.avif', 'catalog/products/2026/10/a.webp']);
    Storage::disk('media')->assertMissing('tenants/1/banners/2026/10/b.avif.tmp');
    expect(DB::table('images')->value('path'))->toEndWith('a.webp') // not applied yet
        ->and(ImageOptimization::where('status', 'optimized')->count())->toBe(3)
        ->and(ImageOptimization::where('source_path', 'missing/x.webp')->value('status'))->toBe('missing');

    $this->artisan('images:optimize-existing', ['--disk' => 'media', '--category' => ['images', 'banners'], '--apply' => true])->assertSuccessful();
    // second run is idempotent (no reconversion) but now applies
    expect(DB::table('images')->first())->path->toEndWith('a.avif')->thumb_path->toEndWith('a_thumb.avif');
    expect(json_decode(DB::table('banners')->where('id', $banner)->value('images'), true))->toBe(['ar' => 'tenants/1/banners/2026/10/b.avif', 'en' => 'missing/x.webp']);

    $before = ImageOptimization::count();
    $this->artisan('images:optimize-existing', ['--disk' => 'media', '--category' => ['images', 'banners'], '--apply' => true])->assertSuccessful(); // re-run changes nothing
    expect(ImageOptimization::count())->toBe($before)->and(DB::table('images')->value('path'))->toEndWith('a.avif');

    $this->artisan('images:optimize-existing', ['--disk' => 'media', '--category' => ['images', 'banners'], '--rollback' => true])->assertSuccessful();
    expect(DB::table('images')->value('path'))->toBe('catalog/products/2026/10/a.webp')
        ->and(json_decode(DB::table('banners')->where('id', $banner)->value('images'), true)['ar'])->toBe('tenants/1/banners/2026/10/b.webp');
    Storage::disk('media')->assertExists('catalog/products/2026/10/a.webp'); // originals never deleted
});

it('does not repoint references for images that exceed their limit', function () {
    config(['images.profiles.category' => ['max_w' => 600, 'max_h' => 600, 'fit' => 'scale', 'max_bytes' => 400]]);
    Storage::disk('media')->put('catalog/categories/2026/10/c.webp', file_get_contents(imgFixture('webp', 600, 600, noise: true)));
    DB::table('categories')->insert(['name' => '{}', 'slug' => 'cx', 'path' => '1', 'depth' => 0, 'image' => 'catalog/categories/2026/10/c.webp', 'created_at' => now(), 'updated_at' => now()]);
    $this->artisan('images:optimize-existing', ['--disk' => 'media', '--category' => ['categories'], '--apply' => true])->assertSuccessful();
    expect(ImageOptimization::first()->status)->toBe('exceeds_limit')
        ->and(DB::table('categories')->where('slug', 'cx')->value('image'))->toBe('catalog/categories/2026/10/c.webp');
    Storage::disk('media')->assertMissing('catalog/categories/2026/10/c.avif');
});

it('dry-run writes no files, ledger rows or DB changes', function () {
    seedLegacy('catalog/products/2026/10/d.webp');
    DB::table('images')->insert(['imageable_type' => 'product', 'imageable_id' => 1, 'path' => 'catalog/products/2026/10/d.webp', 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()]);
    $this->artisan('images:optimize-existing', ['--disk' => 'media', '--dry-run' => true, '--sample' => 1])->assertSuccessful();
    expect(ImageOptimization::count())->toBe(0)->and(Storage::disk('media')->allFiles())->toBe(['catalog/products/2026/10/d.webp']);
});

it('invalid sources are recorded, never converted or applied', function () {
    Storage::disk('media')->put('catalog/products/2026/10/bad.webp', 'garbage');
    DB::table('images')->insert(['imageable_type' => 'product', 'imageable_id' => 1, 'path' => 'catalog/products/2026/10/bad.webp', 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()]);
    $this->artisan('images:optimize-existing', ['--disk' => 'media', '--apply' => true])->assertSuccessful();
    expect(ImageOptimization::first()->status)->toBe('invalid')->and(DB::table('images')->value('path'))->toEndWith('bad.webp');
});

it('prune-orphans keeps the webp original of a referenced avif', function () {
    foreach (['a.avif', 'a_thumb.avif', 'a.webp', 'a_thumb.webp', 'orphan.webp'] as $f) {
        Storage::disk('media')->put("catalog/products/$f", 'x');
        touch(Storage::disk('media')->path("catalog/products/$f"), time() - 86400 * 3);
    }
    DB::table('images')->insert(['imageable_type' => 'product', 'imageable_id' => 1, 'path' => 'catalog/products/a.avif', 'thumb_path' => 'catalog/products/a_thumb.avif', 'sort_order' => 1, 'created_at' => now(), 'updated_at' => now()]);
    $this->artisan('media:prune-orphans')->assertSuccessful();
    Storage::disk('media')->assertExists(['catalog/products/a.avif', 'catalog/products/a.webp', 'catalog/products/a_thumb.webp']);
    Storage::disk('media')->assertMissing('catalog/products/orphan.webp');
});
