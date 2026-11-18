<?php

// Search by image (IMAGE_SEARCH_PLAN.md): index upkeep (fill-missing only adds missing / replaced, never recomputes), observers, job retries,
// search ranking / grouping / thresholds / filters / counts, service-down handling, permission + validation. The embedder is faked with Http::fake:
// the query photo maps to the unit vector e0; every stored image is registered with an exact cosine to e0 (s·e0 + √(1−s²)·e_k).

use App\Enums\ImageEmbeddingSource;
use App\Enums\ImageEmbeddingStatus;
use App\Jobs\EmbedImageJob;
use App\Models\Admin;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Bundle;
use App\Models\BundleItem;
use App\Models\Image;
use App\Models\ImageEmbedding;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\ImageSearch\ImageEmbeddingService;
use App\Services\ImageSearch\ImageSearchService;
use Database\Seeders\FoundationSeeder;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;

const IS_DIM = 768;

/** Unit vector with cosine $s to e0 (noise on axis $k), packed like the embedder (little-endian float32). */
function isVec(float $s, int $k): string
{
    $v = array_fill(0, IS_DIM, 0.0);
    $v[0] = $s;
    $v[$k] = sqrt(max(0.0, 1 - $s * $s));

    return pack('g*', ...$v);
}

function isPng(int $seed): string
{
    $im = imagecreatetruecolor(40, 40);
    imagefill($im, 0, 0, imagecolorallocate($im, $seed % 255, 80, 120));
    ob_start();
    imagepng($im);

    return (string) ob_get_clean();
}

beforeEach(function () {
    $this->seed(FoundationSeeder::class);
    Cache::flush();
    ImageSearchService::flushMemo();
    Storage::fake(config('media.disk'));
    config(['image_search.embedder.url' => 'http://embedder.test', 'image_search.model' => 'test-model@pad224', 'image_search.dim' => IS_DIM]);

    $this->vectors = [];          // sha256(bytes) => packed vector
    $this->embedderDown = false;
    $this->embedCalls = 0;
    $this->noise = 1;
    Http::fake(['http://embedder.test/*' => function (HttpRequest $r) {
        if ($this->embedderDown) {
            return Http::response('', 502);
        }
        if (str_ends_with($r->url(), '/health')) {
            return Http::response(['status' => 'ok', 'model' => 'test-model@pad224', 'dim' => IS_DIM]);
        }
        $this->embedCalls++;
        $part = collect($r->data())->firstWhere('name', 'image');
        $bytes = (string) ($part['contents'] ?? '');
        if (str_starts_with($bytes, 'GARBAGE')) {
            return Http::response(['error' => 'invalid_image', 'detail' => 'cannot decode image'], 422);
        }
        $vec = $this->vectors[hash('sha256', $bytes)] ?? isVec(0.0, 700);

        return Http::response(['model' => 'test-model@pad224', 'dim' => IS_DIM, 'vector' => base64_encode($vec), 'ms' => 1]);
    }]);

    // a stored file whose embedding has cosine $s with the query photo
    $this->file = function (string $path, float $s) {
        $bytes = isPng(crc32($path)).$path; // unique bytes per path
        Storage::disk(config('media.disk'))->put($path, $bytes);
        $this->vectors[hash('sha256', $bytes)] = isVec($s, ++$this->noise);

        return $path;
    };
    $this->product = fn (string $sku, ?string $type = 'simple') => Product::create(['type' => $type, 'name' => ['ar' => 'منتج '.$sku, 'en' => 'Item '.$sku],
        'slug' => strtolower($sku), 'sku' => $sku]);
    $this->image = fn ($owner, string $path, float $s) => $owner->images()->create(['path' => ($this->file)($path, $s), 'thumb_path' => str_replace('.avif', '_thumb.avif', $path)]);

    // the query photo itself = e0
    $this->queryBytes = isPng(7).'QUERY';
    $this->vectors[hash('sha256', $this->queryBytes)] = isVec(1.0, 1);

    $this->svc = app(ImageEmbeddingService::class);
    $this->owner = Admin::query()->where('email', 'owner@egy.test')->first();
    $this->staff = function (array $perms) {
        $a = Admin::create(['name' => 'S', 'email' => 's'.mt_rand(1000, 99999).'@egy.test', 'password' => 'password', 'is_active' => true]);
        $perms && $a->givePermissionTo($perms);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $a->fresh();
    };
    $this->upload = function (string $bytes, string $name = 'photo.png') {
        file_put_contents($tmp = tempnam(sys_get_temp_dir(), 'isq'), $bytes);

        return new UploadedFile($tmp, $name, null, null, true);
    };
    $this->search = fn (array $extra = [], ?Admin $as = null) => $this->actingAs($as ?? $this->owner, 'admin')
        ->post(route('admin.image-search'), ['image' => ($this->upload)($this->queryBytes)] + $extra, ['Accept' => 'application/json']);

    // catalog: product A (2 images), variable product V (variant "Black" image), product B, bundle K, trashed product T
    $this->a = ($this->product)('A-1');
    $this->a1 = ($this->image)($this->a, 'catalog/a1.avif', 0.65);
    $this->a2 = ($this->image)($this->a, 'catalog/a2.avif', 0.78);
    $this->v = ($this->product)('V-1', 'variable');
    $attr = Attribute::create(['name' => ['ar' => 'لون', 'en' => 'Colour'], 'code' => 'colour', 'type' => 'select']);
    $black = AttributeValue::create(['attribute_id' => $attr->id, 'value' => ['ar' => 'أسود', 'en' => 'Black']]);
    $this->variant = ProductVariant::create(['product_id' => $this->v->id, 'combination_hash' => ProductVariant::hashFor([$black->id]), 'sku' => 'V-1-BK', 'barcode' => '2000000000022', 'is_active' => true]);
    $this->variant->values()->attach($black->id);
    $this->vImg = ($this->image)($this->variant, 'catalog/v1.avif', 0.90);
    $this->b = ($this->product)('B-1');
    $this->bImg = ($this->image)($this->b, 'catalog/b1.avif', 0.55);
    $this->bundle = Bundle::create(['name' => ['ar' => 'باقة', 'en' => 'Pack'], 'slug' => 'pack', 'price' => 100, 'is_active' => true, 'image' => ($this->file)('catalog/k.avif', 0.62)]);
    BundleItem::create(['bundle_id' => $this->bundle->id, 'product_id' => $this->a->id, 'quantity' => 1]);
    BundleItem::create(['bundle_id' => $this->bundle->id, 'product_id' => $this->b->id, 'quantity' => 2]);
    $this->t = ($this->product)('T-1');
    ($this->image)($this->t, 'catalog/t1.avif', 0.97);
    $this->t->delete();
    $this->total = 5; // a1, a2, v1, b1, bundle (trashed product T excluded)
});

