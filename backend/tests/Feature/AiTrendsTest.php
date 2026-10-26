<?php

use App\Enums\AiDriverType;
use App\Exceptions\AiException;
use App\Jobs\RunMarketTrendsJob;
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
    $this->base = 'http://egy.3alraf.test/admin/ai/trends';
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

it('computes velocity, growth, margin and days of stock from the confirmed set only', function () {
    $p = ($this->product)($this->tenant, 'hot', 'Hot Item', 12);
    ($this->sale)($this->tenant, $p, 10, 5);                              // current window
    ($this->sale)($this->tenant, $p, 5, 10, 'delivered', '50.00', '20', 1); // net 4, current window
    ($this->sale)($this->tenant, $p, 7, 40);                              // previous 30d window
    ($this->sale)($this->tenant, $p, 99, 3, 'pending');                   // never counts
    ($this->sale)($this->tenant, $p, 99, 3, 'cancelled');
    $o = Order::where('tenant_id', $this->tenant->id)->latest('id')->first();
    $r = collect(app(TrendService::class)->own(30))->firstWhere('product_id', $p->id);
    expect($r['qty'])->toBe(14)->and($r['prev_qty'])->toBe(7)->and($r['growth_pct'])->toBe(100.0)->and($r['velocity'])->toBe(0.47)
        ->and($r['revenue'])->toBe('700.00')->and($r['cost'])->toBe('280.00')->and($r['margin_pct'])->toBe(60.0)
        ->and($r['available'])->toBe(12)->and($r['days_of_stock'])->toBe(25.7)->and($r['restock'])->toBeFalse();
    // 7 day window: only the first sale; 90 day window picks up the old one as current
    expect(collect(app(TrendService::class)->own(7))->firstWhere('product_id', $p->id)['qty'])->toBe(10)
        ->and(collect(app(TrendService::class)->own(90))->firstWhere('product_id', $p->id)['qty'])->toBe(21);
});

it('flags low stock for restock, marks brand-new sellers and honours the category filter and sort', function () {
    $a = ($this->product)($this->tenant, 'low', 'Low Item', 2);
    $b = ($this->product)($this->tenant, 'fresh', 'Fresh Item', 100);
    ($this->sale)($this->tenant, $a, 30, 2);
    ($this->sale)($this->tenant, $b, 3, 2);
    $rows = app(TrendService::class)->own(30);
    $low = collect($rows)->firstWhere('product_id', $a->id);
    expect($low['restock'])->toBeTrue()->and($low['days_of_stock'])->toBe(2.0)->and($low['suggested_qty'])->toBe(28)
        ->and(collect($rows)->firstWhere('product_id', $b->id)['is_new'])->toBeTrue()->and($rows[0]['product_id'])->toBe($a->id)
        ->and(app(TrendService::class)->own(30, null, 'days_of_stock')[0]['product_id'])->toBe($a->id);
    $cat = \App\Models\Category::create(['name' => ['en' => 'Cat'], 'slug' => 'cat-tr', 'path' => '0', 'depth' => 0, 'is_active' => true]);
    $b->categories()->attach($cat->id);
    expect(array_column(app(TrendService::class)->own(30, $cat->id), 'product_id'))->toBe([$b->id]);
});

it('excludes in-stock products (name / sku / barcode fuzzy) but keeps zero-stock ones flagged restock', function () {
    $in = ($this->product)($this->tenant, 'in-stock', 'Magnetic Phone Holder', 5);
    ($this->product)($this->tenant, 'barc', 'Totally Different', 5);
    $zero = ($this->product)($this->tenant, 'zero', 'Wireless Earbuds Pro', 0);
    $items = app(TrendService::class)->parse(json_encode(['items' => [
        ['name' => 'magnetic  phone-holder', 'why' => 'a'], ['name' => 'Car mount', 'sku' => 'in-stock', 'why' => 'b'],
        ['name' => 'Some barcode thing', 'barcode' => Product::where('slug', 'barc')->value('barcode'), 'why' => 'c'],
        ['name' => 'Wireless Earbuds Pro 2', 'why' => 'zero'], ['name' => 'Smart Ring', 'why' => 'new', 'confidence' => 1.7, 'sources' => ['https://a.test/x', 'javascript:alert(1)'], 'price_min' => 10, 'price_max' => '20'],
    ]]));
    $out = app(TrendService::class)->filterAgainstCatalog($items);
    expect(array_column($out, 'name'))->toBe(['Wireless Earbuds Pro 2', 'Smart Ring']);
    expect($out[0]['restock'])->toBeTrue()->and($out[0]['product_id'])->toBe($zero->id)->and($out[0]['product_name'])->toBe('Wireless Earbuds Pro');
    expect($out[1]['restock'])->toBeFalse()->and($out[1]['product_id'])->toBeNull()->and($out[1]['confidence'])->toBe(1.0)->and($out[1]['sources'])->toBe(['https://a.test/x'])->and($out[1]['price_max'])->toBe(20.0);
    expect($in->id)->not->toBe($zero->id);
});

