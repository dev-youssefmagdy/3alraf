<?php

// Gift Finder as a standalone module (P2, MARKETER_PLAN.md): App\Services\GiftFinder\*, switch `gift_finder.enabled` (Settings → General, default ON),
// storefront + mobile API + admin (marketer.gift-finder.*, perms gift_finder.*). Ported from the removed Growth tests + new switch / admin coverage.

use App\Enums\StockMovementType as T;
use App\Models\Admin;
use App\Models\Branch;
use App\Models\Category;
use App\Models\GiftFinderSession;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Services\BarcodeService;
use App\Services\GiftFinder\GiftFinderService;
use App\Services\GiftFinder\GiftFinderSettings;
use App\Services\GiftFinder\GiftScorer;
use App\Services\GiftFinder\GiftTagSuggester;
use App\Services\Stock\StockLedger;
use App\Services\Storefront\AttributionService;
use Database\Seeders\FoundationSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(FoundationSeeder::class);
    Cache::flush();
    $this->owner = fn () => $this->actingAs(Admin::query()->where('email', 'owner@egy.test')->first(), 'admin');
    $this->cat = fn (string $n) => Category::create(['name' => ['en' => $n, 'ar' => $n], 'slug' => strtolower($n).uniqid(), 'is_active' => true, 'path' => '0', 'depth' => 0]);
    $this->product = function (string $slug, string $price = '100.00', string $cost = '50', int $stock = 20, array $cats = []) {
        $pr = Product::create(['type' => 'simple', 'name' => ['en' => 'Item '.$slug, 'ar' => 'صنف '.$slug], 'description' => ['en' => 'x'], 'slug' => $slug, 'sku' => strtoupper($slug), 'barcode' => app(BarcodeService::class)->generateUnique()]);
        listProduct($pr, ['price' => $price, 'cost' => $cost, 'is_active' => true]);
        if ($cats) {
            $pr->categories()->attach(array_map(fn ($c) => $c->id, $cats));
        }
        if ($stock > 0) {
            app(StockLedger::class)->record(Branch::first()->id, $pr->id, 0, T::PURCHASE_IN, $stock, $cost);
        }

        return $pr;
    };
    $this->off = fn () => app(GiftFinderSettings::class)->set(false);
});

it('is ON by default: storefront page, web + API search, prepared cart, nav link and the app feature flag', function () {
    $c = ($this->cat)('Skin');
    $p = ($this->product)('fit', '100', '40', 20, [$c]);
    expect(app(GiftFinderSettings::class)->enabled())->toBeTrue();   // no setting row yet → config default ON
    $this->get('/en')->assertOk()->assertSee('/en/gift-finder', false)->assertSee(__('gift_finder.cta'));
    $this->get('/en/gift-finder')->assertOk();
    $j = $this->postJson('/en/gift-finder', ['budget' => 150, 'category_id' => $c->id])->assertOk()->json('data');
    expect($j['results'][0]['product_id'])->toBe($p->id);
    $this->postJson('/en/gift-finder', ['budget' => -5])->assertStatus(422);
    $this->postJson('/en/gift-finder/'.$j['token'].'/cart', [])->assertOk()->assertJsonPath('data.added.0', $p->id);
    expect(GiftFinderSession::first()->cart_at)->not->toBeNull();
    $this->postJson('/api/v1/gift-finder', ['budget' => 150])->assertOk()->assertJsonPath('data.results.0.slug', 'fit');
    $this->getJson('/api/v1/store')->assertJsonPath('data.features.gift_finder', true);
    expect(app(GiftFinderService::class)->stats()['cart_rate'])->toBe(50.0);
});

it('switched OFF: pages 404, JSON 403 feature_disabled, links hidden, app flag false; ON again restores it', function () {
    $tok = app(GiftFinderService::class)->search(['budget' => 100])['token'];
    ($this->off)();
    $this->get('/en')->assertOk()->assertDontSee('/en/gift-finder', false);
    $this->get('/en/gift-finder')->assertNotFound();
    $this->postJson('/en/gift-finder', ['budget' => 150])->assertStatus(403)->assertJson(['error' => 'feature_disabled']);
    $this->postJson('/api/v1/gift-finder', ['budget' => 150])->assertStatus(403)->assertJson(['error' => 'feature_disabled']);
    $this->postJson('/api/v1/gift-finder/'.$tok.'/cart', [])->assertStatus(403);
    $this->getJson('/api/v1/store')->assertJsonPath('data.features.gift_finder', false);
    app(GiftFinderSettings::class)->set(true);
    $this->get('/en/gift-finder')->assertOk();
});