it('fill-missing queues only missing sources and a second run queues 0 while the jobs are still pending', function () {
    Queue::fake();
    $first = $this->svc->fillMissing();
    expect($first['queued'])->toBe($this->total)->and($first['status']['pending'])->toBe($this->total)->and($first['status']['indexed'])->toBe(0);
    Queue::assertPushedOn('embeddings', EmbedImageJob::class);
    Queue::assertPushed(EmbedImageJob::class, $this->total);

    $second = $this->svc->fillMissing();
    expect($second['queued'])->toBe(0);
    Queue::assertPushed(EmbedImageJob::class, $this->total);

    // a job lost by a dead worker is re-queued after the pending timeout
    ImageEmbedding::query()->where('source_type', 'image')->where('source_id', $this->a1->id)->update(['queued_at' => now()->subHours(7)]);
    expect($this->svc->fillMissing()['queued'])->toBe(1);
});

it('indexes everything, never recomputes up-to-date rows, and re-embeds replaced images', function () {
    $r = $this->svc->fillMissing(); // sync queue: jobs run now
    expect($r['queued'])->toBe($this->total)->and($this->embedCalls)->toBe($this->total);
    $s = $this->svc->status();
    expect($s)->toMatchArray(['total' => $this->total, 'indexed' => $this->total, 'pending' => 0, 'failed' => 0, 'missing' => 0, 'complete' => true]);
    expect(ImageEmbedding::query()->where('status', 'ready')->count())->toBe($this->total)
        ->and(strlen(ImageEmbedding::query()->first()->getRawOriginal('vector')))->toBe(IS_DIM * 4);

    // second run: nothing queued, the embedder is not called again
    expect($this->svc->fillMissing()['queued'])->toBe(0)->and($this->embedCalls)->toBe($this->total);
    // a duplicate job for an up-to-date image does not call the embedder either
    (new EmbedImageJob('image', $this->a1->id))->handle($this->svc);
    expect($this->embedCalls)->toBe($this->total);

    // replaced in place (same path, new bytes) → detected by the hash check
    Storage::disk(config('media.disk'))->put('catalog/a1.avif', 'new-bytes');
    // replaced through the app (new path) → detected by the path check
    $this->b->images()->first()->update(['path' => ($this->file)('catalog/b1-new.avif', 0.88)]);
    $r = $this->svc->fillMissing();
    expect($r['queued'])->toBe(2)->and($this->embedCalls)->toBe($this->total + 2)
        ->and(ImageEmbedding::query()->where('source_id', $this->bImg->id)->value('source_path'))->toBe('catalog/b1-new.avif');
    expect($this->svc->fillMissing()['queued'])->toBe(0);
});

