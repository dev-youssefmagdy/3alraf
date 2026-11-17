<?php

// new_product suggestions (MCP create_new_product_suggestion): images by URL (SSRF-safe, staged by content hash), variants from existing
// attribute values, validation, approve → apply creates a DRAFT product through ProductService, rollback while still a draft, review page.

use App\DTOs\Marketing\MarketingTokenData;
use App\Models\Attribute;
use App\Models\Brand;
use App\Models\Category;
use App\Models\MarketingSuggestion;
use App\Models\Product;
use App\Models\Tag;
use App\Services\Marketing\MarketingTokenService;
use App\Services\Marketing\Suggestions\SuggestionEngine;
use App\Services\Marketing\Suggestions\SuggestionException;
use App\Services\Marketing\Suggestions\SuggestionImages;
use App\Support\Http\SafeImageFetcher;
use App\Support\Marketing\SuggestionScopes;
use Database\Seeders\FoundationSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

function pngBytes(int $w = 40, int $h = 30, array $rgb = [200, 30, 30]): string
{
    $im = imagecreatetruecolor($w, $h);
    imagefill($im, 0, 0, imagecolorallocate($im, ...$rgb));
    ob_start();
    imagepng($im);

    return (string) ob_get_clean();
}

beforeEach(function () {
    $this->seed(FoundationSeeder::class);
    Cache::flush();
    Storage::fake(config('media.private_disk'));
    config(['app.url' => 'http://3alraf.test', 'app.host' => '3alraf.test']);
    // DNS: shop.example → a public address; intranet.example → a private one (SSRF attempt)
    $this->app->instance(SafeImageFetcher::class, new SafeImageFetcher(fn (string $host) => match ($host) {
        'intranet.example' => ['10.0.0.5'], default => ['93.184.216.34'],
    }));
    Http::fake([
        'https://shop.example/front.png' => Http::response(pngBytes(), 200, ['Content-Type' => 'image/png']),
        'https://shop.example/back.png' => Http::response(pngBytes(50, 50, [20, 20, 200]), 200, ['Content-Type' => 'image/png']),
        'https://shop.example/page.html' => Http::response('<html>not an image</html>', 200, ['Content-Type' => 'text/html']),
        'https://shop.example/moved.png' => Http::response('', 302, ['Location' => 'http://127.0.0.1/admin.png']),
        '*' => Http::response('', 404),
    ]);
    $this->owner = marketerOwner();
    $this->engine = app(SuggestionEngine::class);
    $this->tok = app(MarketingTokenService::class)->create(new MarketingTokenData('mcp', ['marketing:read', SuggestionScopes::for('new_product')], null, null))[1];
    $this->call = fn (string $method, string $path, array $body = []) => $this->flushHeaders()
        ->withHeaders(['Accept' => 'application/json', 'Authorization' => 'Bearer '.$this->tok])->json($method, '/api/internal/marketing/'.$path, $body);

    $tr = fn (string $en, string $ar) => ['en' => $en, 'ar' => $ar];
    $this->brand = Brand::create(['name' => $tr('Casio', 'كاسيو'), 'slug' => 'casio', 'is_active' => true]);
    $this->cat = Category::create(['name' => $tr('Watches', 'ساعات'), 'slug' => 'watches', 'path' => '', 'depth' => 0, 'is_active' => true]);
    $this->tag = Tag::create(['name' => $tr('New', 'جديد'), 'slug' => 'new', 'is_active' => true]);
    $this->dial = Attribute::create(['name' => $tr('Dial', 'الميناء'), 'code' => 'dial', 'type' => 'select']);
    $this->black = $this->dial->values()->create(['value' => $tr('Black', 'أسود'), 'sort_order' => 0]);
    $this->blue = $this->dial->values()->create(['value' => $tr('Blue', 'أزرق'), 'sort_order' => 1]);
    $this->strap = Attribute::create(['name' => $tr('Strap', 'السوار'), 'code' => 'strap', 'type' => 'select']);
    $this->steel = $this->strap->values()->create(['value' => $tr('Steel', 'ستيل'), 'sort_order' => 0]);

    $this->payload = fn (array $over = []) => array_replace([
        'name' => $tr('Casio Edifice EFR-100', 'كاسيو إديفيس EFR-100'),
        'description' => ['en' => '<p>Chronograph <b>steel</b> watch</p><script>alert(1)</script>'],
        'meta_title' => ['en' => 'Casio Edifice EFR-100'],
        'sku' => 'CAS-EFR100', 'brand_id' => $this->brand->id, 'category_ids' => [$this->cat->id], 'tag_ids' => [$this->tag->id],
        'price' => 2500, 'sale_price' => 2300, 'cost' => 1600, 'weight' => 140,
        'gift' => ['suitable' => true, 'recipients' => ['partner'], 'occasions' => ['birthday']],
        'images' => [['url' => 'https://shop.example/front.png'], ['url' => 'https://shop.example/back.png']],
        'variants' => [
            ['values' => [$this->black->id, $this->steel->id], 'sku' => 'CAS-EFR100-BK', 'image' => 0],
            ['values' => [$this->blue->id, $this->steel->id], 'sku' => 'CAS-EFR100-BL', 'price' => 2600, 'image' => 1],
        ],
    ], $over);
    $this->create = fn (array $payload) => ($this->call)('POST', 'suggestions/new_product', ['title' => 'Add Casio EFR-100', 'reasoning' => 'Supplier sheet', 'payload' => $payload]);
});

