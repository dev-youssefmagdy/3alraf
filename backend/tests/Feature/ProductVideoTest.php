<?php

// P8 (MARKETER_PLAN.md, Decision 6): public product videos — upload validation, permissions, shared image/video ordering, storefront slider markup, API v1 `media[]`,
// file housekeeping, and no leakage of the private social-media library.

use App\Models\Admin;
use App\Models\Product;
use App\Models\ProductVideo;
use App\Models\SocialMediaAsset;
use App\Services\BarcodeService;
use App\Services\ImageUploadService;
use App\Services\Retention\SoftDeletePurger;
use App\Services\Storefront\ProductGallery;
use Database\Seeders\FoundationSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(FoundationSeeder::class);
    Cache::flush();
    Storage::fake(config('media.disk'));
    Storage::fake(config('media.private_disk'));
    config(['app.url' => 'http://3alraf.test', 'app.host' => '3alraf.test']);
    $this->owner = Admin::query()->where('email', 'owner@egy.test')->first();
    $this->staff = function (array $perms, string $email = 's@egy.test') {
        $a = Admin::create(['name' => 'S', 'email' => $email, 'password' => 'password', 'is_active' => true]);
        $perms && $a->givePermissionTo($perms);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $a->fresh();
    };
    $this->makeProduct = fn (string $slug) => listProduct(Product::create(['type' => 'simple', 'name' => ['en' => 'Item '.$slug, 'ar' => 'صنف'], 'description' => ['en' => 'x'], 'slug' => $slug, 'sku' => 'SKU-'.$slug,
        'barcode' => app(BarcodeService::class)->generateUnique()]), ['price' => '100', 'cost' => '60', 'is_active' => true]);
    $this->product = ($this->makeProduct)('with-video');
    // real UploadedFiles (not UploadedFile::fake()) so MIME detection reads the bytes, as in production
    $this->file = function (string $name, string $bytes, ?int $error = null) {
        file_put_contents($tmp = tempnam(sys_get_temp_dir(), 'vid'), $bytes);

        return new UploadedFile($tmp, $name, null, $error, true);
    };
    $this->mp4Bytes = "\x00\x00\x00\x18ftypisom\x00\x00\x02\x00isomiso2mp41".str_repeat("\x00", 4096);
    $this->webmBytes = "\x1A\x45\xDF\xA3\x9F\x42\x86\x81\x01\x42\xF7\x81\x01\x42\xF2\x81\x04\x42\xF3\x81\x08\x42\x82\x84webm\x42\x87\x81\x04\x42\x85\x81\x02".str_repeat("\x00", 4096);
    $this->mp4 = fn (string $name = 'clip.mp4') => ($this->file)($name, $this->mp4Bytes);
    $this->post_ = fn (Product $p, array $data) => $this->post("/admin/products/{$p->id}/videos", $data, ['Accept' => 'application/json']);
    $this->disk = fn () => Storage::disk(config('media.disk'));
    $this->addImage = fn (Product $p, int $order) => $p->images()->create(['path' => "catalog/products/img$order.avif", 'thumb_path' => "catalog/products/img{$order}_thumb.avif", 'sort_order' => $order]);
    $this->addVideo = fn (Product $p, int $order, array $extra = []) => ProductVideo::create(['product_id' => $p->id, 'path' => "catalog/videos/v$order.mp4", 'mime' => 'video/mp4', 'size_bytes' => 10, 'sort_order' => $order] + $extra);
});

it('stores a valid mp4 publicly under a server-chosen name, after the existing media, and returns the re-rendered card', function () {
    $this->actingAs($this->owner, 'admin');
    ($this->addImage)($this->product, 1);
    ($this->addImage)($this->product, 2);

    $r = ($this->post_)($this->product, ['video' => ($this->mp4)('../../evil name.mp4')])->assertOk();

    $v = ProductVideo::sole();
    expect($v->product_id)->toBe($this->product->id)->and($v->mime)->toBe('video/mp4')->and($v->sort_order)->toBe(3)->and($v->poster_path)->toBeNull()
        ->and($v->path)->toMatch('#^catalog/videos/\d{4}/\d{2}/[a-z0-9]{40}\.mp4$#')
        ->and($v->url)->toBe(media_url($v->path))
        ->and($r->json('data.html'))->toContain('data-product-media')->toContain('video:'.$v->id);
    ($this->disk)()->assertExists($v->path);
    expect(Storage::disk(config('media.private_disk'))->allFiles())->toBe([]);
});