it('auto-indexes new and replaced images through the queue and removes rows of deleted images and bundle images', function () {
    config(['image_search.auto_index' => true]);
    Queue::fake();
    $img = ($this->image)($this->a, 'catalog/a3.avif', 0.7);
    Queue::assertPushed(EmbedImageJob::class, fn ($j) => $j->sourceType === 'image' && $j->sourceId === $img->id && $j->queue === 'embeddings');
    expect(ImageEmbedding::query()->where('source_id', $img->id)->value('status'))->toBe(ImageEmbeddingStatus::PENDING);

    Queue::fake();
    $this->bundle->update(['image' => ($this->file)('catalog/k2.avif', 0.7)]);
    Queue::assertPushed(EmbedImageJob::class, fn ($j) => $j->sourceType === 'bundle' && $j->sourceId === $this->bundle->id);

    // deleted image → row removed; bundle image removed → row removed
    $img->delete();
    $this->bundle->update(['image' => null]);
    expect(ImageEmbedding::query()->where('source_type', 'image')->where('source_id', $img->id)->exists())->toBeFalse()
        ->and(ImageEmbedding::query()->where('source_type', 'bundle')->exists())->toBeFalse();

    // images deleted without model events (mass delete) are pruned by fill-missing
    $this->svc->fillMissing();
    Image::query()->whereKey($this->a2->id)->delete();
    $r = $this->svc->fillMissing();
    expect($r['pruned'])->toBe(1)->and(ImageEmbedding::query()->where('source_id', $this->a2->id)->where('source_type', 'image')->exists())->toBeFalse();
});

it('keeps jobs pending while the embedder is down and records the failure when retries run out', function () {
    $this->svc->fillMissing(); // index
    Storage::disk(config('media.disk'))->put('catalog/a1.avif', 'replaced-bytes');
    $this->embedderDown = true;
    Queue::fake();
    expect($this->svc->fillMissing()['queued'])->toBe(1);

    $job = new EmbedImageJob('image', $this->a1->id);
    $job->handle($this->svc); // EmbedderUnavailable → released, not failed
    $row = ImageEmbedding::query()->where('source_id', $this->a1->id)->where('source_type', 'image')->first();
    expect($row->status)->toBe(ImageEmbeddingStatus::PENDING)->and($row->error)->toStartWith('retrying:');

    $job->failed(new RuntimeException('retries exhausted'));
    $row->refresh();
    expect($row->status)->toBe(ImageEmbeddingStatus::FAILED)->and($row->error)->toBe('retries exhausted');
    expect($this->svc->status()['failed'])->toBe(1);
    // failed rows wait for the retry window, then are queued again
    expect($this->svc->fillMissing()['queued'])->toBe(0);
    $row->update(['failed_at' => now()->subHours(25)]);
    expect($this->svc->fillMissing()['queued'])->toBe(1);

    // an undecodable file fails at once, without retries
    Storage::disk(config('media.disk'))->put('catalog/b1.avif', 'GARBAGE');
    $this->embedderDown = false;
    expect($this->svc->embed(ImageEmbeddingSource::IMAGE, $this->bImg->id))->toBe('failed')
        ->and(ImageEmbedding::query()->where('source_id', $this->bImg->id)->where('source_type', 'image')->value('error'))->toContain('invalid_image');
});

