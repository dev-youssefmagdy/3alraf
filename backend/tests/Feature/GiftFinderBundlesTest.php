<?php

// P3 (MARKETER_PLAN.md): unified ranked list of products AND bundles, filters / relaxation, share link, bundle cart, API parity, nav visibility.

use App\Enums\StockMovementType as T;
use App\Models\Admin;
use App\Models\Branch;
use App\Models\Bundle;
use App\Models\BundleItem;
use App\Models\Category;
use App\Models\GiftFinderSession;
use App\Models\Product;
use App\Services\BarcodeService;
use App\Services\GiftFinder\GiftFinderService;
use App\Services\GiftFinder\GiftFinderSettings;
use App\Services\Stock\StockLedger;
use Database\Seeders\FoundationSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->seed(FoundationSeeder::class);
    Cache::flush();
    app()->setLocale('en');
    $this->cat = fn (string $n) => Category::create(['name' => ['en' => $n, 'ar' => $n], 'slug' => strtolower($n).uniqid(), 'is_active' => true, 'path' => (string) mt_rand(900, 99999), 'depth' => 0]);
    $this->product = function (string $slug, string $price = '100.00', string $cost = '50', int $stock = 20, array $cats = [], array $tags = []) {
        $pr = Product::create(['type' => 'simple', 'name' => ['en' => 'Item '.$slug, 'ar' => 'صنف '.$slug], 'description' => ['en' => 'x'], 'slug' => $slug, 'sku' => strtoupper($slug), 'barcode' => app(BarcodeService::class)->generateUnique()]);
        listProduct($pr, ['price' => $price, 'cost' => $cost, 'is_active' => true]);
        if ($cats) {
            $pr->categories()->attach(array_map(fn ($c) => $c->id, $cats));
        }
        if ($stock > 0) {
            app(StockLedger::class)->record(Branch::first()->id, $pr->id, 0, T::PURCHASE_IN, $stock, $cost);
        }
        if ($tags) {
            Product::whereKey($pr->id)->update($tags);
        }

        return $pr;
    };
    $this->bundle = function (string $slug, string $price, array $parts) {
        $b = Bundle::create(['name' => ['en' => 'Pack '.$slug, 'ar' => 'باقة '.$slug], 'slug' => $slug, 'price' => $price, 'is_active' => true]);
        foreach ($parts as [$p, $q]) {
            BundleItem::create(['bundle_id' => $b->id, 'product_id' => $p->id, 'product_variant_id' => 0, 'quantity' => $q]);
        }

        return $b;
    };
});

it('ranks products and bundles in one list with type, scores bundles by their items tags, never exceeds the budget', function () {
    $tag = ['gift_suitable' => true, 'gift_recipients' => ['mother'], 'gift_occasions' => ['mothers_day']];
    $a = ($this->product)('rose', '60', '20', 20, [], $tag);
    $b = ($this->product)('mug', '50', '20', 20, [], $tag);
    $plain = ($this->product)('pen', '90', '20', 20, [], ['gift_suitable' => true]);   // no recipient / occasion tag
    ($this->product)('pricey', '400', '20', 20, [], $tag);
    $pack = ($this->bundle)('mom-pack', '95', [[$a, 1], [$b, 1]]);                       // regular 110, saves 15
    $over = ($this->bundle)('big-pack', '300', [[$a, 2], [$b, 3]]);

    $r = app(GiftFinderService::class)->search(['budget' => 100, 'recipient' => 'mother', 'occasion' => 'mothers_day']);
    $items = collect($r['items']);
    expect($items->pluck('type')->unique()->sort()->values()->all())->toBe(['bundle', 'product'])
        ->and($items->every(fn ($i) => (float) $i['price'] <= 100))->toBeTrue()
        ->and($items->pluck('key')->all())->not->toContain('bundle:'.$over->id)->toContain('product:'.$plain->id); // untagged pen = "anyone, any occasion"
    $bundle = $items->firstWhere('type', 'bundle');
    expect($bundle['bundle_id'])->toBe($pack->id)->and($bundle['recipient_match'])->toBeTrue()->and($bundle['occasion_match'])->toBeTrue()
        ->and($bundle['savings'])->toBe('15.00')->and($bundle['regular_total'])->toBe('110.00')->and($bundle['components'])->toHaveCount(2)
        ->and($bundle['explanation'])->toContain('Pack mom-pack')->toContain('2 items')->toContain('save 15')
        ->and($bundle)->not->toHaveKeys(['margin', 'bucket', 'bundle', 'component_names']);
    // the tagged bundle (fit 25+20+15·0.95+15+savings) outranks the untagged pen (15+0+0+13.5)
    $keys = $items->pluck('key')->all();
    expect($keys[0])->toBe('bundle:'.$pack->id)->and(array_search('bundle:'.$pack->id, $keys))->toBeLessThan(array_search('product:'.$plain->id, $keys));
    // legacy arrays kept for older app builds
    expect(collect($r['results'])->every(fn ($x) => $x['type'] === 'product' && is_float($x['price'])))->toBeTrue()
        ->and($r['bundles'][0]['id'])->toBe($pack->id)
        ->and(collect($r['near_items'])->pluck('key')->all())->not->toContain('bundle:'.$over->id); // 300 > 125
    expect(GiftFinderSession::first()->bundle_ids)->toBe([$pack->id]);
});