it('is toggled from Settings → General (and so is customer cancellation)', function () {
    ($this->owner)();
    $p = ['name' => 'Shop', 'country' => 'EG', 'currency_code' => 'EGP', 'currency_symbol' => 'ج.م', 'default_locale' => 'en', 'locales' => ['ar', 'en'], 'timezone' => 'Africa/Cairo'];
    $this->get('/admin/settings/general')->assertOk()->assertSee('gift_finder_enabled', false)->assertSee('customer_cancel_enabled', false);
    $this->putJson('/admin/settings/general', $p + ['gift_finder_enabled' => 0, 'customer_cancel_enabled' => 1])->assertOk();
    Setting::flush();
    expect(app(GiftFinderSettings::class)->enabled())->toBeFalse()->and(setting('customer_cancel.enabled'))->toBeTrue();
    $this->putJson('/admin/settings/general', $p + ['gift_finder_enabled' => 1])->assertOk();
    Setting::flush();
    expect(app(GiftFinderSettings::class)->enabled())->toBeTrue()->and(setting('customer_cancel.enabled'))->toBeFalse();
});

it('never exceeds the budget, excludes out-of-stock, margin only breaks ties, restrictions apply', function () {
    $c1 = ($this->cat)('Skin');
    $c2 = ($this->cat)('Hair');
    ($this->product)('fit', '100', '95', 20, [$c1]);          // best fit (category), terrible margin
    ($this->product)('other', '100', '10', 20, [$c2]);
    ($this->product)('over', '300', '10', 20, [$c1]);
    ($this->product)('near', '130', '10', 20, [$c1]);
    ($this->product)('nostock', '90', '10', 0, [$c1]);
    ($this->product)('forbidden', '90', '10', 20, [$c1]);
    $r = app(GiftFinderService::class)->search(['budget' => 120, 'category_id' => $c1->id, 'restrictions' => 'forbidden']);
    $names = collect($r['results'])->pluck('slug')->all();
    expect($names[0])->toBe('fit')->and($names)->not->toContain('over', 'nostock', 'forbidden')
        ->and(collect($r['results'])->max('price'))->toBeLessThanOrEqual(120.0)
        ->and(collect($r['near_budget'])->pluck('slug')->all())->toBe(['near'])->and($r['near_budget'][0]['over_budget'])->toBeTrue()
        ->and(collect($r['results'])->every(fn ($x) => $x['explanation'] !== ''))->toBeTrue();
    expect(fn () => app(GiftFinderService::class)->search(['budget' => 0]))->toThrow(ValidationException::class);
});

it('tuning comes from config, restriction words are normalised, internal ranking fields never leak', function () {
    $c = ($this->cat)('Skin');
    ($this->product)('fit', '100', '40', 20, [$c]);
    ($this->product)('edge', '150', '40', 20, [$c]);
    expect(app(GiftScorer::class)->restrictionWords(" Foo ,x\nبار، , baz "))->toBe(['foo', 'بار', 'baz']);
    $r = app(GiftFinderService::class)->search(['budget' => 100]);
    expect($r['results'][0])->not->toHaveKeys(['margin', 'bucket', 'names'])->and($r['near_budget'])->toBe([]);
    config(['gift_finder.near_budget_factor' => 1.6, 'gift_finder.max_results' => 1]);
    $r = app(GiftFinderService::class)->search(['budget' => 100]);
    expect($r['results'])->toHaveCount(1)->and(collect($r['near_budget'])->pluck('slug')->all())->toBe(['edge']);
});

it('tags: untagged products stay visible until tagging starts; then recipient / occasion tags drive the list', function () {
    $c = ($this->cat)('Skin');
    $womens = ($this->product)('womens', '100', '40', 20, [$c]);
    $mens = ($this->product)('mens', '100', '40', 20, [$c]);
    ($this->product)('car', '100', '40', 20, [$c]);
    $svc = app(GiftFinderService::class);
    expect(collect($svc->search(['budget' => 150, 'recipient' => 'mother'])['results'])->pluck('slug')->sort()->values()->all())->toBe(['car', 'mens', 'womens']);
    Product::whereKey($womens->id)->update(['gift_suitable' => true, 'gift_recipients' => ['mother', 'sister'], 'gift_occasions' => ['mothers_day']]);
    Product::whereKey($mens->id)->update(['gift_suitable' => true, 'gift_recipients' => ['partner']]);
    $r = $svc->search(['budget' => 150, 'recipient' => 'mother', 'occasion' => 'mothers_day']);
    expect(collect($r['results'])->pluck('slug')->all())->toBe(['womens'])
        ->and($r['results'][0]['recipient_match'])->toBeTrue()->and($r['results'][0]['occasion_match'])->toBeTrue()
        ->and($r['results'][0]['explanation'])->toContain('Mother')->and($r['results'][0]['explanation'])->toContain("Mother's Day");
});

