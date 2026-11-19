<?php

// product_update suggestions (MCP suggest_product_update): change an EXISTING product's catalog record — wording, brand, categories, status,
// prices (product + existing variants), images (add by URL, remove, main image) — after approval, through ProductService; rollback restores it.

use App\DTOs\Marketing\MarketingTokenData;
use App\Models\Brand;
use App\Models\Category;
use App\Models\MarketingSuggestion;
use App\Models\Product;
use App\Services\Marketing\MarketingTokenService;
use App\Services\Marketing\Suggestions\SuggestionEngine;
use App\Services\Marketing\Suggestions\SuggestionException;
use App\Support\Http\SafeImageFetcher;
use App\Support\Marketing\SuggestionScopes;
use Database\Seeders\FoundationSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

function productUpdatePng(int $w = 80, int $h = 80): string
{
    $im = imagecreatetruecolor($w, $h);
    imagefill($im, 0, 0, imagecolorallocate($im, 200, 40, 90));
    ob_start();
    imagepng($im);

    return (string) ob_get_clean();
}

beforeEach(function () {
    $this->seed(FoundationSeeder::class);
    Cache::flush();
    Storage::fake(config('media.private_disk'));
    Storage::fake(config('media.disk'));
    config(['app.url' => 'http://3alraf.test', 'app.host' => '3alraf.test']);
    $this->app->instance(SafeImageFetcher::class, new SafeImageFetcher(fn (string $host) => $host === 'intranet.example' ? ['10.0.0.5'] : ['93.184.216.34']));
    Http::fake([
        'https://shop.example/new.png' => Http::response(productUpdatePng(), 200, ['Content-Type' => 'image/png']),
        '*' => Http::response('', 404),
    ]);
    $this->owner = marketerOwner();
    $this->engine = app(SuggestionEngine::class);
    $this->tok = app(MarketingTokenService::class)->create(new MarketingTokenData('mcp', ['marketing:read', SuggestionScopes::for('product_update')], null, null))[1];
    $this->call = fn (string $method, string $path, array $body = []) => $this->flushHeaders()
        ->withHeaders(['Accept' => 'application/json', 'Authorization' => 'Bearer '.$this->tok])->json($method, '/api/internal/marketing/'.$path, $body);

    $this->brand = Brand::create(['name' => ['en' => 'Casio'], 'slug' => 'casio', 'is_active' => true]);
    $this->watches = Category::create(['name' => ['en' => 'Watches'], 'slug' => 'watches', 'path' => '', 'depth' => 0, 'is_active' => true]);
    $this->gifts = Category::create(['name' => ['en' => 'Gifts'], 'slug' => 'gifts', 'path' => '', 'depth' => 0, 'is_active' => true]);
    $this->product = Product::create(['type' => 'simple', 'name' => ['en' => 'Watch', 'ar' => 'ساعة'], 'description' => ['en' => 'Old text'], 'slug' => 'watch', 'sku' => 'W-1',
        'is_active' => true, 'price' => '1000.00', 'cost' => '600.00']);
    $this->product->categories()->sync([$this->watches->id]);
    Storage::disk(config('media.disk'))->put('products/a.avif', 'a');
    Storage::disk(config('media.disk'))->put('products/b.avif', 'b');
    $this->imgA = $this->product->images()->create(['path' => 'products/a.avif', 'sort_order' => 1]);
    $this->imgB = $this->product->images()->create(['path' => 'products/b.avif', 'sort_order' => 2]);

    $this->create = fn (array $payload, array $extra = []) => ($this->call)('POST', 'suggestions/product_update', ['title' => 'Refresh the watch', 'target_entity_type' => 'product',
        'target_entity_id' => $this->product->id, 'payload' => $payload] + $extra);
    $this->applyIt = function () {
        $s = MarketingSuggestion::query()->latest('id')->firstOrFail();
        $this->engine->approve($s->fresh(), $this->owner);

        return [$s, $this->engine->apply($s->fresh(), $this->owner)];
    };
});