it('a bundle with a sold-out item, a non-purchasable item or an item tagged for other people is never offered', function () {
    $ok = ($this->product)('ok', '40');
    $empty = ($this->product)('empty', '40', '10', 0);
    $mens = ($this->product)('mens', '40', '10', 20, [], ['gift_recipients' => ['partner']]);
    $off = ($this->product)('off', '40');
    Product::whereKey($off->id)->update(['is_active' => false]);
    ($this->bundle)('soldout', '70', [[$ok, 1], [$empty, 1]]);
    ($this->bundle)('inactive', '70', [[$ok, 1], [$off, 1]]);
    ($this->bundle)('formen', '70', [[$ok, 1], [$mens, 1]]);
    $good = ($this->bundle)('good', '70', [[$ok, 2]]);
    $r = app(GiftFinderService::class)->search(['budget' => 100, 'recipient' => 'mother', 'type' => 'bundles']);
    expect(collect($r['items'])->pluck('key')->all())->toBe(['bundle:'.$good->id])->and($r['results'])->toBe([]);
});

it('budget cap is never exceeded for any type / sort, sorts by price, type filter works', function () {
    foreach ([30, 70, 99, 101, 140] as $i => $price) {
        ($this->product)('p'.$i, (string) $price);
    }
    $x = ($this->product)('x', '45');
    ($this->bundle)('b1', '80', [[$x, 1]]);
    ($this->bundle)('b2', '120', [[$x, 2]]);
    $svc = app(GiftFinderService::class);
    foreach (['all', 'products', 'bundles'] as $type) {
        foreach (GiftFinderService::SORTS as $sort) {
            $r = $svc->search(['budget' => 100, 'type' => $type, 'sort' => $sort]);
            expect(collect($r['items'])->every(fn ($i) => (float) $i['price'] <= 100))->toBeTrue()
                ->and(collect($r['near_items'])->every(fn ($i) => (float) $i['price'] > 100 && $i['over_budget']))->toBeTrue();
            if ($type !== 'all') {
                expect(collect($r['items'])->pluck('type')->unique()->all())->toBe([$type === 'products' ? 'product' : 'bundle']);
            }
        }
    }
    $asc = collect($svc->search(['budget' => 100, 'sort' => 'price_asc'])['items'])->pluck('price')->map(fn ($p) => (float) $p)->all();
    $sorted = $asc;
    sort($sorted);
    expect($asc)->toBe($sorted);
});

