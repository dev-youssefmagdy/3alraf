<?php

// POST products/{id}/image-suggestion: product / variant images uploaded in one call become a product_update suggestion (pending review);
// approve + apply puts them on the product and its variants, rollback restores the old images.

use App\DTOs\Marketing\MarketingTokenData;
use App\Models\MarketingSuggestion;
use App\Models\Product;
use App\Services\BarcodeService;
use App\Services\Marketing\MarketingTokenService;
use App\Services\Marketing\Suggestions\SuggestionEngine;
use App\Support\Marketing\SuggestionScopes;
use Database\Seeders\FoundationSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(FoundationSeeder::class);
    Cache::flush();
    Storage::fake(config('media.private_disk'));
    Storage::fake(config('media.disk'));
    config(['app.url' => 'http://3alraf.test', 'app.host' => '3alraf.test']);
    $this->owner = marketerOwner();
    $this->engine = app(SuggestionEngine::class);
    $token = fn (array $scopes) => app(MarketingTokenService::class)->create(new MarketingTokenData('t', $scopes, null, null))[1];
    $this->writer = $token(['marketing:read', SuggestionScopes::for('product_update')]);
    $this->mediaOnly = $token(['media:write']);
    $this->product = listProduct(Product::create(['type' => 'variable', 'name' => ['en' => 'Liner', 'ar' => 'قلم كحل'], 'description' => ['en' => 'x'], 'slug' => 'liner', 'sku' => 'RB-1',
        'barcode' => app(BarcodeService::class)->generateUnique()]), ['price' => '75', 'cost' => '42', 'is_active' => true]);
    $mk = fn (string $sku) => $this->product->variants()->create(['combination_hash' => $sku, 'sku' => $sku, 'barcode' => app(BarcodeService::class)->generateUnique(), 'is_active' => true, 'price' => 75, 'cost' => 42]);
    $this->gold = $mk('RB-1-108');
    $this->green = $mk('RB-1-117');
    $this->other = Product::create(['type' => 'simple', 'name' => ['en' => 'Other'], 'description' => ['en' => 'x'], 'slug' => 'other', 'sku' => 'OT-1', 'barcode' => app(BarcodeService::class)->generateUnique()]);
    $this->foreign = $this->other->variants()->create(['combination_hash' => 'f', 'sku' => 'OT-1-A', 'barcode' => app(BarcodeService::class)->generateUnique(), 'is_active' => true]);
    Storage::disk(config('media.disk'))->put('products/old.avif', 'old');
    Storage::disk(config('media.disk'))->put('variants/old-gold.avif', 'og');
    $this->oldImage = $this->product->images()->create(['path' => 'products/old.avif', 'sort_order' => 1]);
    $this->oldGold = $this->gold->images()->create(['path' => 'variants/old-gold.avif', 'sort_order' => 1]);
    $this->send = fn (array $body, ?string $tok = null) => $this->flushHeaders()->withHeaders(['Accept' => 'application/json', 'Authorization' => 'Bearer '.($tok ?? $this->writer)])
        ->post('/api/internal/marketing/products/'.$this->product->id.'/image-suggestion', $body);
    $this->img = fn (string $name = 'a.jpg', int $w = 600) => UploadedFile::fake()->image($name, $w, $w);
});

