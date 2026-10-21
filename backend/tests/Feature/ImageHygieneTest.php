<?php

use App\Models\Banner;
use App\Models\Brand;
use App\Models\Category;
use App\Models\CentralAdmin;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\TenantAdmin;
use App\Services\ImageUploadService;
use Database\Seeders\FoundationSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(FoundationSeeder::class);
    Storage::fake('media');
    Storage::fake('private');
    $this->c = 'http://3alraf.test/admin';
    $this->t = 'http://egy.3alraf.test/admin';
    $this->media = Storage::disk('media');
    $this->img = fn (string $n = 'a.jpg', int $w = 400) => UploadedFile::fake()->image($n, $w, $w);
    $this->files = fn () => collect($this->media->allFiles())->reject(fn ($f) => str_contains($f, '/qr/'))->values()->all();
    $this->central = fn () => $this->actingAs(CentralAdmin::first(), 'central');
    $this->tenantAdmin = fn () => $this->actingAs(TenantAdmin::withoutGlobalScopes()->where('email', 'owner@egy.test')->first(), 'tenant');
});

it('brand logo: replace deletes original + thumb, remove deletes, delete removes the file', function () {
    ($this->central)();
    $h = ['Accept' => 'application/json'];
    $this->post("{$this->c}/brands", ['name' => ['ar' => 'ب', 'en' => 'B'], 'is_active' => 1, 'logo' => ($this->img)()], $h)->assertOk();
    $b = Brand::first();
    $first = $b->logo;
    $this->media->assertExists([$first, str_replace('.webp', '_thumb.webp', $first)]);

    $this->post("{$this->c}/brands/{$b->id}", ['_method' => 'PUT', 'name' => ['ar' => 'ب'], 'is_active' => 1, 'logo' => ($this->img)('b.jpg', 300)], $h)->assertOk();
    $second = $b->fresh()->logo;
    expect($second)->not->toBe($first);
    $this->media->assertMissing([$first, str_replace('.webp', '_thumb.webp', $first)]);
    expect(($this->files)())->toHaveCount(2); // new original + thumb only

    $this->post("{$this->c}/brands/{$b->id}", ['_method' => 'PUT', 'name' => ['ar' => 'ب'], 'is_active' => 1, 'remove_logo' => 1], $h)->assertOk();
    expect($b->fresh()->logo)->toBeNull()->and(($this->files)())->toBe([]);

    $this->post("{$this->c}/brands/{$b->id}", ['_method' => 'PUT', 'name' => ['ar' => 'ب'], 'is_active' => 1, 'logo' => ($this->img)()], $h)->assertOk();
    expect(($this->files)())->toHaveCount(2);
    $this->deleteJson("{$this->c}/brands/{$b->id}")->assertOk();
    expect(($this->files)())->toBe([])->and(Brand::withTrashed()->find($b->id)->logo)->toBeNull();
});

it('category image: replace / remove / delete leave no files behind', function () {
    ($this->central)();
    $h = ['Accept' => 'application/json'];
    $this->post("{$this->c}/categories", ['name' => ['ar' => 'ق'], 'is_active' => 1, 'image' => ($this->img)()], $h)->assertOk();
    $cat = Category::first();
    $first = $cat->image;
    $this->post("{$this->c}/categories/{$cat->id}", ['_method' => 'PUT', 'name' => ['ar' => 'ق'], 'image' => ($this->img)('x.jpg', 500)], $h)->assertOk();
    $this->media->assertMissing($first);
    expect(($this->files)())->toHaveCount(2);
    $this->post("{$this->c}/categories/{$cat->id}", ['_method' => 'PUT', 'name' => ['ar' => 'ق'], 'remove_image' => 1], $h)->assertOk();
    expect(($this->files)())->toBe([]);
    $this->post("{$this->c}/categories/{$cat->id}", ['_method' => 'PUT', 'name' => ['ar' => 'ق'], 'image' => ($this->img)()], $h)->assertOk();
    $this->deleteJson("{$this->c}/categories/{$cat->id}")->assertOk();
    expect(($this->files)())->toBe([]);
});