it('ranks products, variants and bundles, groups per product, hides weak hits and never exposes the raw score', function () {
    $this->svc->fillMissing();
    $files = Storage::disk(config('media.disk'))->allFiles();
    $res = ($this->search)()->assertOk()->json('data');

    // variant 0.90 > product A (best image 0.78, not a1 0.65) > bundle 0.62; B (0.55) hidden; trashed T (0.97) excluded
    expect(array_column($res['results'], 'type'))->toBe(['variant', 'product', 'bundle'])
        ->and($res['counts'])->toBe(['all' => 3, 'products' => 2, 'bundles' => 1])
        ->and($res['low_confidence'])->toBeFalse();
    [$variant, $product, $bundle] = $res['results'];
    expect($variant)->toMatchArray(['id' => $this->v->id, 'variant_id' => $this->variant->id, 'sku' => 'V-1-BK', 'match' => 'strong', 'best' => true,
        'strength' => 86, 'url' => route('admin.products.edit', $this->v->id).'#variant-section'])
        ->and($variant['name'])->toContain('أسود');
    expect($product)->toMatchArray(['id' => $this->a->id, 'sku' => 'A-1', 'match' => 'medium', 'best' => false, 'strength' => 51,
        'url' => route('admin.products.edit', $this->a->id), 'thumb' => media_url('catalog/a2_thumb.avif')]);
    expect($bundle)->toMatchArray(['type' => 'bundle', 'id' => $this->bundle->id, 'sku' => null, 'items' => 2, 'match' => 'weak', 'strength' => 6,
        'url' => route('admin.bundles.edit', $this->bundle->id)]);
    foreach ($res['results'] as $r) {
        expect($r)->not->toHaveKey('score');
    }
    // the query photo was not stored anywhere on the media disk
    expect(Storage::disk(config('media.disk'))->allFiles())->toBe($files);

    // server-side type filter
    expect(array_column(($this->search)(['type' => 'bundles'])->json('data.results'), 'type'))->toBe(['bundle']);
    expect(array_column(($this->search)(['type' => 'products'])->json('data.results'), 'type'))->toBe(['variant', 'product']);
});

it('caps results per type, flags low confidence and answers no matches', function () {
    config(['image_search.results' => 1]);
    $this->svc->fillMissing();
    $res = ($this->search)()->json('data');
    expect(array_column($res['results'], 'type'))->toBe(['variant', 'bundle'])->and($res['counts'])->toBe(['all' => 1, 'products' => 1, 'bundles' => 1]);

    config(['image_search.results' => 24]);
    $this->variant->delete();               // soft delete: hidden from search at once (index version bumped)
    $this->a->delete();
    $res = ($this->search)()->json('data');
    expect(array_column($res['results'], 'type'))->toBe(['bundle'])->and($res['low_confidence'])->toBeTrue();

    $this->bundle->delete();
    $res = ($this->search)()->assertOk()->json('data');
    expect($res['results'])->toBe([])->and($res['counts'])->toBe(['all' => 0, 'products' => 0, 'bundles' => 0]);

    // restoring a product makes its existing embeddings searchable again (index version bumped)
    $this->a->restore();
    $this->svc->fillMissing();
    expect(array_column(($this->search)()->json('data.results'), 'id'))->toBe([$this->a->id]);
});

it('computes strength and labels from config thresholds', function () {
    expect(ImageSearchService::strength(0.60))->toBe(0)->and(ImageSearchService::strength(0.95))->toBe(100)->and(ImageSearchService::strength(0.99))->toBe(100)
        ->and(ImageSearchService::strength(0.50))->toBe(0)->and(ImageSearchService::strength(0.775))->toBe(50)
        ->and(ImageSearchService::label(0.85))->toBe('strong')->and(ImageSearchService::label(0.8499))->toBe('medium')
        ->and(ImageSearchService::label(0.75))->toBe('medium')->and(ImageSearchService::label(0.7))->toBe('weak');
});

it('answers 503 with a clear message when the embedder is down and 422 for an undecodable photo', function () {
    $this->svc->fillMissing();
    $this->embedderDown = true;
    ($this->search)()->assertStatus(503)->assertJson(['success' => false, 'error' => 'unavailable', 'message' => __('admin.image_search.down_title')]);

    $this->embedderDown = false;
    $png = isPng(3);
    $this->actingAs($this->owner, 'admin')->post(route('admin.image-search'), ['image' => ($this->upload)('GARBAGE'.$png, 'x.png')], ['Accept' => 'application/json'])
        ->assertStatus(422);

    // coverage strip data
    $this->actingAs($this->owner, 'admin')->getJson(route('admin.image-search.coverage'))->assertOk()
        ->assertJson(['data' => ['total' => $this->total, 'indexed' => $this->total, 'waiting' => 0, 'complete' => true, 'service_up' => true]]);
});

