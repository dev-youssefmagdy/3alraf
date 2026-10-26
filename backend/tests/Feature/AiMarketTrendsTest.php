<?php

use App\Enums\AiDriverType;
use App\Exceptions\AiException;
use App\Jobs\RunGlobalMarketTrendsJob;
use App\DTOs\Ai\MarketTrendQuery;
use App\Services\Ai\GlobalMarketTrendService;
use App\Models\AiModel;
use App\Models\AiProvider;
use App\Models\AiTenantSetting;
use App\Models\AiTrendSnapshot;
use App\Models\Branch;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\TenantAdmin;
use App\Models\TenantProduct;
use App\Services\Ai\TrendService;
use Database\Seeders\FoundationSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function () {
    $this->seed(FoundationSeeder::class);
    Cache::flush();
    RateLimiter::clear('ai:1');
    $this->tenant = Tenant::where('slug', 'egy')->first();
    $this->other = Tenant::firstOrCreate(['slug' => 'sa'], ['name' => 'KSA', 'country' => 'SA', 'currency_code' => 'SAR', 'currency_symbol' => 'SAR', 'currency_rate' => 1, 'default_locale' => 'ar', 'locales' => ['ar', 'en'], 'timezone' => 'Asia/Riyadh', 'is_active' => true]);
    app()->instance('currentTenant', $this->tenant);
    RateLimiter::clear('ai-trends:refresh:'.$this->tenant->id);
    $p = AiProvider::create(['driver' => AiDriverType::OPENAI, 'name' => 'oa', 'api_key' => 'sk-test-1234567', 'is_active' => true]);
    $this->model = AiModel::create(['ai_provider_id' => $p->id, 'model_id' => 'gpt-web', 'label' => 'Web', 'kind' => 'text', 'capabilities' => ['tools' => true, 'web_search' => true], 'is_active' => true]);
    AiTenantSetting::withoutGlobalScopes()->updateOrCreate(['tenant_id' => $this->tenant->id], ['ai_enabled' => true, 'features' => ['trends' => true], 'allowed_model_ids' => [$this->model->id], 'default_ai_model_id' => $this->model->id]);
    $this->owner = fn () => $this->actingAs(TenantAdmin::withoutGlobalScopes()->where('email', 'owner@egy.test')->first(), 'tenant');
    $this->base = 'http://egy.3alraf.test/admin/ai/market-trends';
    $this->branchId = fn (Tenant $t) => Branch::withoutGlobalScopes()->where('tenant_id', $t->id)->value('id') ?? Branch::first()->id;
    $this->product = function (Tenant $t, string $slug, string $name, int $stock = 10, string $cost = '20') {
        $p = Product::create(['type' => 'simple', 'name' => ['en' => $name, 'ar' => $name], 'slug' => $slug, 'sku' => strtoupper($slug), 'barcode' => app(\App\Services\BarcodeService::class)->generateUnique()]);
        TenantProduct::create(['tenant_id' => $t->id, 'product_id' => $p->id, 'price' => '50.00', 'cost' => $cost, 'is_active' => true]);
        StockLevel::create(['tenant_id' => $t->id, 'branch_id' => ($this->branchId)($t), 'product_id' => $p->id, 'product_variant_id' => 0, 'on_hand' => $stock, 'reserved' => 0, 'avg_cost' => $cost]);

        return $p;
    };
    $this->sale = function (Tenant $t, Product $p, int $qty, int $daysAgo, string $status = 'confirmed', string $price = '50.00', string $cost = '20', int $returned = 0) {
        static $n = 0;
        $o = Order::create(['tenant_id' => $t->id, 'number' => 'TR-'.$t->id.'-'.(++$n), 'public_token' => (string) \Illuminate\Support\Str::uuid(), 'customer_name' => 'X', 'customer_phone' => '01001234567', 'address' => 'addr', 'subtotal' => '0', 'total' => '0', 'status' => $status, 'currency' => $t->currency_code, 'placed_at' => now()]);
        $o->forceFill(['created_at' => now()->subDays($daysAgo)->subHour()])->saveQuietly();
        OrderItem::create(['tenant_id' => $t->id, 'order_id' => $o->id, 'product_id' => $p->id, 'name' => 'n', 'sku' => $p->sku, 'unit_price' => $price, 'unit_cost' => $cost, 'quantity' => $qty, 'line_subtotal' => bcmul($price, (string) $qty, 2), 'returned_qty' => $returned]);
    };
    $this->json = fn (array $items) => ['choices' => [['message' => ['content' => json_encode(['items' => $items])], 'finish_reason' => 'stop']], 'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 50]];
});

