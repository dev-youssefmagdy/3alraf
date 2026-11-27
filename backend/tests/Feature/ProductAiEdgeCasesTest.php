<?php

// Product AI edge cases (QA, PRODUCT_AI_FREELLMAPI_PLAN.md §5): SSRF through redirects and oversized pages, price formats and quote
// checks, every model cooling down, unusable LLM answers, title input limits, variants of another product (IDOR), disguised uploads,
// the per-admin throttle and the products.edit gate. No real network: stray HTTP requests fail the test.

use App\Models\Admin;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\BarcodeService;
use App\Services\Llm\FreeLlmClient;
use App\Services\Llm\LlmUnavailableException;
use App\Services\ProductAi\PriceExtractor;
use App\Support\Http\SafePageFetcher;
use Database\Seeders\FoundationSeeder;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\PermissionRegistrar;

function paiEdgeAnswer(string $content): PromiseInterface
{
    return Http::response(['choices' => [['message' => ['content' => $content]]]], 200, ['X-Routed-Via' => 'groq/llama']);
}

function paiEdgeVariant(Product $product, string $sku): ProductVariant
{
    return ProductVariant::create(['product_id' => $product->id, 'combination_hash' => $sku, 'sku' => $sku, 'is_active' => true,
        'barcode' => app(BarcodeService::class)->generateUnique()]);
}

beforeEach(function () {
    Http::preventStrayRequests();
    Cache::flush();
    config(['product_ai.base_url' => 'http://llm.test/v1', 'product_ai.api_key' => 'freellmapi-test', 'product_ai.models.text' => ['auto', 'auto:fast'],
        'product_ai.models.vision' => ['auto']]);
    Process::fake(['*' => Process::result(json_encode(['results' => [], 'errors' => []]))]);
});

describe('SafePageFetcher', function () {
    beforeEach(function () {
        $this->fetcher = new SafePageFetcher(fn (string $host) => $host === 'evil.test' ? ['10.1.2.3'] : ['93.184.216.34']);
    });

    it('refuses a redirect to a private or metadata address', function (string $location) {
        Http::fake(['https://shop.test/*' => Http::response('', 302, ['Location' => $location])]);

        expect(fn () => $this->fetcher->fetch('https://shop.test/p/1'))->toThrow(RuntimeException::class, 'blocked_address');
        Http::assertSentCount(1);
    })->with(['metadata IP' => 'http://169.254.169.254/latest/meta-data', 'host resolving to 10/8' => 'https://evil.test/admin', 'loopback' => 'http://127.0.0.1/']);

    it('refuses a body over the size cap and a binary file', function () {
        config(['product_ai.pages.max_bytes' => 1000]);
        Http::fake([
            'https://shop.test/big' => Http::response(str_repeat('a', 1001)),
            'https://shop.test/declared' => Http::response('<p>small</p>', 200, ['Content-Length' => '5000000']),
            'https://shop.test/pdf' => Http::response('%PDF-1.7 binary'),
        ]);

        expect(fn () => $this->fetcher->fetch('https://shop.test/big'))->toThrow(RuntimeException::class, 'too_large')
            ->and(fn () => $this->fetcher->fetch('https://shop.test/declared'))->toThrow(RuntimeException::class, 'too_large')
            ->and(fn () => $this->fetcher->fetch('https://shop.test/pdf'))->toThrow(RuntimeException::class, 'not_html');
    });
});