it('requires products.view, validates size and type, and is rate limited', function () {
    $this->post(route('admin.image-search'), [], ['Accept' => 'application/json'])->assertUnauthorized();
    ($this->search)([], ($this->staff)([]))->assertForbidden();
    $this->actingAs(($this->staff)([]), 'admin')->getJson(route('admin.image-search.coverage'))->assertForbidden();
    ($this->search)([], ($this->staff)(['products.view']))->assertOk();

    $big = UploadedFile::fake()->create('big.jpg', 11 * 1024, 'image/jpeg');
    $this->actingAs($this->owner, 'admin')->post(route('admin.image-search'), ['image' => $big], ['Accept' => 'application/json'])
        ->assertStatus(422)->assertJsonPath('errors.image.0', __('admin.image_search.too_large', ['x' => '11.0']));
    $gif = ($this->upload)("GIF89a\x01\x00\x01\x00\x80\x00\x00\x00\x00\x00\xff\xff\xff!\xf9\x04\x01\x00\x00\x00\x00,\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x02D\x01\x00;", 'a.gif');
    $this->actingAs($this->owner, 'admin')->post(route('admin.image-search'), ['image' => $gif], ['Accept' => 'application/json'])
        ->assertStatus(422)->assertJsonPath('errors.image.0', __('admin.image_search.wrong_type', ['ext' => 'GIF']));
    $this->actingAs($this->owner, 'admin')->post(route('admin.image-search'), ['image' => ($this->upload)($this->queryBytes), 'type' => 'everything'], ['Accept' => 'application/json'])
        ->assertStatus(422)->assertJsonValidationErrors('type');

    config(['image_search.throttle_per_minute' => 2]);
    $u = ($this->staff)(['products.view']);
    ($this->search)([], $u)->assertOk();
    ($this->search)([], $u)->assertOk();
    ($this->search)([], $u)->assertStatus(429);
});

it('renders the modal on the products list page', function () {
    $this->actingAs($this->owner, 'admin')->get(route('admin.products.index'))->assertOk()
        ->assertSee('data-image-search', false)->assertSee(str_replace('/', '\\/', route('admin.image-search')), false)->assertSee(__('admin.image_search.drop_title'));
});

it('accepts AVIF uploads (the catalog format)', function () {
    if (! function_exists('imageavif')) {
        $this->markTestSkipped('GD without AVIF');
    }
    $im = imagecreatetruecolor(32, 32);
    ob_start();
    imageavif($im);
    $bytes = (string) ob_get_clean();
    $this->actingAs($this->owner, 'admin')->post(route('admin.image-search'), ['image' => ($this->upload)($bytes, 'p.avif')], ['Accept' => 'application/json'])->assertOk();
});

it('keeps ONE cached index entry per model and refreshes it when the index changes (no orphan blob per version)', function () {
    $this->svc->fillMissing(); // sync queue: everything indexed
    $store = Cache::store(config('image_search.cache_store'));
    $key = 'image_search:index:'.md5((string) config('image_search.model'));
    $repo = app(\App\Repositories\Contracts\ImageEmbeddingRepositoryInterface::class);

    ($this->search)()->assertOk()->assertJsonPath('data.results.1.id', $this->a->id);
    $v1 = $repo->version();
    expect($store->get($key)['version'] ?? null)->toBe($v1)->and($store->has($key.':'.$v1))->toBeFalse();

    // the index changes (A's images deleted ⇒ rows removed ⇒ version bumped): same key, new version, A gone from the results
    $this->a1->delete();
    $this->a2->delete();
    ImageSearchService::flushMemo(); // new request
    $ids = collect(($this->search)()->assertOk()->json('data.results'))->pluck('id');
    expect($ids)->not->toContain($this->a->id)
        ->and($repo->version())->not->toBe($v1)
        ->and($store->get($key)['version'])->toBe($repo->version())
        ->and($store->has($key.':'.$repo->version()))->toBeFalse();
});