it('puts the tenant country in the prompt, validates JSON and caches the snapshot', function () {
    Http::fake(['*' => Http::response(($this->json)([['name' => 'Smart Ring', 'why' => 'viral', 'confidence' => 0.8, 'sources' => ['https://x.test']]]))]);
    $s = app(TrendService::class)->generateMarket();
    Http::assertSent(fn ($req) => str_contains(json_encode($req->data()), 'country '.$this->tenant->country) && str_contains(json_encode($req->data()), $this->tenant->currency_code));
    expect($s->kind)->toBe('market')->and($s->country)->toBe($this->tenant->country)->and($s->items[0]['name'])->toBe('Smart Ring')->and($s->tenant_id)->toBe($this->tenant->id);
    $m = app(TrendService::class)->market();
    expect($m['stale'])->toBeFalse();
    $s->forceFill(['generated_at' => now()->subHours(25)])->save();
    expect(app(TrendService::class)->market()['stale'])->toBeTrue();
});

it('serves the cache without a job, queues a refresh, and throttles manual refreshes', function () {
    Queue::fake();
    $t = app(TrendService::class);
    AiTrendSnapshot::create(['kind' => 'market', 'country' => $this->tenant->country, 'period' => 'all', 'items' => [], 'generated_at' => now()]);
    expect($t->requestRefresh(false))->toBe('cached');
    Queue::assertNothingPushed();
    expect($t->requestRefresh(true))->toBe('queued');
    Queue::assertPushed(RunMarketTrendsJob::class, 1);
    Cache::forget('ai-trends:running:'.$this->tenant->id);
    expect(fn () => $t->requestRefresh(true))->toThrow(AiException::class);
    Queue::assertPushed(RunMarketTrendsJob::class, 1);
});

it('handles malformed model output without caching anything and reports it through the job', function () {
    Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => 'Sorry, here is some prose, not JSON'], 'finish_reason' => 'stop']], 'usage' => ['prompt_tokens' => 1, 'completion_tokens' => 1]])]);
    expect(fn () => app(TrendService::class)->generateMarket())->toThrow(AiException::class);
    expect(AiTrendSnapshot::count())->toBe(0);
    foreach (['{"items":"nope"}', '{"items":[{"why":"no name"}]}', '[]', ''] as $bad) {
        expect(fn () => app(TrendService::class)->parse($bad))->toThrow(AiException::class);
    }
    expect(app(TrendService::class)->parse("```json\n{\"items\":[{\"name\":\"<b>Ok</b>\"}]}\n```")[0]['name'])->toBe('Ok');
    Cache::put('ai-trends:running:'.$this->tenant->id, true);
    (new RunMarketTrendsJob($this->tenant->id))->handle(app(TrendService::class));
    expect(Cache::get('ai-trends:error:'.$this->tenant->id))->not->toBeNull()->and(Cache::get('ai-trends:running:'.$this->tenant->id))->toBeNull();
});

it('refuses market research when no web-capable model exists', function () {
    $this->model->update(['capabilities' => ['tools' => true]]);
    expect(app(TrendService::class)->webModel())->toBeNull();
    $this->actingAs(TenantAdmin::withoutGlobalScopes()->where('email', 'owner@egy.test')->first(), 'tenant');
    $this->postJson($this->base.'/market/refresh', ['force' => 1])->assertStatus(422);
});