it('accepts webm, names the file from its content, and processes an optional poster', function () {
    $this->actingAs($this->owner, 'admin');
    ($this->post_)($this->product, ['video' => ($this->file)('really-webm.mp4', $this->webmBytes), 'poster' => UploadedFile::fake()->image('p.jpg', 600, 600)])->assertOk();

    $v = ProductVideo::sole();
    expect($v->mime)->toBe('video/webm')->and($v->path)->toEndWith('.webm')->and($v->poster_path)->toEndWith('.avif')->and($v->poster_thumb_path)->toEndWith('_thumb.avif');
    ($this->disk)()->assertExists([$v->path, $v->poster_path, $v->poster_thumb_path]);
});

it('refuses wrong types with a clear message', function (callable $make) {
    $this->actingAs($this->owner, 'admin');
    ($this->post_)($this->product, ['video' => $make($this)])->assertStatus(422)->assertJsonPath('errors.video.0', __('admin.product_media.bad_type'));
    expect(ProductVideo::count())->toBe(0)->and(($this->disk)()->allFiles())->toBe([]);
})->with([
    'php disguised as mp4' => [fn ($t) => ($t->file)('shell.mp4', '<?php system($_GET["c"]); ?>'.str_repeat(' ', 2048))],
    'html disguised as webm' => [fn ($t) => ($t->file)('x.webm', '<html><script>alert(1)</script></html>')],
    'real mp4 with a bad extension' => [fn ($t) => ($t->file)('clip.php', $t->mp4Bytes)],
    'image' => [fn ($t) => UploadedFile::fake()->image('a.jpg')],
]);

it('requires a file and refuses a bad poster', function () {
    $this->actingAs($this->owner, 'admin');
    ($this->post_)($this->product, [])->assertStatus(422)->assertJsonPath('errors.video.0', __('admin.product_media.required'));
    ($this->post_)($this->product, ['video' => ($this->mp4)(), 'poster' => ($this->file)('p.png', '<svg xmlns="http://www.w3.org/2000/svg"></svg>')])->assertStatus(422)->assertJsonValidationErrors('poster');
    expect(ProductVideo::count())->toBe(0);
});

it('shows a clear error when the file is over the limit or PHP refused it', function () {
    $this->actingAs($this->owner, 'admin');
    config(['media.product_video.max_mb' => 1]);

    ($this->post_)($this->product, ['video' => ($this->file)('big.mp4', $this->mp4Bytes.str_repeat("\x00", 1024 * 1024 + 1))])->assertStatus(422)->assertJsonPath('errors.video.0', __('admin.product_media.too_large', ['mb' => 1]));
    ($this->post_)($this->product, ['video' => ($this->file)('ini.mp4', $this->mp4Bytes, UPLOAD_ERR_INI_SIZE)])->assertStatus(422)->assertJsonPath('errors.video.0', __('admin.product_media.too_large', ['mb' => 1]));
    expect(ProductVideo::count())->toBe(0);
});

it('never advertises more than PHP itself can accept', function () {
    config(['media.product_video.max_mb' => 1000000]);
    expect(\App\Services\ProductVideoService::maxKb() * 1024)->toBeLessThanOrEqual((int) UploadedFile::getMaxFilesize());
});

it('caps the number of videos per product', function () {
    $this->actingAs($this->owner, 'admin');
    config(['media.product_video.max_per_product' => 1]);
    ($this->post_)($this->product, ['video' => ($this->mp4)()])->assertOk();
    ($this->post_)($this->product, ['video' => ($this->mp4)()])->assertStatus(422)->assertJsonPath('errors.video.0', __('admin.product_media.limit_reached', ['max' => 1]));
});

it('deletes a video with its files and keeps the routes scoped to the product', function () {
    $this->actingAs($this->owner, 'admin');
    ($this->post_)($this->product, ['video' => ($this->mp4)(), 'poster' => UploadedFile::fake()->image('p.jpg', 600, 600)])->assertOk();
    $v = ProductVideo::sole();
    $other = ($this->makeProduct)('other');

    $this->deleteJson("/admin/products/{$other->id}/videos/{$v->id}")->assertNotFound();
    expect(ProductVideo::count())->toBe(1);

    $this->deleteJson("/admin/products/{$this->product->id}/videos/{$v->id}")->assertOk();
    expect(ProductVideo::count())->toBe(0)->and(($this->disk)()->allFiles())->toBe([]);
});