it('creates the suggestion: images downloaded once and pinned by hash, variants normalised, review warnings', function () {
    ($this->create)(($this->payload)())->assertCreated()->assertJsonPath('data.type', 'new_product')->assertJsonPath('data.status', 'pending');

    $s = MarketingSuggestion::query()->firstOrFail();
    $p = $s->payload;
    expect($p['type'])->toBe('variable')
        ->and($p['description']['en'])->toBe('<p>Chronograph <b>steel</b> watch</p>')                       // sanitised
        ->and($p['images'])->toHaveCount(2)
        ->and($p['images'][0])->toMatchArray(['url' => 'https://shop.example/front.png', 'mime' => 'image/png', 'width' => 40, 'height' => 30])
        ->and(strlen($p['images'][0]['sha256']))->toBe(64)
        ->and(app(SuggestionImages::class)->exists($p['images'][1]))->toBeTrue()
        ->and($p['variants'][0]['values'])->toBe([$this->black->id, $this->steel->id]);

    // editing re-normalises the payload but never downloads the same URL again
    Http::assertSentCount(2);
    app(SuggestionImages::class)->stage('https://shop.example/front.png');
    Http::assertSentCount(2);
});

it('refuses internal addresses, redirects to them, and non-images', function (string $url, string $reason) {
    ($this->create)(($this->payload)(['images' => [['url' => $url]], 'variants' => []]))
        ->assertStatus(422)->assertJsonPath('error', 'validation_failed')
        ->assertJsonValidationErrors(['payload.images.0.url']);

    expect(MarketingSuggestion::count())->toBe(0)
        ->and(app(SuggestionImages::class)->stage($url)['error'])->toBe($reason);
})->with([
    'private DNS answer' => ['https://intranet.example/x.png', 'blocked_address'],
    'loopback literal' => ['http://127.0.0.1/x.png', 'blocked_address'],
    'cloud metadata' => ['http://169.254.169.254/latest/meta-data', 'blocked_address'],
    'redirect to loopback' => ['https://shop.example/moved.png', 'blocked_address'],
    'non-default port' => ['https://shop.example:8443/x.png', 'bad_port'],
    'html page' => ['https://shop.example/page.html', 'not_image'],
    'not found' => ['https://shop.example/missing.png', 'http_error'],
]);