it('isolates tenants: sales, snapshots and dismissals never cross', function () {
    $mine = ($this->product)($this->tenant, 'mine', 'Mine', 5);
    $theirs = ($this->product)($this->other, 'theirs', 'Theirs', 5);
    ($this->sale)($this->tenant, $mine, 3, 2);
    ($this->sale)($this->other, $theirs, 50, 2);
    expect(array_column(app(TrendService::class)->own(30), 'product_id'))->toBe([$mine->id]);
    AiTrendSnapshot::withoutGlobalScopes()->create(['tenant_id' => $this->other->id, 'kind' => 'market', 'country' => $this->tenant->country, 'period' => 'all', 'items' => [['name' => 'Secret', 'key' => 'abcdefabcdef', 'dismissed' => false]], 'generated_at' => now()]);
    expect(app(TrendService::class)->market()['snapshot'])->toBeNull()->and(app(TrendService::class)->dismiss('abcdefabcdef'))->toBeFalse();
    ($this->owner)();
    $this->postJson($this->base.'/market/dismiss', ['key' => 'abcdefabcdef'])->assertNotFound();
    expect(AiTrendSnapshot::withoutGlobalScopes()->where('tenant_id', $this->other->id)->first()->items[0]['dismissed'])->toBeFalse();
});

it('serves the page, own json (with narrative), market json, csv export and dismiss', function () {
    $p = ($this->product)($this->tenant, 'hotx', '=HYPERLINK("x")', 3);
    ($this->sale)($this->tenant, $p, 9, 2);
    ($this->owner)();
    $this->get($this->base)->assertOk()->assertSee(__('ai.trends.tab_market'));
    Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => '- Restock hot item'], 'finish_reason' => 'stop']], 'usage' => ['prompt_tokens' => 5, 'completion_tokens' => 5]])]);
    $j = $this->getJson($this->base.'/own?period=30&narrative=1')->assertOk()->json('data');
    expect($j['rows'][0]['product_id'])->toBe($p->id)->and($j['narrative'])->toBe('- Restock hot item');
    $this->getJson($this->base.'/own?period=5')->assertStatus(422);
    $csv = $this->get($this->base.'/export?tab=own')->assertOk()->getContent();
    expect($csv)->toContain("'=HYPERLINK");
    $s = AiTrendSnapshot::create(['kind' => 'market', 'country' => $this->tenant->country, 'period' => 'all', 'items' => app(TrendService::class)->filterAgainstCatalog(app(TrendService::class)->parse(json_encode(['items' => [['name' => 'Smart Ring']]]))), 'generated_at' => now()]);
    $key = $s->items[0]['key'];
    expect($this->getJson($this->base.'/market')->assertOk()->json('data.items'))->toHaveCount(1);
    $this->postJson($this->base.'/market/dismiss', ['key' => $key])->assertOk();
    expect($this->getJson($this->base.'/market')->json('data.items'))->toHaveCount(0);
});

it('creates only a DRAFT purchase order for own catalog products, never another tenants', function () {
    $mine = ($this->product)($this->tenant, 'po1', 'PO Item', 0, '15.00');
    $theirs = ($this->product)($this->other, 'po2', 'Their Item', 0);
    $sup = Supplier::create(['name' => 'Sup', 'is_active' => true]);
    ($this->owner)();
    $br = ($this->branchId)($this->tenant);
    $res = $this->postJson($this->base.'/purchase-draft', ['supplier_id' => $sup->id, 'branch_id' => $br, 'items' => [['product_id' => $mine->id, 'quantity' => 6]]])->assertOk();
    $po = \App\Models\PurchaseOrder::latest('id')->first();
    expect($po->status->value)->toBe('draft')->and($po->items()->first()->quantity)->toBe(6)->and((string) $po->items()->first()->unit_cost)->toBe('15.00')->and($po->received_at)->toBeNull();
    expect(StockLevel::where('product_id', $mine->id)->sum('on_hand'))->toBe(0);
    $this->postJson($this->base.'/purchase-draft', ['supplier_id' => $sup->id, 'branch_id' => $br, 'items' => [['product_id' => $theirs->id, 'quantity' => 1]]])->assertStatus(422);
});

it('is gated by the trends feature and permission', function () {
    AiTenantSetting::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->update(['features' => ['chat' => true]]);
    app(\App\Services\Ai\AiAccess::class)->forget();
    ($this->owner)();
    $this->get($this->base)->assertForbidden();
    $this->getJson($this->base.'/market')->assertForbidden();
});
