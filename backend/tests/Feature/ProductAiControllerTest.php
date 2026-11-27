<?php

// Product edit → AI tools endpoints (PRODUCT_AI_FREELLMAPI_PLAN.md §3.1): auth, products.edit, the product-ai throttle, not_configured,
// the error contract {message, code}, photo sources and the title endpoints. FreeLLMAPI and the search script are faked.

use App\Models\Admin;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\BarcodeService;
use App\Support\Http\SafePageFetcher;
use Database\Seeders\FoundationSeeder;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Spatie\Permission\PermissionRegistrar;

function aiAnswer(array $json): PromiseInterface
{
    return Http::response(['choices' => [['message' => ['content' => json_encode($json, JSON_UNESCAPED_UNICODE)]]]], 200, ['X-Routed-Via' => 'gemini/gemini-2.5-flash']);
}

beforeEach(function () {
    $this->seed(FoundationSeeder::class);
    Cache::flush();
    $this->withoutVite();
    config(['product_ai.base_url' => 'http://llm.test/v1', 'product_ai.api_key' => 'freellmapi-test', 'product_ai.models.text' => ['auto'], 'product_ai.models.vision' => ['auto']]);
    $this->owner = Admin::query()->where('email', 'owner@egy.test')->first();
    $this->actingAs($this->owner, 'admin');
    $this->product = Product::create(['type' => 'variable', 'name' => ['en' => 'Philips Air Fryer', 'ar' => 'قلاية فيليبس'], 'slug' => 'fryer', 'sku' => 'PH-1']);
    $attr = Attribute::create(['name' => ['en' => 'Color', 'ar' => 'اللون'], 'code' => 'color', 'type' => 'color']);
    $black = AttributeValue::create(['attribute_id' => $attr->id, 'value' => ['en' => 'Black', 'ar' => 'أسود']]);
    $this->variant = ProductVariant::create(['product_id' => $this->product->id, 'combination_hash' => (string) $black->id, 'sku' => 'PH-1-B',
        'is_active' => true, 'barcode' => app(BarcodeService::class)->generateUnique()]);
    $this->variant->values()->attach($black->id);
    $this->ai = fn (string $action) => route('admin.products.ai.'.$action, $this->product);
    Process::fake(['*' => Process::result(json_encode(['results' => [], 'errors' => []]))]);
});

it('needs a signed-in admin with products.edit', function () {
    auth('admin')->logout();
    $this->postJson(($this->ai)('titles'), ['target_type' => 'product'])->assertUnauthorized();

    $viewer = Admin::create(['name' => 'V', 'email' => 'v@egy.test', 'password' => 'password', 'is_active' => true]);
    $viewer->givePermissionTo('products.view');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->actingAs($viewer->fresh(), 'admin');
    foreach (['identify' => ['source' => 'product'], 'prices' => ['query' => 'x y'], 'titles' => ['target_type' => 'product'],
        'titles.apply' => ['target_type' => 'product', 'locale' => 'en', 'title' => 'A good title here']] as $action => $body) {
        $this->postJson(($this->ai)($action), $body)->assertForbidden();
    }
    $this->getJson(($this->ai)('status'))->assertForbidden();
    Http::assertNothingSent();
    Process::assertNothingRan();
});

it('answers 503 not_configured without a base URL and key, and status says so', function () {
    config(['product_ai.api_key' => '']);
    Http::fake();
    $this->getJson(($this->ai)('status'))->assertOk()->assertJsonPath('data.configured', false);
    foreach (['identify' => ['source' => 'product'], 'prices' => ['query' => 'philips fryer'], 'titles' => ['target_type' => 'product']] as $action => $body) {
        $this->postJson(($this->ai)($action), $body)->assertStatus(503)
            ->assertJson(['success' => false, 'code' => 'not_configured', 'message' => __('admin.product_ai.not_configured')]);
    }
    Http::assertNothingSent();
    Process::assertNothingRan();
});

it('throttles AI requests per admin with code throttled', function () {
    config(['product_ai.throttle_per_minute' => 2, 'product_ai.api_key' => '']);
    $this->postJson(($this->ai)('titles'), ['target_type' => 'product'])->assertStatus(503);
    $this->postJson(($this->ai)('titles'), ['target_type' => 'product'])->assertStatus(503);
    $this->postJson(($this->ai)('titles'), ['target_type' => 'product'])->assertStatus(429)->assertJsonPath('code', 'throttled')->assertHeader('Retry-After');
    $this->getJson(($this->ai)('status'))->assertOk(); // status is not part of the AI quota
});