it('updates core fields and pricing after approval, keeps the rest, and rolls back', function () {
    ($this->create)(['name' => ['ar' => 'ساعة كاسيو'], 'brand_id' => $this->brand->id, 'category_ids' => [$this->watches->id, $this->gifts->id],
        'price' => 1200, 'sale_price' => 990])->assertCreated()->assertJsonPath('data.type', 'product_update');

    [$s, $exec] = ($this->applyIt)();
    $p = $this->product->fresh();
    expect($exec->status->value)->toBe('succeeded')
        ->and($p->getTranslation('name', 'ar'))->toBe('ساعة كاسيو')
        ->and($p->getTranslation('name', 'en'))->toBe('Watch')            // untouched locale
        ->and($p->getTranslation('description', 'en'))->toBe('Old text')  // untouched field
        ->and($p->brand_id)->toBe($this->brand->id)
        ->and((string) $p->price)->toBe('1200.00')->and((string) $p->sale_price)->toBe('990.00')
        ->and((string) $p->cost)->toBe('600.00')                          // cost is never changed
        ->and($p->sku)->toBe('W-1')
        ->and($p->categories()->pluck('categories.id')->sort()->values()->all())->toBe([$this->watches->id, $this->gifts->id])
        ->and($p->images()->count())->toBe(2);

    $this->engine->rollback($s->fresh(), $this->owner);
    $p = $this->product->fresh();
    expect($p->getTranslation('name', 'ar'))->toBe('ساعة')->and($p->brand_id)->toBeNull()
        ->and((string) $p->price)->toBe('1000.00')->and($p->sale_price)->toBeNull()
        ->and($p->categories()->pluck('categories.id')->all())->toBe([$this->watches->id]);
});

it('adds, removes and reorders images, keeping removed files until rollback restores them', function () {
    ($this->create)(['images_add' => [['url' => 'https://shop.example/new.png']], 'images_remove' => [$this->imgB->id], 'main_image_index' => 0])->assertCreated();
    $s = MarketingSuggestion::query()->firstOrFail();
    expect($s->payload['images_add'][0]['sha256'])->toBe(hash('sha256', productUpdatePng()));

    // review page shows current vs proposed images
    $this->actingAs($this->owner, 'marketer');
    $this->get("/marketer/suggestions/{$s->id}")->assertOk()->assertSee('/marketer/suggestions/image/'.$s->payload['images_add'][0]['sha256'], false);

    [$s, $exec] = ($this->applyIt)();
    expect($exec->status->value)->toBe('succeeded');
    $gallery = $this->product->fresh()->images()->get();
    expect($gallery)->toHaveCount(2)
        ->and($gallery->first()->path)->not->toBe('products/a.avif')           // the added image is now the main one
        ->and($gallery->pluck('path')->all())->toContain('products/a.avif')->not->toContain('products/b.avif');
    Storage::disk(config('media.disk'))->assertExists('products/b.avif');      // removed image file kept for rollback
    $added = $gallery->first()->path;

    $this->engine->rollback($s->fresh(), $this->owner);
    $gallery = $this->product->fresh()->images()->get();
    expect($gallery->pluck('path')->all())->toBe(['products/a.avif', 'products/b.avif']);
    Storage::disk(config('media.disk'))->assertMissing($added);
});

it('updates prices of existing variants and refuses a variant of another product', function () {
    $variable = Product::create(['type' => 'variable', 'name' => ['en' => 'Strap'], 'slug' => 'strap', 'sku' => 'S-1', 'is_active' => true, 'price' => '300.00', 'cost' => '100.00']);
    $bc = fn () => app(\App\Services\BarcodeService::class)->generateUnique();
    $v1 = $variable->variants()->create(['combination_hash' => 'a', 'sku' => 'S-1-A', 'barcode' => $bc()]);
    $v2 = $variable->variants()->create(['combination_hash' => 'b', 'sku' => 'S-1-B', 'barcode' => $bc(), 'price' => '350.00', 'sale_price' => '320.00']);
    $other = Product::create(['type' => 'variable', 'name' => ['en' => 'Other'], 'slug' => 'other', 'sku' => 'O-1']);
    $foreign = $other->variants()->create(['combination_hash' => 'c', 'sku' => 'O-1-A', 'barcode' => $bc()]);
    $body = fn (array $payload) => ['title' => 'Strap prices', 'target_entity_type' => 'product', 'target_entity_id' => $variable->id, 'payload' => $payload];

    ($this->call)('POST', 'suggestions/product_update', $body(['variants' => [['variant_id' => $foreign->id, 'price' => 200]]]))
        ->assertStatus(422)->assertJsonValidationErrors(['payload.variants.0.variant_id']);

    ($this->call)('POST', 'suggestions/product_update', $body(['variants' => [['variant_id' => $v1->id, 'price' => 280], ['variant_id' => $v2->id, 'remove_sale_price' => true]]]))->assertCreated();
    [$s] = ($this->applyIt)();
    expect((string) $v1->fresh()->price)->toBe('280.00')->and($v2->fresh()->sale_price)->toBeNull()->and((string) $v2->fresh()->price)->toBe('350.00');

    $this->engine->rollback($s->fresh(), $this->owner);
    expect($v1->fresh()->price)->toBeNull()->and((string) $v2->fresh()->sale_price)->toBe('320.00');
});