it('validates identifiers, prices, references and variants', function () {
    Product::create(['type' => 'simple', 'name' => ['en' => 'Old'], 'slug' => 'old', 'sku' => 'TAKEN']);
    $bad = ($this->payload)([
        'sku' => 'TAKEN', 'sale_price' => 2600, 'brand_id' => 999999,
        'variants' => [
            ['values' => [$this->black->id, $this->blue->id]],          // two values of the same attribute
            ['values' => [$this->black->id], 'image' => 7],             // other attributes than the first variant + bad image index
        ],
    ]);

    $res = ($this->create)($bad)->assertStatus(422);
    expect(array_keys($res->json('errors')))->toContain('payload.sku', 'payload.sale_price', 'payload.brand_id', 'payload.variants.0.values', 'payload.variants.1.values', 'payload.variants.1.image');

    // selling below cost is refused
    ($this->create)(($this->payload)(['price' => 1000, 'sale_price' => null, 'variants' => []]))->assertStatus(422)
        ->assertJsonValidationErrors(['payload.price']);
});

it('applies as a DRAFT product with variants, images and every field, then rolls back while still a draft', function () {
    ($this->create)(($this->payload)())->assertCreated();
    $s = MarketingSuggestion::query()->firstOrFail();

    $this->engine->approve($s->fresh(), $this->owner);
    $exec = $this->engine->apply($s->fresh(), $this->owner);
    expect($exec->status->value)->toBe('succeeded');

    $product = Product::query()->with(['variants.values', 'variants.images', 'images', 'categories', 'tags'])->findOrFail($exec->after['product_id']);
    expect($product->is_draft)->toBeTrue()
        ->and($product->is_active)->toBeFalse()
        ->and($product->type->value)->toBe('variable')
        ->and($product->sku)->toBe('CAS-EFR100')
        ->and($product->getTranslations('name'))->toMatchArray(['en' => 'Casio Edifice EFR-100', 'ar' => 'كاسيو إديفيس EFR-100'])
        ->and($product->brand_id)->toBe($this->brand->id)
        ->and($product->categories->pluck('id')->all())->toBe([$this->cat->id])
        ->and($product->tags->pluck('id')->all())->toBe([$this->tag->id])
        ->and([$product->price, $product->sale_price, $product->cost])->toBe(['2500.00', '2300.00', '1600.00'])
        ->and($product->weight)->toBe(140)
        ->and($product->gift_suitable)->toBeTrue()
        ->and($product->images)->toHaveCount(2)
        ->and($product->variants)->toHaveCount(2)
        ->and($product->variants->firstWhere('sku', 'CAS-EFR100-BL')->price)->toBe('2600.00')
        ->and($product->variants->every(fn ($v) => $v->images->count() === 1))->toBeTrue()
        ->and($s->fresh()->result_entity_type)->toBe('product');

    $this->engine->rollback($s->fresh(), $this->owner);
    expect(Product::find($product->id))->toBeNull()->and(Product::withTrashed()->find($product->id))->not->toBeNull();
});

it('refuses to roll back once the product was published', function () {
    ($this->create)(($this->payload)(['variants' => []]))->assertCreated();
    $s = MarketingSuggestion::query()->firstOrFail();
    $this->engine->approve($s->fresh(), $this->owner);
    $product = Product::findOrFail($this->engine->apply($s->fresh(), $this->owner)->after['product_id']);

    $product->update(['is_draft' => false, 'is_active' => true]);   // the owner clicked "Save & publish"

    expect(fn () => $this->engine->rollback($s->fresh(), $this->owner))->toThrow(SuggestionException::class);
    expect(Product::find($product->id))->not->toBeNull();
});

it('shows the staged images to reviewers only', function () {
    ($this->create)(($this->payload)())->assertCreated();
    $s = MarketingSuggestion::query()->firstOrFail();
    $sha = $s->payload['images'][0]['sha256'];

    $this->flushHeaders()->get("/marketer/suggestions/image/$sha")->assertRedirect();   // guests: login
    $this->actingAs($this->owner, 'marketer');
    $this->get("/marketer/suggestions/{$s->id}")->assertOk()->assertSee('data-images', false)->assertSee("/marketer/suggestions/image/$sha", false);
    $this->get("/marketer/suggestions/image/$sha")->assertOk()->assertHeader('Content-Type', 'image/png');
});