$q = fn (array $c = ['EG'], int $count = 10, string $kw = '') => new MarketTrendQuery($c, 30, $kw, $count);
$item = fn (string $name, array $extra = []) => ['name' => $name, 'why' => 'viral', 'countries' => ['EG'], 'strength' => 80, 'confidence' => 0.7, 'prices' => ['EG' => ['min' => 10, 'max' => 20]], 'keywords' => ['k'], 'sources' => ['https://a.test'], ...$extra];

it('evaluates in stock, out of stock, inactive and not in catalog server-side', function () use ($q, $item) {
    $in = ($this->product)($this->tenant, 'mk-in', 'Zorbix Camera Alpha', 5);
    $zero = ($this->product)($this->tenant, 'mk-zero', 'Quantum Lamp Deluxe', 0);
    $off = ($this->product)($this->tenant, 'mk-off', 'Nebula Speaker Max', 9);
    TenantProduct::where('product_id', $off->id)->update(['is_active' => false]);
    $svc = app(GlobalMarketTrendService::class);
    $parsed = $svc->parse(json_encode(['items' => [$item('Zorbix Camera Alpha'), $item('Quantum Lamp Deluxe'), $item('Nebula Speaker Max'), $item('Totally Unknown Gadget')]]), ['EG'], 10);
    $out = $svc->evaluate($parsed);
    expect(array_column(array_column($out, 'match'), 'status'))->toBe(['in_stock', 'out_of_stock', 'out_of_stock', 'not_in_catalog']);
    expect($out[0]['match']['qty'])->toBe(5)->and($out[0]['match']['price'])->toBe('50.00')->and($out[3]['match'])->toBe(['status' => 'not_in_catalog']);
});

it('validates countries (max 5, ISO list) and keeps the cache key per country set, period, keyword and count', function () use ($q) {
    ($this->owner)();
    $this->postJson($this->base.'/refresh', ['countries' => ['EG', 'SA', 'AE', 'KW', 'QA', 'OM']])->assertStatus(422);
    $this->postJson($this->base.'/refresh', ['countries' => ['XX']])->assertStatus(422);
    $this->postJson($this->base.'/refresh', [])->assertStatus(422);
    expect($q(['EG', 'SA'])->key())->toBe($q(['EG', 'SA'])->key())->not->toBe($q(['EG'])->key())->not->toBe($q(['EG', 'SA'], 20)->key())->not->toBe($q(['EG', 'SA'], 10, 'watch')->key());
});

it('drops out-of-selection countries, trims to count and rejects malformed output without caching', function () use ($q, $item) {
    $svc = app(GlobalMarketTrendService::class);
    $out = $svc->parse(json_encode(['items' => [$item('A One', ['countries' => ['EG', 'FR']]), $item('B Two', ['countries' => ['FR']]), $item('C Three'), $item('D Four')]]), ['EG', 'SA'], 2);
    expect(array_column($out, 'name'))->toBe(['A One', 'C Three'])->and($out[0]['countries'])->toBe(['EG']);
    foreach (['nope', '{"items":"x"}', '[]', '{"items":[{"name":"X","countries":["FR"]}]}'] as $bad) {
        expect(fn () => $svc->parse($bad, ['EG'], 10))->toThrow(AiException::class);
    }
    Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => 'prose'], 'finish_reason' => 'stop']], 'usage' => ['prompt_tokens' => 1, 'completion_tokens' => 1]])]);
    expect(fn () => $svc->generate($q(['EG'])))->toThrow(AiException::class);
    expect(AiTrendSnapshot::count())->toBe(0);
    $query = $q(['EG']);
    Cache::put('ai-mkt:running:'.$this->tenant->id.'-'.$query->key(), true);
    (new RunGlobalMarketTrendsJob($this->tenant->id, ['EG'], 30, '', 10, $this->model->id))->handle($svc);
    expect(Cache::get('ai-mkt:error:'.$this->tenant->id.'-'.$query->key()))->not->toBeNull()->and(Cache::get('ai-mkt:running:'.$this->tenant->id.'-'.$query->key()))->toBeNull();
});