it('relaxes the category, then the occasion, and says so', function () {
    $skin = ($this->cat)('Skin');
    $hair = ($this->cat)('Hair');
    ($this->product)('wed', '80', '10', 20, [$hair], ['gift_suitable' => true, 'gift_occasions' => ['wedding']]);
    $svc = app(GiftFinderService::class);

    $r = $svc->search(['budget' => 100, 'category_id' => $hair->id, 'occasion' => 'wedding']);
    expect($r['relaxed'])->toBe([])->and($r['items'])->toHaveCount(1);

    $r = $svc->search(['budget' => 100, 'category_id' => $skin->id, 'occasion' => 'wedding']);
    expect($r['relaxed'])->toBe(['category'])->and($r['items'][0]['slug'])->toBe('wed')->and($r['relaxed_notice'])->toBe(__('gift_finder.relaxed_category'));

    $r = $svc->search(['budget' => 100, 'category_id' => $skin->id, 'occasion' => 'birthday']);
    expect($r['relaxed'])->toBe(['category', 'occasion'])->and($r['items'])->toHaveCount(1)->and($r['relaxed_notice'])->toBe(__('gift_finder.relaxed_category_occasion'));

    $r = $svc->search(['budget' => 100, 'occasion' => 'birthday']);
    expect($r['relaxed'])->toBe(['occasion']);
    $r = $svc->search(['budget' => 5]);
    expect($r['relaxed'])->toBe([])->and($r['items'])->toBe([]);
});

it('adds a suggested bundle to the cart through the bundle cart path, records it and refuses bundles it did not suggest', function () {
    $a = ($this->product)('a', '40');
    $pack = ($this->bundle)('pk', '70', [[$a, 2]]);
    $other = ($this->bundle)('other', '500', [[$a, 1]]);
    $r = app(GiftFinderService::class)->search(['budget' => 100]);
    $this->postJson('/en/gift-finder/'.$r['token'].'/cart', ['bundle_ids' => [$pack->id]])->assertOk()
        ->assertJsonPath('data.bundles_added', [$pack->id])->assertJsonPath('data.added', []);   // products not added when only bundles are chosen
    $this->postJson('/en/gift-finder/'.$r['token'].'/cart', ['bundle_ids' => [$other->id]])->assertStatus(422)->assertJsonPath('data.bundles_skipped', [$other->id]);
    expect(DB::table('cart_items')->where('bundle_id', $pack->id)->count())->toBe(1);
    $s = GiftFinderSession::first();
    expect($s->cart_bundle_ids)->toBe([$pack->id])->and($s->cart_at)->not->toBeNull()
        ->and(app(GiftFinderService::class)->stats())->toMatchArray(['bundle_sessions' => 1, 'bundle_carts' => 1]);
    // API v1 too
    $this->postJson('/api/v1/gift-finder/'.$r['token'].'/cart', ['bundle_ids' => [$pack->id], 'product_ids' => [$a->id]])->assertOk()
        ->assertJsonPath('data.bundles_added', [$pack->id])->assertJsonPath('data.added', [$a->id]);
});

it('API v1 returns the unified list with bundle items and keeps the legacy arrays; GET by token re-renders the stored search', function () {
    $a = ($this->product)('a', '40');
    $pack = ($this->bundle)('pk', '70', [[$a, 2]]);
    $j = $this->withHeader('Accept-Language', 'ar')->postJson('/api/v1/gift-finder', ['budget' => 100, 'type' => 'all', 'sort' => 'best'])->assertOk()
        ->assertJsonStructure(['data' => ['token', 'share_url', 'budget', 'filters' => ['type', 'sort'], 'relaxed', 'answers', 'items' => [['type', 'key', 'price', 'explanation']],
            'near_items', 'results' => [['product_id', 'name', 'slug', 'price', 'simple', 'explanation']], 'bundles' => [['id', 'slug', 'name', 'price', 'regular_total', 'savings', 'components']], 'near_budget']])
        ->json('data');
    $bundle = collect($j['items'])->firstWhere('type', 'bundle');
    expect($bundle['components'][0])->toHaveKeys(['product_id', 'variant_id', 'name', 'quantity', 'image'])->and($bundle['explanation'])->toContain('تجمع')
        ->and($j['share_url'])->toContain('/ar/gift-finder/r/'.$j['token']);
    $this->postJson('/api/v1/gift-finder', ['budget' => 100, 'type' => 'nope'])->assertStatus(422);

    $sessions = GiftFinderSession::count();
    $again = $this->getJson('/api/v1/gift-finder/'.$j['token'])->assertOk()->json('data');
    expect(collect($again['items'])->pluck('key')->all())->toBe(collect($j['items'])->pluck('key')->all())->and($again['shared'])->toBeTrue()
        ->and(GiftFinderSession::count())->toBe($sessions);
    $this->getJson('/api/v1/gift-finder/'.str_repeat('z', 32))->assertNotFound();
});