it('lists ALL attributes with all their values in one call (no paging), without draft attributes, and tags', function () {
    Attribute::create(['name' => ['en' => 'Hidden'], 'code' => 'hidden', 'type' => 'select', 'is_draft' => true]);
    foreach (range(1, 40) as $i) {   // more than any page size
        Attribute::create(['name' => ['en' => "Extra $i"], 'code' => "extra_$i", 'type' => 'select', 'sort_order' => 10])->values()->create(['value' => ['en' => "V$i"], 'sort_order' => 0]);
    }

    $res = ($this->call)('GET', 'attributes', ['per_page' => 5])->assertOk();
    $attrs = $res->json('data');
    expect($attrs)->toHaveCount(42)->and($res->json('meta.total'))->toBe(42)->and($res->json('meta.values_total'))->toBe(43);
    $attrs = array_slice($attrs, 0, 2);
    expect(collect($attrs)->pluck('code')->all())->toBe(['dial', 'strap'])
        ->and($attrs[0]['values'][0])->toMatchArray(['id' => $this->black->id, 'value' => ['ar' => 'أسود', 'en' => 'Black']]);
    expect(($this->call)('GET', 'tags')->assertOk()->json('data.0.id'))->toBe($this->tag->id);
});

it('prunes staged images no open suggestion needs', function () {
    ($this->create)(($this->payload)())->assertCreated();
    $s = MarketingSuggestion::query()->firstOrFail();
    $images = app(SuggestionImages::class);

    $this->travel(10)->days();
    expect($images->prune(7))->toBe(0);                                      // still pending: kept

    $s->update(['status' => 'rejected']);
    expect($images->prune(7))->toBe(2)->and($images->exists($s->payload['images'][0]))->toBeFalse();
});

it('creates proposed attributes and values together with the product when it is applied', function () {
    $payload = ($this->payload)([
        'new_attributes' => [['ref' => 'case_size', 'name' => ['en' => 'Case size', 'ar' => 'قطر الهيكل'], 'values' => [
            ['ref' => 's40', 'value' => ['en' => '40 mm', 'ar' => '40 مم']], ['ref' => 's44', 'value' => ['en' => '44 mm', 'ar' => '44 مم']],
        ]]],
        'new_values' => [['attribute_id' => $this->dial->id, 'ref' => 'Teal', 'value' => ['en' => 'Teal', 'ar' => 'تيل']]],
        'variants' => [
            ['values' => [$this->black->id, 's40'], 'sku' => 'CAS-BK-40', 'image' => 0],
            ['values' => ['teal', 's44'], 'sku' => 'CAS-TL-44', 'image' => 1],
        ],
    ]);
    ($this->create)($payload)->assertCreated();
    $s = MarketingSuggestion::query()->firstOrFail();
    expect($s->payload['new_attributes'][0])->toMatchArray(['ref' => 'case_size', 'code' => 'case_size', 'type' => 'select'])
        ->and($s->payload['new_values'][0]['ref'])->toBe('teal')                         // refs are lowercased
        ->and($s->payload['variants'][1]['values'])->toBe(['s44', 'teal'])
        ->and(Attribute::query()->where('code', 'case_size')->exists())->toBeFalse();   // nothing is created before approval

    $this->engine->approve($s->fresh(), $this->owner);
    $exec = $this->engine->apply($s->fresh(), $this->owner);
    expect($exec->status->value)->toBe('succeeded');

    $size = Attribute::query()->where('code', 'case_size')->with('values')->firstOrFail();
    $teal = $this->dial->values()->get()->first(fn ($v) => $v->getTranslation('value', 'en') === 'Teal');
    expect($size->is_draft)->toBeFalse()->and($size->values->map(fn ($v) => $v->getTranslation('value', 'en'))->all())->toBe(['40 mm', '44 mm'])->and($teal)->not->toBeNull()
        ->and($exec->after['created_attribute_ids'])->toBe([$size->id])->and($exec->after['created_value_ids'])->toBe([$teal->id]);

    $product = Product::with('variants.values')->findOrFail($exec->after['product_id']);
    expect($product->variants->firstWhere('sku', 'CAS-BK-40')->values->pluck('id')->sort()->values()->all())->toBe(collect([$this->black->id, $size->values[0]->id])->sort()->values()->all())
        ->and($product->variants->firstWhere('sku', 'CAS-TL-44')->values->map(fn ($v) => $v->getTranslation('value', 'en'))->sort()->values()->all())->toBe(['44 mm', 'Teal']);

    // rollback removes the product and what this suggestion created (nothing else uses them)
    $this->engine->rollback($s->fresh(), $this->owner);
    expect(Attribute::query()->find($size->id))->toBeNull()->and(\App\Models\AttributeValue::query()->find($teal->id))->toBeNull()
        ->and(\App\Models\AttributeValue::query()->find($this->black->id))->not->toBeNull();
});