describe('PriceExtractor', function () {
    it('verifies quotes in Egyptian price formats', function (float $price, string $quote) {
        $source = "منتج أصلي ضمان سنة. {$quote} شامل الضريبة";

        expect(PriceExtractor::verify($price, $quote, $source))->toBeTrue();
    })->with([
        'Arabic-Indic digits with ج.م' => [1299.0, 'السعر ١٬٢٩٩ ج.م'],
        'Arabic decimal separator' => [1299.5, '١٢٩٩٫٥٠ جنيه'],
        'EGP after, comma thousands' => [12499.0, '12,499 EGP'],
        'EGP glued before' => [1299.0, 'EGP1,299.00'],
        'LE' => [850.0, 'LE 850'],
    ]);

    it('rejects a quote whose number is not the price, or that is not in the text', function (float $price, string $quote) {
        $source = 'Philips HD9200/90 Airfryer 1400W, 4.1 L. Price: 12,990 EGP. Model SM-A515';

        expect(PriceExtractor::verify($price, $quote, $source))->toBeFalse();
    })->with([
        'thousands, not decimals' => [12.99, 'Price: 12,990 EGP'],
        'model number' => [9200.0, 'Philips HD9200/90'],
        'model suffix' => [90.0, 'HD9200/90'],
        'model with dash' => [515.0, 'Model SM-A515'],
        'hallucinated quote' => [999.0, 'Price: 999 EGP'],
    ]);

    it('maps ج.م to EGP and drops a hallucinated LLM price', function () {
        Http::fake(['llm.test/*' => paiEdgeAnswer(json_encode(['items' => [
            ['id' => 'a', 'price' => 1299, 'currency' => 'ج.م', 'in_stock' => true, 'quote' => 'بسعر ١٬٢٩٩ ج.م'],
            ['id' => 'b', 'price' => 1450, 'currency' => 'EGP', 'in_stock' => null, 'quote' => 'EGP 1,450'],
        ]], JSON_UNESCAPED_UNICODE))]);

        $res = app(PriceExtractor::class)->fromText([
            'a' => ['text' => 'قلاية فيليبس بسعر ١٬٢٩٩ ج.م توصيل مجاني', 'source' => 'snippet'],
            'b' => ['text' => 'Philips air fryer, call for price', 'source' => 'snippet'],
        ]);

        expect($res['prices'])->toHaveKeys(['a'])->not->toHaveKey('b')
            ->and($res['prices']['a'])->toMatchArray(['price' => 1299.0, 'currency' => 'EGP', 'in_stock' => true])
            ->and($res['dropped'])->toBe(1);
    });
});

describe('FreeLlmClient', function () {
    it('sends nothing and throws quota_exceeded when every model is cooling down', function () {
        Http::fake(['llm.test/*' => Http::response(['error' => ['code' => 'quota_exceeded']], 429, ['Retry-After' => '90'])]);
        $llm = app(FreeLlmClient::class);
        expect(fn () => $llm->chat([['role' => 'user', 'content' => 'x']]))->toThrow(LlmUnavailableException::class);
        Http::assertSentCount(2);

        expect(fn () => $llm->chat([['role' => 'user', 'content' => 'y']]))
            ->toThrow(fn (LlmUnavailableException $e) => expect($e->errorCode)->toBe('quota_exceeded')->and($e->retryAfter)->toBeBetween(1, 90)
                ->and($e->failures)->toBe(['auto' => 'cooling_down', 'auto:fast' => 'cooling_down']));
        Http::assertSentCount(2);
    });
});