it('share page re-renders the stored results (prefilled, noindex), 404 for an unknown token', function () {
    ($this->product)('a', '40');
    $tok = app(GiftFinderService::class)->search(['budget' => 100, 'occasion' => 'birthday'])['token'];
    $this->get('/en/gift-finder/r/'.$tok)->assertOk()->assertSee(__('gift_finder.shared_title'))->assertSee('noindex', false)
        ->assertSee('value="birthday" selected', false)->assertSee('product:', false);
    $this->get('/ar/gift-finder/r/'.$tok)->assertOk()->assertSee('dir="rtl"', false);
    $this->get('/en/gift-finder/r/'.str_repeat('q', 32))->assertNotFound();
});

it('setting off: share page 404, token API 403', function () {
    ($this->product)('a', '40');
    $tok = app(GiftFinderService::class)->search(['budget' => 100])['token'];
    app(GiftFinderSettings::class)->set(false);
    $this->get('/en/gift-finder/r/'.$tok)->assertNotFound();
    $this->getJson('/api/v1/gift-finder/'.$tok)->assertStatus(403)->assertJson(['error' => 'feature_disabled']);
    $this->get('/ar')->assertOk()->assertDontSee('data-nav-gift-finder', false)->assertDontSee('data-bottom-gift-finder', false)->assertDontSee('data-home-gift-finder', false);
});

it('shows the gift finder link in the header top nav, the drawer and the home CTA in ar and en', function () {
    foreach (['ar', 'en'] as $l) {
        app()->setLocale($l);
        $html = $this->get('/'.$l)->assertOk()->getContent();
        expect(substr_count($html, 'href="'.url($l.'/gift-finder').'"'))->toBeGreaterThanOrEqual(3)
            ->and($html)->toContain('data-nav-gift-finder')->toContain('data-bottom-gift-finder')->toContain('data-home-gift-finder')->toContain(__('gift_finder.nav'));
    }
    $this->get('/en/gift-finder')->assertOk()->assertSee('aria-current="page"', false)->assertSee('aria-live="polite"', false)
        ->assertSee('data-preset', false)->assertSee('name="type"', false)->assertSee('name="sort"', false);
});

it('search runs a fixed number of queries whatever the catalog size (no N+1)', function () {
    $count = function () {
        DB::flushQueryLog();
        DB::enableQueryLog();
        app(GiftFinderService::class)->search(['budget' => 1000]);
        $n = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $n;
    };
    $make = function (int $from, int $to) {
        for ($i = $from; $i < $to; $i++) {
            $a = ($this->product)('qa'.$i, '40');
            $b = ($this->product)('qb'.$i, '50');
            ($this->bundle)('qk'.$i, '80', [[$a, 1], [$b, 1]]);
        }
    };
    $make(0, 2);
    $small = $count();
    $make(2, 12);
    $big = $count();
    expect($big)->toBe($small)->and($small)->toBeLessThan(40);
});

it('admin page shows bundle stats and the bundles whose items lack tags', function () {
    $a = ($this->product)('a', '40');
    ($this->bundle)('untagged-pack', '70', [[$a, 1]]);
    $this->actingAs(Admin::query()->where('email', 'owner@egy.test')->first(), 'admin')
        ->get('/marketer/gift-finder')->assertOk()->assertSee(__('gift_finder.bundle_carts', [], 'ar'))->assertSee(__('gift_finder.untagged_bundles', [], 'ar'))->assertSee('untagged-pack');
});