it('refuses duplicates of what the store already has and unknown refs', function () {
    $res = ($this->create)(($this->payload)([
        'new_attributes' => [
            ['ref' => 'dial2', 'name' => ['en' => 'Dial'], 'code' => 'dial', 'values' => [['ref' => 'x1', 'value' => ['en' => 'X']]]],                  // code taken
            ['ref' => 'paint', 'name' => ['en' => 'Paint'], 'type' => 'color', 'values' => [['ref' => 'red', 'value' => ['en' => 'Red']], ['ref' => 'red2', 'value' => ['en' => 'red']]]],
        ],
        'new_values' => [
            ['attribute_id' => $this->dial->id, 'ref' => 'black2', 'value' => ['en' => 'BLACK']],     // already exists as Black
            ['attribute_id' => 999999, 'ref' => 'ghost', 'value' => ['en' => 'Ghost']],
            ['attribute_id' => $this->strap->id, 'ref' => 'x1', 'value' => ['en' => 'Rubber']],        // ref reused
        ],
        'variants' => [['values' => [$this->black->id, 'nope']]],
    ]))->assertStatus(422);

    expect(array_keys($res->json('errors')))->toContain(
        'payload.new_attributes.0.code', 'payload.new_attributes.1.values.0.color_hex', 'payload.new_attributes.1.values.1.value',
        'payload.new_values.0.value', 'payload.new_values.1.attribute_id', 'payload.new_values.2.ref', 'payload.variants.0.values',
    )->and($res->json('errors')['payload.new_values.0.value'][0])->toContain('#'.$this->black->id);
});

it('keeps an attribute it created on rollback when another product already uses it', function () {
    ($this->create)(($this->payload)([
        'new_attributes' => [['ref' => 'size', 'name' => ['en' => 'Size'], 'values' => [['ref' => 's1', 'value' => ['en' => 'S1']]]]],
        'variants' => [['values' => ['s1'], 'sku' => 'A-1']],
    ]))->assertCreated();
    $s = MarketingSuggestion::query()->firstOrFail();
    $this->engine->approve($s->fresh(), $this->owner);
    $this->engine->apply($s->fresh(), $this->owner);
    $size = Attribute::query()->where('code', 'size')->with('values')->firstOrFail();

    $other = Product::create(['type' => 'variable', 'name' => ['en' => 'Other'], 'slug' => 'other', 'sku' => 'OTHER']);
    $v = $other->variants()->create(['combination_hash' => (string) $size->values[0]->id, 'sku' => 'OTHER-1', 'barcode' => app(\App\Services\BarcodeService::class)->generateUnique()]);
    $v->values()->sync([$size->values[0]->id]);

    $exec = $this->engine->rollback($s->fresh(), $this->owner);
    expect(Attribute::query()->find($size->id))->not->toBeNull()->and($exec->after['kept_in_use'])->toBe(['attribute#'.$size->id]);
});

it('needs ai_products.approve to approve or apply a new product', function () {
    ($this->create)(($this->payload)(['variants' => []]))->assertCreated();
    $s = MarketingSuggestion::query()->firstOrFail();
    $staff = makeMarketer(['ai_suggestions.view', 'ai_suggestions.approve', 'ai_suggestions.apply']);

    expect(fn () => $this->engine->approve($s->fresh(), $staff))->toThrow(SuggestionException::class);

    $staff->givePermissionTo('ai_products.approve');
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    $this->engine->approve($s->fresh(), $staff->fresh());
    expect($this->engine->apply($s->fresh(), $staff->fresh())->status->value)->toBe('succeeded');
});
