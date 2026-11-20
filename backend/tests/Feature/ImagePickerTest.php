<?php

// Product edit → "Find images" (tools/image-picker): free web search through the picker script, caching, and import of a chosen picture
// into the product gallery / main photo / variant photo. The script and every download are faked.

use App\Models\Admin;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\BarcodeService;
use App\Services\ImagePicker\ImagePickerService;
use App\Support\Http\SafeImageFetcher;
use Database\Seeders\FoundationSeeder;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\PermissionRegistrar;

function ipPng(int $w, int $h): string
{
    $im = imagecreatetruecolor($w, $h);
    imagefill($im, 0, 0, imagecolorallocate($im, 200, 30, 90));
    ob_start();
    imagepng($im);

    return (string) ob_get_clean();
}

beforeEach(function () {
    $this->seed(FoundationSeeder::class);
    Cache::flush();
    Storage::fake(config('media.disk'));
    $this->withoutVite();
    $this->owner = Admin::query()->where('email', 'owner@egy.test')->first();
    $this->actingAs($this->owner, 'admin');
    app()->instance(SafeImageFetcher::class, new SafeImageFetcher(fn (string $h) => $h === 'internal.test' ? ['10.0.0.8'] : ['93.184.216.34']));

    $brand = Brand::create(['name' => ['en' => 'Glam', 'ar' => 'جلام'], 'slug' => 'glam', 'is_active' => true]);
    $this->product = listProduct(Product::create(['type' => 'simple', 'brand_id' => $brand->id, 'name' => ['en' => 'Silk Scrunchies', 'ar' => 'توك شعر حرير'],
        'description' => ['en' => 'x'], 'slug' => 'silk-scrunchies', 'sku' => 'SCR', 'barcode' => app(BarcodeService::class)->generateUnique()]), ['price' => '50']);
    $attr = Attribute::create(['name' => ['en' => 'Color', 'ar' => 'اللون'], 'code' => 'color', 'type' => 'color']);
    $red = AttributeValue::create(['attribute_id' => $attr->id, 'value' => ['en' => 'Red', 'ar' => 'أحمر']]);
    $this->variant = ProductVariant::create(['product_id' => $this->product->id, 'combination_hash' => (string) $red->id, 'sku' => 'SCR-RED',
        'is_active' => true, 'barcode' => app(BarcodeService::class)->generateUnique()]);
    $this->variant->values()->attach($red->id);

    $this->script = json_encode(['query' => 'q', 'page' => 1, 'has_more' => true, 'errors' => ['duckduckgo' => 'RequestError'], 'results' => [
        ['image' => 'https://img.test/red.png', 'thumbnail' => 'https://tse.mm.bing.net/th?id=1', 'title' => '<b>Glam</b> silk scrunchie red', 'page' => 'https://shop.test/red',
            'source' => 'shop.test', 'width' => 1000, 'height' => 1000, 'engine' => 'bing'],
        ['image' => 'javascript:alert(1)', 'title' => 'xss'],
        ['image' => 'https://img.test/small.png', 'thumbnail' => 'data:image/png;base64,AA', 'page' => 'javascript:x', 'title' => 'small', 'width' => 200, 'height' => 200, 'engine' => 'bing'],
    ]]);
    Process::fake(['*' => Process::result($this->script)]);
    $this->png = ipPng(900, 900);
    Http::fake([
        'https://img.test/red.png' => Http::response($this->png, 200, ['Content-Type' => 'image/png']),
        'https://img.test/not-image.png' => Http::response('<?php echo 1;', 200, ['Content-Type' => 'image/png']),
        '*' => Http::response('', 404),
    ]);
    $this->base = '/admin/products/'.$this->product->id.'/image-picker';
});

it('shows Find images on the edit page for the product and each saved variant, with the search text prefilled', function () {
    $html = $this->get("/admin/products/{$this->product->id}/edit")->assertOk()->getContent();
    expect($html)->toContain('data-image-picker')->toContain('data-image-picker-open data-target-type="product"')
        ->toContain('data-target-type="variant" data-target-id="'.$this->variant->id.'"')
        ->toContain(__('admin.image_picker.find_images'));

    $targets = app(ImagePickerService::class)->targets($this->product->fresh());
    expect(collect($targets)->pluck('query')->all())->toBe(['Glam Silk Scrunchies', 'Glam Silk Scrunchies Red']);
    $this->get('/admin/products/create')->assertOk()->assertDontSee('data-image-picker', false);
});