it('suggests titles for a variant with competitor titles, then saves one locale', function () {
    Http::fake(['llm.test/*' => aiAnswer(['ar' => ['قلاية فيليبس الهوائية 4.1 لتر أسود'], 'en' => ['Philips Essential Air Fryer 4.1 L Black', 'x']])]);

    $this->postJson(($this->ai)('titles'), ['target_type' => 'variant', 'target_id' => $this->variant->id, 'use_competitor_titles' => true,
        'competitor_titles' => ['Philips HD9200/90 Airfryer <b>Black</b>']])
        ->assertOk()->assertJsonPath('data.titles.en', ['Philips Essential Air Fryer 4.1 L Black'])->assertJsonPath('data.titles.ar.0', 'قلاية فيليبس الهوائية 4.1 لتر أسود')
        ->assertJsonPath('data.target', ['type' => 'variant', 'id' => $this->variant->id, 'label' => $this->variant->fresh('values')->label(), 'current' => ['ar' => null, 'en' => null]])
        ->assertJsonPath('data.answered_by', 'gemini/gemini-2.5-flash');
    Http::assertSent(fn (HttpRequest $r) => str_contains($r->data()['messages'][1]['content'], 'Philips HD9200/90 Airfryer Black')
        && str_contains($r->data()['messages'][1]['content'], 'PH-1-B'));

    $this->postJson(($this->ai)('titles.apply'), ['target_type' => 'variant', 'target_id' => $this->variant->id, 'locale' => 'en', 'title' => 'Philips Essential Air Fryer 4.1 L Black'])
        ->assertOk()->assertJsonPath('data.target.current.en', 'Philips Essential Air Fryer 4.1 L Black')->assertJsonPath('data.locale', 'en');
    expect($this->variant->fresh()->getTranslations('name'))->toBe(['en' => 'Philips Essential Air Fryer 4.1 L Black']);

    $this->postJson(($this->ai)('titles.apply'), ['target_type' => 'product', 'locale' => 'en', 'title' => 'short'])->assertStatus(422)->assertJsonValidationErrors('title');
});

it('maps gateway failures to the error contract', function () {
    Http::fake(['llm.test/*' => Http::response(['error' => ['code' => 'quota_exceeded']], 429, ['Retry-After' => '45'])]);
    $this->postJson(($this->ai)('titles'), ['target_type' => 'product'])->assertStatus(429)
        ->assertJson(['code' => 'quota_exceeded', 'message' => __('admin.product_ai.error_quota')])->assertHeader('Retry-After', '45');

    Cache::flush();
    Http::swap(new Factory);
    Http::fake(['llm.test/*' => Http::failedConnection()]);
    $this->postJson(($this->ai)('titles'), ['target_type' => 'product'])->assertStatus(503)->assertJsonPath('code', 'gateway_down');
});

it('identifies an uploaded photo, needs a photo for the product source, and checks the upload', function () {
    Http::fake(['llm.test/*' => aiAnswer(['brand' => 'Philips', 'name' => 'Essential Airfryer', 'model' => 'HD9200/90', 'size' => '4.1 L',
        'search_queries' => ['Philips HD9200 air fryer', 'قلاية فيليبس HD9200']])]);

    $this->postJson(($this->ai)('identify'), ['source' => 'upload', 'image' => UploadedFile::fake()->image('fryer.jpg', 300, 300)])
        ->assertOk()->assertJsonPath('data.brand', 'Philips')->assertJsonPath('data.model', 'HD9200/90')->assertJsonPath('data.found', true)
        ->assertJsonPath('data.search_queries.0', 'Philips HD9200 air fryer')->assertJsonPath('data.cached_at', null);
    Http::assertSent(fn (HttpRequest $r) => str_starts_with($r->data()['messages'][0]['content'][1]['image_url']['url'] ?? '', 'data:image/jpeg;base64,'));

    $this->postJson(($this->ai)('identify'), ['source' => 'product'])->assertStatus(422)->assertJsonPath('errors.image.0', __('admin.product_ai.no_primary'));
    $this->postJson(($this->ai)('identify'), ['source' => 'upload', 'image' => UploadedFile::fake()->create('big.jpg', 6000, 'image/jpeg')])->assertStatus(422)->assertJsonValidationErrors('image');
    $this->postJson(($this->ai)('identify'), ['source' => 'upload', 'image' => UploadedFile::fake()->create('x.gif', 10, 'image/gif')])->assertStatus(422)->assertJsonValidationErrors('image');
    $this->postJson(($this->ai)('identify'), ['source' => 'variant', 'variant_id' => 999999])->assertStatus(422)->assertJsonValidationErrors('variant_id');
    Http::assertSentCount(1);
});

it('runs a marketplace price search', function () {
    Process::fake(['*' => Process::result(json_encode(['results' => [['title' => 'Fryer', 'url' => 'https://www.amazon.eg/dp/1', 'snippet' => 'Buy', 'engine' => 'bing']], 'errors' => []]))]);
    app()->instance(SafePageFetcher::class, new SafePageFetcher(fn () => ['93.184.216.34']));
    Http::fake(['https://www.amazon.eg/*' => Http::response('<meta property="product:price:amount" content="1500"><meta property="product:price:currency" content="EGP">')]);

    $this->postJson(($this->ai)('prices'), ['query' => 'Philips HD9200', 'target_type' => 'variant', 'target_id' => $this->variant->id])
        ->assertOk()->assertJsonPath('data.rows.0.price', 1500)->assertJsonPath('data.rows.0.price_source', 'structured_data')
        ->assertJsonPath('data.stats.median', 1500)->assertJsonPath('data.cached_at', null);
    $this->postJson(($this->ai)('prices'), ['query' => 'x'])->assertStatus(422)->assertJsonValidationErrors('query');
});