it('refuses selling below cost, a sale above the price, conflicting fields and an empty change', function () {
    ($this->create)(['price' => 500])->assertStatus(422)->assertJsonValidationErrors(['payload.price']);                 // cost 600
    ($this->create)(['sale_price' => 1100])->assertStatus(422)->assertJsonValidationErrors(['payload.sale_price']);       // price 1000
    ($this->create)(['sale_price' => 900, 'remove_sale_price' => true])->assertStatus(422)->assertJsonValidationErrors(['payload.remove_sale_price']);
    ($this->create)(['main_image_id' => $this->imgA->id, 'main_image_index' => 0])->assertStatus(422);
    ($this->create)(['images_remove' => [999999]])->assertStatus(422)->assertJsonValidationErrors(['payload.images_remove']);
    ($this->create)(['images_add' => [['url' => 'https://intranet.example/x.png']]])->assertStatus(422)->assertJsonValidationErrors(['payload.images_add.0.url']);
    ($this->create)(['cost' => 10])->assertStatus(422)->assertJsonValidationErrors(['payload.cost']);                   // unknown field: cost is never changed
    ($this->create)([])->assertStatus(422);
    ($this->call)('POST', 'suggestions/product_update', ['title' => 'No target', 'payload' => ['price' => 1500]])->assertStatus(422);
    expect(MarketingSuggestion::count())->toBe(0);
});

it('hides and re-activates a product, but never activates a draft', function () {
    ($this->create)(['status' => 'hidden'])->assertCreated();
    ($this->applyIt)();
    expect($this->product->fresh()->is_active)->toBeFalse();

    $draft = Product::create(['type' => 'simple', 'name' => ['en' => 'Draft'], 'slug' => 'draft', 'sku' => 'D-1', 'is_draft' => true, 'price' => '100.00']);
    ($this->call)('POST', 'suggestions/product_update', ['title' => 'Publish draft', 'target_entity_type' => 'product', 'target_entity_id' => $draft->id, 'payload' => ['status' => 'active']])
        ->assertStatus(422)->assertJsonValidationErrors(['payload.status']);
});

it('needs ai_products.approve to approve a product update', function () {
    ($this->create)(['price' => 1500])->assertCreated();
    $s = MarketingSuggestion::query()->firstOrFail();
    $staff = makeMarketer(['ai_suggestions.view', 'ai_suggestions.approve', 'ai_suggestions.apply']);
    expect(fn () => $this->engine->approve($s->fresh(), $staff))->toThrow(SuggestionException::class);
});

it('needs the product_update write scope', function () {
    $tok = app(MarketingTokenService::class)->create(new MarketingTokenData('other', ['marketing:read', SuggestionScopes::for('product')], null, null))[1];
    $this->flushHeaders()->withHeaders(['Accept' => 'application/json', 'Authorization' => 'Bearer '.$tok])
        ->postJson('/api/internal/marketing/suggestions/product_update', ['title' => 'x', 'target_entity_type' => 'product', 'target_entity_id' => $this->product->id, 'payload' => ['price' => 1500]])
        ->assertForbidden();
});

it('grants the product_update scope to existing product-writing tokens only', function () {
    $svc = app(MarketingTokenService::class);
    $writer = \App\Models\MarketingApiToken::query()->findOrFail($svc->create(new MarketingTokenData('writer', ['marketing:read', SuggestionScopes::for('product')], null, null))[0]->id);
    $reader = \App\Models\MarketingApiToken::query()->findOrFail($svc->create(new MarketingTokenData('reader', ['marketing:read'], null, null))[0]->id);

    $m = require database_path('migrations/2027_01_19_000000_grant_product_update_suggestion_scope.php');
    $m->up();
    $m->up();   // idempotent
    expect($writer->fresh()->scopes)->toBe(['marketing:read', SuggestionScopes::for('product'), SuggestionScopes::for('product_update')])
        ->and($reader->fresh()->scopes)->toBe(['marketing:read']);

    $m->down();
    expect($writer->fresh()->scopes)->toBe(['marketing:read', SuggestionScopes::for('product')]);
});