it('searches through the picker script, keeps only http(s) links, strips HTML from titles and caches the answer', function () {
    $r = $this->postJson("{$this->base}/search", ['query' => 'Glam Silk Scrunchies Red'])->assertOk();

    expect($r->json('data.results'))->toHaveCount(2)
        ->and($r->json('data.results.0'))->toMatchArray(['image' => 'https://img.test/red.png', 'title' => 'Glam silk scrunchie red', 'width' => 1000, 'page' => 'https://shop.test/red'])
        ->and($r->json('data.results.1.thumbnail'))->toBe('https://img.test/small.png')   // data: thumbnail replaced by the image itself
        ->and($r->json('data.results.1.page'))->toBeNull()
        ->and($r->json('data.has_more'))->toBeTrue()
        ->and($r->json('data.errors'))->toHaveKey('duckduckgo');
    Process::assertRan(fn (PendingProcess $p) => is_array($p->command) && $p->command[2] === 'search' && $p->command[3] === 'Glam Silk Scrunchies Red'
        && in_array('--no-cache', $p->command, true));

    $this->postJson("{$this->base}/search", ['query' => 'Glam Silk Scrunchies Red'])->assertOk();
    Process::assertRanTimes(fn () => true, 1); // second time from the cache
});

it('passes the search text as one argument, never through a shell', function () {
    $this->postJson("{$this->base}/search", ['query' => 'x"; rm -rf / #'])->assertOk();
    Process::assertRan(fn (PendingProcess $p) => is_array($p->command) && $p->command[3] === 'x"; rm -rf / #');
});

it('answers 503 and caches nothing when the script fails', function () {
    Process::fake(['*' => Process::result('', 'ModuleNotFoundError: ddgs', 1)]);
    $this->postJson("{$this->base}/search", ['query' => 'abc'])->assertStatus(503)->assertJsonPath('message', __('admin.image_picker.unavailable'));
    Process::fake(['*' => Process::result($this->script)]);
    $this->postJson("{$this->base}/search", ['query' => 'abc'])->assertOk();
});

it('adds a chosen picture to the gallery or as the main photo, through the normal upload pipeline, and logs its source', function () {
    $old = $this->product->images()->create(['path' => 'catalog/products/old.avif', 'thumb_path' => 'catalog/products/old_thumb.avif', 'sort_order' => 1]);

    $this->postJson("{$this->base}/import", ['image_url' => 'https://img.test/red.png', 'page_url' => 'https://shop.test/red', 'title' => 'Red', 'as' => 'gallery'])
        ->assertOk()->assertJsonPath('message', __('admin.image_picker.imported_gallery'));
    $added = $this->product->images()->reorder()->latest('id')->first();
    expect($this->product->images()->count())->toBe(2)->and($this->product->images()->first()->id)->toBe($old->id)
        ->and($added->path)->toStartWith('catalog/products/')->toEndWith('.avif');
    Storage::disk(config('media.disk'))->assertExists($added->path);

    $this->postJson("{$this->base}/import", ['image_url' => 'https://img.test/red.png', 'as' => 'main'])->assertOk();
    $main = $this->product->images()->first();
    expect($main->id)->not->toBe($old->id)->and($this->product->images()->count())->toBe(3);

    $log = Activity::query()->where('log_name', 'image_picker')->latest('id')->first();
    expect($log->description)->toBe('image_picker.imported')->and($log->properties['source_image'])->toBe('https://img.test/red.png')
        ->and(Activity::query()->where('log_name', 'image_picker')->first()->properties['source_page'])->toBe('https://shop.test/red');
});