it('product images: remove ids deletes rows + files, variant image replacement drops the old one', function () {
    ($this->central)();
    $h = ['Accept' => 'application/json'];
    $cat = Category::create(['name' => ['ar' => 'c'], 'slug' => 'c', 'path' => '1', 'depth' => 0]);
    $base = ['type' => 'simple', 'name' => ['ar' => 'س'], 'sku' => 'HG-1', 'category_ids' => [$cat->id], 'is_active' => 1];
    $this->post("{$this->c}/products", $base + ['images' => [($this->img)('a.jpg', 300), ($this->img)('b.jpg', 301)]], $h)->assertOk();
    $p = Product::with('images')->first();
    expect($p->images)->toHaveCount(2)->and(($this->files)())->toHaveCount(4);
    $gone = $p->images[0];
    $keep = $p->images[1];
    $this->post("{$this->c}/products/{$p->id}", $base + ['_method' => 'PUT', 'remove_images' => [$gone->id]], $h)->assertOk();
    $this->media->assertMissing([$gone->path, $gone->thumb_path]);
    $this->media->assertExists([$keep->path, $keep->thumb_path]);
    expect($p->fresh()->images)->toHaveCount(1)->and(\App\Models\Image::count())->toBe(1);

    // variant image: uploading a new one removes the previous row + files
    $svc = app(ImageUploadService::class);
    $variant = $p->variants()->create(['combination_hash' => 'x', 'sku' => 'V1', 'barcode' => '2000000000039', 'is_active' => true]);
    $svc->attach($variant, [($this->img)('v1.jpg', 200)], 'variants');
    $old = $variant->images()->first();
    $ps = app(\App\Services\ProductService::class);
    $m = new ReflectionMethod($ps, 'attachVariantImage');
    $row = new \App\DTOs\ProductVariantData(null, [], 'V1', null, true, ($this->img)('v2.jpg', 210));
    $m->invoke($ps, $variant, $row);
    $this->media->assertMissing([$old->path, $old->thumb_path]);
    expect($variant->images()->count())->toBe(1);
    $this->media->assertExists($variant->images()->first()->path);
})->skip(! (new ReflectionClass(\App\DTOs\ProductVariantData::class))->isInstantiable());

it('deleting an image row removes both files; two owners uploading the same picture never share a file', function () {
    $svc = app(ImageUploadService::class);
    $f = ($this->img)('same.jpg', 350);
    $a = $svc->store($f, 'products');
    $b = $svc->store($f, 'products');
    expect($a['path'])->not->toBe($b['path']);
    $svc->deletePath($a['path']);
    $this->media->assertMissing([$a['path'], $a['thumb_path']]);
    $this->media->assertExists([$b['path'], $b['thumb_path']]);
});

it('a failed upload keeps the old file (new is stored before the old is deleted)', function () {
    $svc = app(ImageUploadService::class);
    $old = $svc->store(($this->img)(), 'brands')['path'];
    $bad = UploadedFile::fake()->createWithContent('broken.jpg', 'not an image');
    $failed = false;
    try {
        $svc->storeSingle($bad, 'brands', $old);
    } catch (Throwable) {
        $failed = true;
    }
    expect($failed)->toBeTrue();
    $this->media->assertExists($old);
});