it('creates a pending product_update suggestion and changes nothing until it is applied', function () {
    $r = ($this->send)(['main' => ($this->img)('main.jpg'), 'variants' => [$this->gold->id => ($this->img)('gold.jpg'), $this->green->id => ($this->img)('green.jpg')], 'replace_gallery' => 1])
        ->assertCreated()->assertJsonPath('data.images_added', 1)->assertJsonPath('data.images_removed', 1)->assertJsonPath('data.variant_images', 2);
    $s = MarketingSuggestion::findOrFail($r->json('data.id'));
    expect($s->type->value)->toBe('product_update')->and($s->target_entity_id)->toBe($this->product->id)
        ->and($s->payload['images_add'][0]['url'])->toStartWith('upload://')->and($s->payload['images_add'][0])->toHaveKey('sha256')
        ->and(array_column($s->payload['variant_images'], 'variant_id'))->toBe([$this->gold->id, $this->green->id])
        ->and($r->json('data.review_url'))->toContain('/marketer/suggestions/'.$s->id);
    expect($this->product->images()->pluck('id')->all())->toBe([$this->oldImage->id])->and($this->gold->images()->pluck('id')->all())->toBe([$this->oldGold->id])
        ->and($this->green->images()->count())->toBe(0);
    $rows = collect($this->engine->compare($s->fresh()))->pluck('field')->all();
    expect($rows)->toContain('images')->toContain('variant_images.'.$this->gold->id)->toContain('variant_images.'.$this->green->id);
});

it('applies gallery + variant images after approval and rolls them back', function () {
    $r = ($this->send)(['main' => ($this->img)(), 'variants' => [$this->gold->id => ($this->img)('g.jpg'), $this->green->id => ($this->img)('n.jpg')], 'replace_gallery' => 1])->assertCreated();
    $s = MarketingSuggestion::findOrFail($r->json('data.id'));
    $this->engine->approve($s->fresh(), $this->owner);
    $this->engine->apply($s->fresh(), $this->owner);

    $gallery = $this->product->fresh()->images()->get();
    expect($gallery)->toHaveCount(1)->and($gallery->first()->id)->not->toBe($this->oldImage->id)
        ->and($this->gold->fresh()->images()->pluck('id')->all())->not->toContain($this->oldGold->id)->and($this->gold->images()->count())->toBe(1)
        ->and($this->green->fresh()->images()->count())->toBe(1);
    Storage::disk(config('media.disk'))->assertExists('variants/old-gold.avif');   // replaced file kept for rollback

    $this->engine->rollback($s->fresh(), $this->owner);
    expect($this->product->fresh()->images()->pluck('path')->all())->toBe(['products/old.avif'])
        ->and($this->gold->fresh()->images()->pluck('path')->all())->toBe(['variants/old-gold.avif'])
        ->and($this->green->fresh()->images()->count())->toBe(0);
});

it('keeps the current gallery when replace_gallery is off', function () {
    $r = ($this->send)(['gallery' => [($this->img)('x.jpg'), ($this->img)('y.jpg')]])->assertCreated()->assertJsonPath('data.images_removed', 0);
    $s = MarketingSuggestion::findOrFail($r->json('data.id'));
    expect($s->payload)->not->toHaveKey('images_remove')->not->toHaveKey('main_image_index')->and($s->payload['images_add'])->toHaveCount(2);
});

it('refuses foreign variants, non-images, empty calls and the wrong scope', function () {
    $r = ($this->send)(['variants' => [$this->foreign->id => ($this->img)()]]);
    expect($r->status())->toBe(422);
    ($this->send)(['main' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')])->assertStatus(422);
    ($this->send)([])->assertStatus(422);
    ($this->send)(['main' => ($this->img)()], $this->mediaOnly)->assertForbidden();
    expect(MarketingSuggestion::count())->toBe(0);
});

it('grants existing marketers every marketer permission and media:write tokens the product_update scope', function () {
    $plain = makeMarketer(['ai_media.view'], null, 'plain@egy.test');
    $studio = app(MarketingTokenService::class)->create(new MarketingTokenData('studio', ['media:write'], null, null))[0];
    $readOnly = app(MarketingTokenService::class)->create(new MarketingTokenData('reader', ['marketing:read'], null, null))[0];
    (require database_path('migrations/2027_01_24_000000_grant_image_suggestion_permissions.php'))->up();
    expect($plain->fresh()->can('ai_products.approve'))->toBeTrue()->and($plain->fresh()->can('ai_suggestions.apply'))->toBeTrue()
        ->and($studio->fresh()->scopes)->toContain(SuggestionScopes::for('product_update'))
        ->and($readOnly->fresh()->scopes)->not->toContain(SuggestionScopes::for('product_update'));
});