it('enforces products.edit on upload, delete and reorder', function () {
    $v = ($this->addVideo)($this->product, 1);
    $id = $this->product->id;
    $calls = fn () => [
        $this->post("/admin/products/$id/videos", ['video' => ($this->mp4)()], ['Accept' => 'application/json']),
        $this->deleteJson("/admin/products/$id/videos/{$v->id}"),
        $this->postJson("/admin/products/$id/media/order", ['order' => ["video:{$v->id}"]]),
    ];

    foreach ($calls() as $guest) {
        expect($guest->status())->toBe(401);
    }
    $this->actingAs(($this->staff)(['products.view']), 'admin');
    foreach ($calls() as $viewer) {
        $viewer->assertForbidden();
    }
    expect(ProductVideo::whereKey($v->id)->exists())->toBeTrue();

    auth('admin')->logout();
    $this->actingAs(($this->staff)(['products.edit'], 'e@egy.test'), 'admin');
    [$upload, $delete] = $calls();
    $upload->assertOk();
    $delete->assertOk();
});

it('shows the media card on the product edit page in both languages', function () {
    $this->actingAs($this->owner, 'admin');
    ($this->addVideo)($this->product, 1);
    $this->get("/admin/products/{$this->product->id}/edit")->assertOk()->assertSee('data-product-media', false)->assertSee('data-media-delete', false);
    $this->get('/admin/products/create')->assertOk()->assertDontSee('data-product-media', false);
});

it('shares one order between images and videos', function () {
    $this->actingAs($this->owner, 'admin');
    $a = ($this->addImage)($this->product, 1);
    $b = ($this->addImage)($this->product, 2);
    $v = ($this->addVideo)($this->product, 3);
    $this->postJson("/admin/products/{$this->product->id}/media/order", ['order' => ["video:{$v->id}", "image:{$b->id}", "image:{$a->id}"]])->assertOk();

    $items = app(ProductGallery::class)->items($this->product->fresh()->load(['images', 'videos']));
    expect($items->pluck('key')->all())->toBe(["video:{$v->id}", "image:{$b->id}", "image:{$a->id}"])->and($items->pluck('sort_order')->all())->toBe([0, 1, 2])
        ->and($this->product->fresh()->images->pluck('id')->all())->toBe([$b->id, $a->id]);   // the plain image list follows
});

it('refuses an order that does not list exactly the product\'s own media', function () {
    $this->actingAs($this->owner, 'admin');
    $a = ($this->addImage)($this->product, 1);
    $v = ($this->addVideo)($this->product, 2);
    $other = ($this->addVideo)(($this->makeProduct)('other'), 9);
    $url = "/admin/products/{$this->product->id}/media/order";

    foreach ([["image:{$a->id}"], ["image:{$a->id}", "video:{$other->id}"], ["image:{$a->id}", "video:{$v->id}", "video:{$other->id}"], ["image:{$a->id}", "image:{$a->id}"], ['audio:1']] as $bad) {
        $this->postJson($url, ['order' => $bad])->assertStatus(422);
    }
    expect($other->fresh()->sort_order)->toBe(9)->and($v->fresh()->sort_order)->toBe(2);
});

it('adds images uploaded through the product form after the existing videos', function () {
    $this->actingAs($this->owner, 'admin');
    ($this->addImage)($this->product, 1);
    $v = ($this->addVideo)($this->product, 5);
    app(ImageUploadService::class)->attach($this->product, [UploadedFile::fake()->image('n.jpg', 500, 500)], 'products', app(\App\Repositories\Contracts\ProductVideoRepositoryInterface::class)->lastMediaOrder($this->product));

    expect(app(ProductGallery::class)->items($this->product->fresh()->load(['images', 'videos']))->pluck('type')->all())->toBe(['image', 'video', 'image'])
        ->and($this->product->fresh()->images->last()->sort_order)->toBeGreaterThan($v->sort_order);
});