it('replaces a variant photo, and refuses a variant of another product', function () {
    $old = $this->variant->images()->create(['path' => 'catalog/variants/old.avif', 'thumb_path' => 'catalog/variants/old_thumb.avif', 'sort_order' => 1]);
    $this->postJson("{$this->base}/import", ['image_url' => 'https://img.test/red.png', 'as' => 'main', 'variant_id' => $this->variant->id])
        ->assertOk()->assertJsonPath('message', __('admin.image_picker.imported_variant'));
    expect($this->variant->images()->count())->toBe(1)->and($old->fresh())->toBeNull()
        ->and($this->product->images()->count())->toBe(0);

    $other = Product::create(['type' => 'simple', 'name' => ['en' => 'Other'], 'slug' => 'other', 'sku' => 'O']);
    $ov = ProductVariant::create(['product_id' => $other->id, 'combination_hash' => 'x', 'sku' => 'O-1', 'is_active' => true, 'barcode' => app(BarcodeService::class)->generateUnique()]);
    $this->postJson("{$this->base}/import", ['image_url' => 'https://img.test/red.png', 'as' => 'main', 'variant_id' => $ov->id])
        ->assertStatus(422)->assertJsonValidationErrors('variant_id');
});

it('never downloads from internal addresses and never stores something that is not an image', function () {
    foreach (['https://internal.test/a.png', 'http://127.0.0.1/admin', 'http://169.254.169.254/latest/meta-data'] as $url) {
        $this->postJson("{$this->base}/import", ['image_url' => $url, 'as' => 'gallery'])->assertStatus(422)->assertJsonValidationErrors('image_url');
    }
    $this->postJson("{$this->base}/import", ['image_url' => 'file:///etc/passwd', 'as' => 'gallery'])->assertStatus(422);
    $this->postJson("{$this->base}/import", ['image_url' => 'https://img.test/not-image.png', 'as' => 'gallery'])->assertStatus(422);
    Http::assertNotSent(fn (HttpRequest $r) => str_contains($r->url(), 'internal.test') || str_contains($r->url(), '127.0.0.1') || str_contains($r->url(), '169.254.'));
    expect($this->product->images()->count())->toBe(0)->and(Storage::disk(config('media.disk'))->allFiles())->toBe([]);
});

it('uses the search engine copy when the site answers with a web page instead of the picture, and says so when both fail', function () {
    Http::swap(new HttpFactory); // drop the catch-all 404 of beforeEach
    Http::fake([
        'https://shop.test/blocked.jpg' => Http::response('<!doctype html><title>Just a moment...</title>', 200, ['Content-Type' => 'text/html']),
        'https://tse.mm.bing.net/th?id=ok' => Http::response($this->png, 200, ['Content-Type' => 'image/jpeg']),
        '*' => Http::response('<html></html>', 200),
    ]);
    $this->postJson("{$this->base}/import", ['image_url' => 'https://shop.test/blocked.jpg', 'thumbnail_url' => 'https://tse.mm.bing.net/th?id=ok',
        'page_url' => 'https://shop.test/p', 'as' => 'gallery'])->assertOk();
    expect($this->product->images()->count())->toBe(1);
    Http::assertSent(fn (HttpRequest $r) => $r->url() === 'https://shop.test/blocked.jpg' && $r->header('Referer') === ['https://shop.test/p']
        && str_starts_with($r->header('User-Agent')[0], 'Mozilla/'));

    $this->postJson("{$this->base}/import", ['image_url' => 'https://shop.test/blocked.jpg', 'thumbnail_url' => 'https://tse.mm.bing.net/th?id=bad', 'as' => 'gallery'])
        ->assertStatus(422)->assertJsonPath('errors.image_url.0', __('admin.image_picker.not_image'));
    expect($this->product->images()->count())->toBe(1);
});

it('needs products.edit', function () {
    $viewer = Admin::create(['name' => 'V', 'email' => 'v@egy.test', 'password' => 'password', 'is_active' => true]);
    $viewer->givePermissionTo('products.view');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->actingAs($viewer->fresh(), 'admin');
    $this->postJson("{$this->base}/search", ['query' => 'x'])->assertForbidden();
    $this->postJson("{$this->base}/import", ['image_url' => 'https://img.test/red.png', 'as' => 'gallery'])->assertForbidden();
    Process::assertNothingRan();
});