it('the tag suggester skips non-gifts and test products and never overwrites human tags', function () {
    $big = ($this->cat)('Furniture');
    $beauty = ($this->cat)('Beauty');
    $sofa = ($this->product)('sofa', '5000', '1', 5, [$big]);
    $perfume = ($this->product)('perfume', '300', '1', 5, [$beauty]);
    $kept = ($this->product)('kept', '300', '1', 5, [$beauty]);
    Product::whereKey($kept->id)->update(['gift_recipients' => ['child']]);
    expect(app(GiftTagSuggester::class)->applyToUntagged())->toBeGreaterThanOrEqual(1);
    expect(Product::find($sofa->id)->gift_suitable)->toBeFalse()->and(Product::find($perfume->id)->gift_recipients)->toContain('mother')
        ->and(Product::find($kept->id)->gift_recipients)->toBe(['child']);
});

it('carries the gift session into the order attribution and reports gift orders', function () {
    $p = ($this->product)('giftx', '80.00');
    $res = app(GiftFinderService::class)->search(['budget' => 200]);
    $this->postJson('/en/gift-finder/'.$res['token'].'/cart', ['product_ids' => [$p->id]])->assertOk();
    $sid = GiftFinderSession::where('token', $res['token'])->value('id');
    expect(session('gift_session_id'))->toBe($sid)
        ->and(AttributionService::forOrder(null)['attribution'])->toBe(['gift_session' => $sid]);
    foreach (['delivered' => 120, 'cancelled' => 100] as $status => $total) {
        (new Order)->forceFill(['number' => 'G'.uniqid(), 'public_token' => uniqid('t', true), 'customer_name' => 'X', 'customer_phone' => '01000000100', 'address' => 'a', 'status' => $status, 'payment_status' => 'paid',
            'subtotal' => $total, 'discount' => 0, 'shipping' => 0, 'shipping_net' => 0, 'shipping_cost' => 0, 'tax' => 0, 'payment_fee' => 0, 'payment_fee_charged' => 0, 'cod_fee' => 0, 'total' => $total,
            'cogs' => 40, 'affiliate_commission' => 0, 'packaging_cost' => 0, 'currency' => 'EGP', 'locale' => 'en', 'placed_at' => now()->subDay(), 'attribution' => ['gift_session' => $sid]])->save();
    }
    $s = app(GiftFinderService::class)->stats();
    expect($s['orders'])->toBe(1)->and($s['orders_aov'])->toBe(120.0)->and($s['order_rate'])->toBe(100.0);
});

it('admin: stats + tags pages work without any Growth switch, are permission-gated, and save tags', function () {
    $a = ($this->product)('a', '100', '40', 20);
    ($this->owner)()->get('/marketer/gift-finder')->assertOk();
    $this->get('/marketer/gift-finder/tags')->assertOk()->assertSee($a->name);
    $this->post('/marketer/gift-finder/tags', ['tags' => [$a->id => ['suitable' => 1, 'recipients' => ['mother'], 'occasions' => ['ramadan']]]])->assertRedirect('/marketer/gift-finder/tags');
    $tp = Product::find($a->id);
    expect($tp->gift_suitable)->toBeTrue()->and($tp->gift_recipients)->toBe(['mother'])->and($tp->gift_occasions)->toBe(['ramadan']);
    $this->post('/marketer/gift-finder/tags', ['tags' => [$a->id => ['suitable' => 1, 'recipients' => ['bogus']]]])->assertSessionHasErrors();
    ($this->off)();
    $this->get('/marketer/gift-finder')->assertOk()->assertSee(__('gift_finder.disabled_hint'));   // admin keeps working while the storefront is off

    $viewer = Admin::create(['name' => 'V', 'email' => 'v@egy.test', 'password' => 'password', 'is_active' => true]);
    $this->actingAs($viewer, 'admin')->get('/marketer/gift-finder')->assertForbidden();
    $viewer->givePermissionTo('gift_finder.view');
    $this->actingAs($viewer->fresh(), 'admin')->get('/marketer/gift-finder')->assertOk();
    $this->actingAs($viewer->fresh(), 'admin')->post('/marketer/gift-finder/tags', ['tags' => []])->assertForbidden();
});

it('the removed Growth admin and storefront routes are gone', function () {
    ($this->owner)();
    foreach (['/admin/growth/brief', '/admin/growth/gift-finder', '/admin/growth/clients', '/admin/growth/settings', '/en/launch/abcd1234', '/l/abcd1234'] as $url) {
        $this->get($url)->assertNotFound();
    }
    expect(collect(Route::getRoutes()->getRoutesByName())->keys()->filter(fn ($n) => str_contains($n, 'growth'))->all())->toBe([]);
});