describe('endpoints', function () {
    beforeEach(function () {
        $this->seed(FoundationSeeder::class);
        $this->withoutVite();
        $this->owner = Admin::query()->where('email', 'owner@egy.test')->first();
        $this->actingAs($this->owner, 'admin');
        $this->product = Product::create(['type' => 'variable', 'name' => ['en' => 'Philips Air Fryer', 'ar' => 'قلاية فيليبس'], 'slug' => 'fryer', 'sku' => 'PH-1']);
        $this->variant = paiEdgeVariant($this->product, 'PH-1-B');
        $other = Product::create(['type' => 'variable', 'name' => ['en' => 'Other product'], 'slug' => 'other', 'sku' => 'OT-1']);
        $this->foreign = paiEdgeVariant($other, 'OT-1-A');
        $this->ai = fn (string $action) => route('admin.products.ai.'.$action, $this->product);
    });

    it('answers gateway_down with the bad-answer message when no model returns JSON', function () {
        Http::fake(['llm.test/*' => paiEdgeAnswer('Here are some great titles: 1. Philips Air Fryer')]);

        $this->postJson(($this->ai)('titles'), ['target_type' => 'product'])->assertStatus(503)
            ->assertJson(['success' => false, 'code' => 'gateway_down', 'message' => __('admin.product_ai.error_bad_answer')]);
        Http::assertSentCount(2);
    });

    it('refuses a variant of another product on every endpoint', function (string $action, array $body, string $field) {
        Http::fake();

        $body = array_map(fn ($v) => $v === 'FOREIGN' ? $this->foreign->id : $v, $body);

        $this->postJson(($this->ai)($action), $body)->assertStatus(422)
            ->assertJsonPath("errors.$field.0", __('admin.product_ai.bad_variant'));

        Http::assertNothingSent();
        Process::assertNothingRan();
        expect($this->foreign->fresh()->getTranslations('name'))->toBe([])
            ->and(Activity::query()->where('log_name', 'product_ai')->count())->toBe(0);
    })->with([
        'apply' => ['titles.apply', ['target_type' => 'variant', 'target_id' => 'FOREIGN', 'locale' => 'en', 'title' => 'Stolen variant title'], 'target_id'],
        'titles' => ['titles', ['target_type' => 'variant', 'target_id' => 'FOREIGN'], 'target_id'],
        'prices' => ['prices', ['query' => 'Philips HD9200', 'target_type' => 'variant', 'target_id' => 'FOREIGN'], 'target_id'],
        'identify' => ['identify', ['source' => 'variant', 'variant_id' => 'FOREIGN'], 'variant_id'],
    ]);

    it('answers 404 for a product that does not exist', function () {
        $this->postJson(route('admin.products.ai.titles', 999999), ['target_type' => 'product'])->assertNotFound();
    });

    it('refuses a locale outside the store locales and a title outside 10 to 120 characters', function (array $body, string $field) {
        $this->postJson(($this->ai)('titles.apply'), ['target_type' => 'product', ...$body])->assertStatus(422)->assertJsonValidationErrors($field);

        expect($this->product->fresh()->getTranslations('name'))->toEqual(['en' => 'Philips Air Fryer', 'ar' => 'قلاية فيليبس']);
    })->with([
        'locale fr' => [['locale' => 'fr', 'title' => 'Philips Essential Air Fryer'], 'locale'],
        '121 characters' => [['locale' => 'en', 'title' => str_repeat('a', 121)], 'title'],
        'only tags left' => [['locale' => 'en', 'title' => '<b></b><i></i><u>ab</u>'], 'title'],
    ]);

    it('saves an HTML title as plain text', function () {
        $this->postJson(($this->ai)('titles.apply'), ['target_type' => 'product', 'locale' => 'en', 'title' => '<b>Philips</b> Essential <script>x</script>Air Fryer'])
            ->assertOk()->assertJsonPath('data.title', 'Philips Essential xAir Fryer');

        expect($this->product->fresh()->getTranslation('name', 'en'))->toBe('Philips Essential xAir Fryer');
    });

    it('refuses a PHP file renamed to .jpg without calling the gateway', function () {
        Http::fake();

        $this->postJson(($this->ai)('identify'), ['source' => 'upload', 'image' => UploadedFile::fake()->createWithContent('photo.jpg', '<?php system($_GET["c"]); ?>')])
            ->assertStatus(422)->assertJsonValidationErrors('image');

        Http::assertNothingSent();
    });

    it('throttles each admin separately', function () {
        config(['product_ai.throttle_per_minute' => 1, 'product_ai.api_key' => '']);
        $this->postJson(($this->ai)('titles'), ['target_type' => 'product'])->assertStatus(503);
        $this->postJson(($this->ai)('titles'), ['target_type' => 'product'])->assertStatus(429)->assertJsonPath('code', 'throttled');

        $editor = Admin::create(['name' => 'E', 'email' => 'e@egy.test', 'password' => 'password', 'is_active' => true]);
        $editor->givePermissionTo('products.edit');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs($editor->fresh(), 'admin');

        $this->postJson(($this->ai)('titles'), ['target_type' => 'product'])->assertStatus(503)->assertJsonPath('code', 'not_configured');
    });
});