it('banner images per locale: replace, remove one locale, delete all', function () {
    ($this->tenantAdmin)();
    $h = ['Accept' => 'application/json'];
    $base = ['placement' => 'home_hero', 'title' => ['ar' => 'ب'], 'is_active' => 1, 'sort_order' => 0];
    $this->post("{$this->t}/banners", $base + ['images' => ['ar' => ($this->img)('1.jpg', 300), 'en' => ($this->img)('2.jpg', 310)]], $h)->assertOk();
    $b = Banner::first();
    $ar = $b->images['ar'];
    $en = $b->images['en'];
    expect(($this->files)())->toHaveCount(4);

    $this->post("{$this->t}/banners/{$b->id}", $base + ['_method' => 'PUT', 'images' => ['ar' => ($this->img)('3.jpg', 320)]], $h)->assertOk();
    $b->refresh();
    $this->media->assertMissing([$ar, str_replace('.webp', '_thumb.webp', $ar)]);
    expect($b->images['ar'])->not->toBe($ar)->and($b->images['en'])->toBe($en)->and(($this->files)())->toHaveCount(4);

    $this->post("{$this->t}/banners/{$b->id}", $base + ['_method' => 'PUT', 'remove_images' => ['en' => 1]], $h)->assertOk();
    $this->media->assertMissing([$en, str_replace('.webp', '_thumb.webp', $en)]);
    expect(array_keys($b->fresh()->images))->toBe(['ar'])->and(($this->files)())->toHaveCount(2);

    $this->deleteJson("{$this->t}/banners/{$b->id}")->assertOk();
    expect(($this->files)())->toBe([]);
});

it('expense receipts (private disk): replace, remove and delete leave no file', function () {
    ($this->tenantAdmin)();
    $h = ['Accept' => 'application/json'];
    $cat = ExpenseCategory::create(['tenant_id' => Tenant::where('slug', 'egy')->value('id'), 'name' => ['ar' => 'ع'], 'is_active' => true]);
    $base = ['expense_category_id' => $cat->id, 'amount' => '10', 'expense_date' => now()->toDateString()];
    $pdf = fn ($n) => UploadedFile::fake()->create($n, 20, 'application/pdf');
    $this->post("{$this->t}/expenses", $base + ['attachment' => $pdf('a.pdf')], $h)->assertOk();
    $e = Expense::first();
    $first = $e->attachment;
    Storage::disk('private')->assertExists($first);
    $this->post("{$this->t}/expenses/{$e->id}", $base + ['_method' => 'PUT', 'attachment' => $pdf('b.pdf')], $h)->assertOk();
    Storage::disk('private')->assertMissing($first);
    expect(Storage::disk('private')->allFiles())->toHaveCount(1);
    $this->post("{$this->t}/expenses/{$e->id}", $base + ['_method' => 'PUT', 'remove_attachment' => 1], $h)->assertOk();
    expect(Storage::disk('private')->allFiles())->toBe([]);
    $this->post("{$this->t}/expenses/{$e->id}", $base + ['_method' => 'PUT', 'attachment' => $pdf('c.pdf')], $h)->assertOk();
    $this->deleteJson("{$this->t}/expenses/{$e->id}")->assertOk();
    expect(Storage::disk('private')->allFiles())->toBe([]);
});

it('media:prune-orphans lists unreferenced files and only deletes them without --dry-run', function () {
    $svc = app(ImageUploadService::class);
    $kept = $svc->store(($this->img)(), 'brands');
    $b = Brand::create(['name' => ['ar' => 'ب'], 'slug' => 'b', 'logo' => $kept['path'], 'is_active' => true]);
    $orphan = $svc->store(($this->img)('o.jpg', 333), 'brands');
    $this->media->put('tenants/1/qr/products/p1-aaaa.png', 'x'); // unreferenced qr file
    foreach ([$orphan['path'], $orphan['thumb_path'], 'tenants/1/qr/products/p1-aaaa.png'] as $f) {
        touch(Storage::disk('media')->path($f), time() - 3 * 86400);
    }

    $this->artisan('media:prune-orphans', ['--dry-run' => true])->expectsOutputToContain($orphan['path'])->assertSuccessful();
    $this->media->assertExists([$orphan['path'], $orphan['thumb_path']]);
    $this->artisan('media:prune-orphans')->assertSuccessful();
    $this->media->assertMissing([$orphan['path'], $orphan['thumb_path'], 'tenants/1/qr/products/p1-aaaa.png']);
    $this->media->assertExists([$b->logo, $kept['thumb_path']]);
});