it('renders the mixed slider for a product with a video', function () {
    ($this->addVideo)($this->product, 1, ['poster_path' => 'catalog/products/poster.avif', 'poster_thumb_path' => 'catalog/products/poster_thumb.avif']);
    ($this->addImage)($this->product, 2);
    ($this->addVideo)($this->product, 3);

    foreach (['ar', 'en'] as $locale) {
        $html = $this->get("/$locale/product/with-video")->assertOk()->getContent();
        expect(substr_count($html, 'data-gallery-slide'))->toBe(3)->and(substr_count($html, 'data-gallery-thumb='))->toBe(3)->and(substr_count($html, 'data-video-badge'))->toBe(2)
            ->and(preg_match_all('/<video[^>]*\bmuted\b[^>]*\bplaysinline\b[^>]*>/', $html))->toBe(2)
            ->and($html)->toContain('preload="metadata"')->toContain('preload="none"')->toContain('poster="'.media_url('catalog/products/poster.avif').'"')->not->toContain('data-main-image');
    }
});

it('leaves a product without videos exactly on the plain gallery', function () {
    ($this->addImage)($this->product, 1);
    ($this->addImage)($this->product, 2);
    $this->get('/en/product/with-video')->assertOk()->assertSee('data-main-image', false)->assertSee('data-thumb=', false)->assertDontSee('data-product-gallery', false)->assertDontSee('<video', false);
});

it('adds media[] to the product API and keeps images[] unchanged', function () {
    $a = ($this->addImage)($this->product, 2);
    $v = ($this->addVideo)($this->product, 1, ['poster_path' => 'catalog/products/poster.avif', 'poster_thumb_path' => 'catalog/products/poster_thumb.avif']);
    $b = ($this->addImage)($this->product, 3);

    $d = $this->getJson('/api/v1/products/with-video')->assertOk()->json('data');

    expect($d['images'])->toBe([['url' => $a->url, 'thumb' => $a->thumb_url], ['url' => $b->url, 'thumb' => $b->thumb_url]])
        ->and(array_column($d['media'], 'type'))->toBe(['video', 'image', 'image'])
        ->and(array_column($d['media'], 'sort_order'))->toBe([0, 1, 2])
        ->and($d['media'][0])->toBe(['id' => $v->id, 'type' => 'video', 'url' => $v->url, 'thumb' => $v->poster_thumb_url, 'poster' => $v->poster_url, 'mime' => 'video/mp4', 'sort_order' => 0])
        ->and($d['media'][1])->toBe(['id' => $a->id, 'type' => 'image', 'url' => $a->url, 'thumb' => $a->thumb_url, 'poster' => null, 'mime' => null, 'sort_order' => 1]);
});

it('returns an empty media[] for a product without media', function () {
    expect($this->getJson('/api/v1/products/with-video')->assertOk()->json('data.media'))->toBe([]);
});

it('never leaks the private social-media library into media[] or the page', function () {
    $v = ($this->addVideo)($this->product, 1);
    Storage::disk(config('media.private_disk'))->put('social-media/secret.mp4', $this->mp4Bytes);
    SocialMediaAsset::forceCreate(['product_id' => $this->product->id, 'kind' => 'video', 'title' => 'SECRET-TITLE', 'disk' => config('media.private_disk'), 'path' => 'social-media/secret.mp4', 'thumb_path' => 'social-media/secret_thumb.jpg',
        'original_name' => 'secret.mp4', 'mime' => 'video/mp4', 'size_bytes' => 10, 'version' => 1, 'status' => 'approved', 'source' => 'manual']);

    $api = $this->getJson('/api/v1/products/with-video')->assertOk();
    expect($api->json('data.media'))->toHaveCount(1)->and($api->json('data.media.0.id'))->toBe($v->id)->and($api->getContent())->not->toContain('social-media')->not->toContain('SECRET-TITLE');
    expect($this->get('/en/product/with-video')->assertOk()->getContent())->not->toContain('social-media/')->not->toContain('SECRET-TITLE');
});

it('keeps video files in the orphan sweep and removes them when the product is purged', function () {
    $this->actingAs($this->owner, 'admin');
    ($this->post_)($this->product, ['video' => ($this->mp4)(), 'poster' => UploadedFile::fake()->image('p.jpg', 600, 600)])->assertOk();
    $v = ProductVideo::sole();
    $paths = [$v->path, $v->poster_path, $v->poster_thumb_path];

    $this->artisan('media:prune-orphans', ['--min-age' => -1])->assertSuccessful();
    ($this->disk)()->assertExists($paths);

    $this->product->delete();
    Product::withTrashed()->whereKey($this->product->id)->update(['deleted_at' => now()->subYears(3)]);
    expect(app(SoftDeletePurger::class)->run(30, false, ['product'])['product']['purged'])->toBe(1);
    expect(ProductVideo::count())->toBe(0);
    ($this->disk)()->assertMissing($paths);
});