it('generates with countries in the prompt, serves cache without a job, throttles manual refresh and re-evaluates status after cache', function () use ($q, $item) {
    Http::fake(['*' => Http::response(($this->json)([$item('Lumo Ring', ['countries' => ['EG', 'SA']])]))]);
    $query = $q(['EG', 'SA']);
    $svc = app(GlobalMarketTrendService::class);
    $s = $svc->generate($query);
    Http::assertSent(fn ($r) => str_contains(json_encode($r->data()), 'SA (Saudi Arabia') && str_contains(json_encode($r->data()), 'SAR'));
    expect($s->kind)->toBe('mkt_global')->and($s->tenant_id)->toBe($this->tenant->id);
    Queue::fake();
    expect($svc->requestRefresh($query))->toBe('cached');
    Queue::assertNothingPushed();
    expect($svc->state($query)['items'][0]['match']['status'])->toBe('not_in_catalog');
    ($this->product)($this->tenant, 'mk-ring', 'Lumo Ring', 3);   // stock arrives after caching
    expect($svc->state($query)['items'][0]['match']['status'])->toBe('in_stock');
    $force = new MarketTrendQuery(['EG', 'SA'], 30, '', 10, $this->model->id, true);
    expect($svc->requestRefresh($force))->toBe('queued');
    Queue::assertPushed(RunGlobalMarketTrendsJob::class, 1);
    Cache::forget('ai-mkt:running:'.$this->tenant->id.'-'.$query->key());
    expect(fn () => $svc->requestRefresh($force))->toThrow(AiException::class);
});

it('isolates snapshots per tenant and supports dismiss and csv', function () use ($q, $item) {
    $query = $q(['EG']);
    AiTrendSnapshot::withoutGlobalScopes()->create(['tenant_id' => $this->other->id, 'kind' => 'mkt_global', 'country' => null, 'period' => $query->key(), 'items' => [['key' => 'abcdefabcdef', 'name' => 'Secret']], 'generated_at' => now()]);
    $svc = app(GlobalMarketTrendService::class);
    expect($svc->state($query)['items'])->toBe([])->and($svc->dismiss($query, 'abcdefabcdef'))->toBeFalse();
    $svc->parse(json_encode(['items' => [$item('=cmd Ring')]]), ['EG'], 10);
    $s = AiTrendSnapshot::create(['kind' => 'mkt_global', 'country' => null, 'period' => $query->key(), 'items' => $svc->parse(json_encode(['items' => [$item('=cmd Ring'), $item('Other Thing')]]), ['EG'], 10), 'generated_at' => now()]);
    $csv = $svc->csv($svc->state($query)['items']);
    expect($csv)->toContain("'=cmd Ring")->toContain('not_in_catalog');
    expect($svc->dismiss($query, $s->items[1]['key']))->toBeTrue();
    expect(array_column($svc->state($query)['items'], 'name'))->toBe(['=cmd Ring']);
});

it('serves the page and endpoints, and is gated by feature and permission', function () {
    ($this->owner)();
    $this->get($this->base)->assertOk()->assertSee(__('ai.market.title'));
    $this->getJson($this->base.'/data?countries[]=EG')->assertOk()->assertJsonPath('data.items', []);
    $this->get($this->base.'/export?countries[]=EG')->assertOk();
    AiTenantSetting::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->update(['features' => ['chat' => true]]);
    app(\App\Services\Ai\AiAccess::class)->forget();
    $this->get($this->base)->assertForbidden();
    $this->getJson($this->base.'/data?countries[]=EG')->assertForbidden();
});

it('refuses refresh without a web-capable model', function () {
    AiModel::query()->update(['capabilities' => ['tools' => true]]);
    app(\App\Services\Ai\AiAccess::class)->forget();
    ($this->owner)();
    $this->postJson($this->base.'/refresh', ['countries' => ['EG'], 'force' => 1])->assertStatus(422);
});
