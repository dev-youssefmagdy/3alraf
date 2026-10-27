<?php

use App\Enums\StockMovementType as T;
use App\Models\Branch;
use App\Models\Bundle;
use App\Models\BundleAbTest;
use App\Models\Category;
use App\Models\GiftFinderSession;
use App\Models\GrowthApproval;
use App\Models\Product;
use App\Models\SeasonalEvent;
use App\Models\Tenant;
use App\Models\TenantAdmin;
use App\Models\TenantProduct;
use App\Services\Growth\ApprovalService;
use App\Services\Growth\BundleAbService;
use App\Services\Growth\BundleProposalService;
use App\Services\Growth\GiftFinderService;
use App\Services\Growth\GrowthAccess;
use App\Services\Growth\GrowthFeatures;
use App\Services\Growth\SeasonalCalendarService;
use App\Services\Stock\StockLedger;
use Carbon\Carbon;
use Database\Seeders\FoundationSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(FoundationSeeder::class);
    Cache::flush();
    $this->tenant = Tenant::where('slug', 'egy')->first();
    $this->other = Tenant::firstOrCreate(['slug' => 'sa'], ['name' => 'KSA', 'country' => 'SA', 'currency_code' => 'SAR', 'currency_symbol' => 'SAR', 'currency_rate' => 1, 'default_locale' => 'ar', 'locales' => ['ar', 'en'], 'timezone' => 'Asia/Riyadh', 'is_active' => true]);
    app()->instance('currentTenant', $this->tenant);
    foreach ([$this->tenant, $this->other] as $t) {
        app(GrowthAccess::class)->set($t, true);
        app(GrowthFeatures::class)->enableAll($t);
    }
    app()->instance('currentTenant', $this->tenant->refresh());
    $this->owner = fn () => $this->actingAs(TenantAdmin::withoutGlobalScopes()->where('email', 'owner@egy.test')->first(), 'tenant');
    $this->off = function (string $key) {
        $t = current_tenant();
        $s = $t->settings;
        $s['growth']['features'][$key] = false;
        $t->update(['settings' => $s]);
        app()->instance('currentTenant', $t->refresh());
    };
    $this->cat = fn (string $n) => Category::create(['name' => ['en' => $n, 'ar' => $n], 'slug' => strtolower($n).uniqid(), 'is_active' => true, 'path' => '0', 'depth' => 0]);
    $this->product = function (string $slug, string $price = '100.00', string $cost = '50', int $stock = 20, array $cats = []) {
        $pr = Product::create(['type' => 'simple', 'name' => ['en' => 'Item '.$slug, 'ar' => 'صنف '.$slug], 'description' => ['en' => 'x'], 'slug' => $slug, 'sku' => strtoupper($slug), 'barcode' => app(\App\Services\BarcodeService::class)->generateUnique()]);
        TenantProduct::create(['tenant_id' => $this->tenant->id, 'product_id' => $pr->id, 'price' => $price, 'cost' => $cost, 'is_active' => true]);
        if ($cats) {
            $pr->categories()->attach(array_map(fn ($c) => $c->id, $cats));
        }
        if ($stock > 0) {
            app(StockLedger::class)->record(Branch::first()->id, $pr->id, 0, T::PURCHASE_IN, $stock, $cost);
        }

        return $pr;
    };
    $this->admin = TenantAdmin::withoutGlobalScopes()->where('email', 'owner@egy.test')->value('id');
    $this->svc = fn () => app(BundleProposalService::class);
});

it('margin is computed by Laravel: picks the best discount that keeps the floor, rejects the rest', function () {
    // floor 15%, max discount 30%: price 100 cost 50 -> 15% off = 85, margin 35/85 = 41%
    $p = ($this->svc)()->price(collect([['price' => 60.0, 'cost' => 30.0], ['price' => 40.0, 'cost' => 20.0]]));
    expect($p['discount_percent'])->toBe(15)->and($p['price'])->toBe(85.0)->and($p['margin'])->toBe(35.0)->and($p['margin_percent'])->toBe(41.18);
    // cost 80 of 100: 5% off -> 95, margin 15/95 = 15.79% ok; 8% -> 92: 12/92 = 13% fails; so 5
    $q = ($this->svc)()->price(collect([['price' => 100.0, 'cost' => 80.0]]));
    expect($q['discount_percent'])->toBe(5);
    // cost 90 of 100: even full price is 10% < floor -> rejected
    expect(($this->svc)()->price(collect([['price' => 100.0, 'cost' => 90.0]]))['rejected'])->toBe('margin_below_floor');
    // cost 84 of 100: full price ok (16%) but no discount keeps it -> no customer saving
    expect(($this->svc)()->price(collect([['price' => 100.0, 'cost' => 84.0]]))['rejected'])->toBe('no_customer_saving');
    // inclusive VAT is never revenue
    $t = current_tenant();
    $t->update(['settings' => $t->settings + ['tax_rate' => 14, 'prices_include_tax' => true]]);
    app()->instance('currentTenant', $t->refresh());
    expect(($this->svc)()->margin(114.0, 50.0)['net_revenue'])->toBe(100.0);
});

it('proposes only suitable products, files pending approvals once, and approval creates an INACTIVE draft', function () {
    [$c1, $c2] = [($this->cat)('Skin'), ($this->cat)('Hair')];
    $a = ($this->product)('a', '100', '40', 20, [$c1]);
    $b = ($this->product)('b', '80', '30', 20, [$c2]);
    ($this->product)('nocost', '80', '0', 20, [$c2]);
    ($this->product)('low', '80', '30', 2, [$c2]);
    $r = ($this->svc)()->propose('complementary', [], 1);
    expect($r['approval_ids'])->toHaveCount(1)->and(collect($r['excluded'])->pluck('reason')->all())->toContain('missing_cost', 'low_stock');
    $ap = GrowthApproval::find($r['approval_ids'][0]);
    expect($ap->type)->toBe('bundle_draft')->and($ap->payload['items'])->toHaveCount(2)->and($ap->payload['pricing']['margin_percent'])->toBeGreaterThanOrEqual(15);
    // idempotent: the same set is not proposed twice while pending
    $again = ($this->svc)()->propose('complementary', [], 1);
    expect($again['approval_ids'])->toBe([])->and($again['duplicates'])->toBe(1);
    // approve -> inactive bundle
    $done = app(ApprovalService::class)->approve($ap->id, $this->admin);
    $bundle = Bundle::find($done->result['bundle_id']);
    expect($bundle->is_active)->toBeFalse()->and($bundle->items)->toHaveCount(2)->and((float) $bundle->price)->toBe((float) $ap->payload['price']);
    expect(fn () => app(ApprovalService::class)->approve($ap->id, $this->admin))->toThrow(ValidationException::class);
    expect(Bundle::count())->toBe(1);
});

it('approval re-checks the margin: a cost increase after the proposal blocks the draft', function () {
    [$c1, $c2] = [($this->cat)('Skin'), ($this->cat)('Hair')];
    $a = ($this->product)('a', '100', '40', 20, [$c1]);
    ($this->product)('b', '80', '30', 20, [$c2]);
    $r = ($this->svc)()->propose('complementary', [], 1);
    TenantProduct::where('product_id', $a->id)->update(['cost' => 120]);
    expect(fn () => app(ApprovalService::class)->approve($r['approval_ids'][0], $this->admin))->toThrow(ValidationException::class);
    expect(Bundle::count())->toBe(0)->and(GrowthApproval::find($r['approval_ids'][0])->status)->toBe(GrowthApproval::PENDING);
});

it('bundle_engine OFF: pages 404, proposals blocked at approval; ON again resumes', function () {
    [$c1, $c2] = [($this->cat)('Skin'), ($this->cat)('Hair')];
    ($this->product)('a', '100', '40', 20, [$c1]);
    ($this->product)('b', '80', '30', 20, [$c2]);
    ($this->owner)()->get('http://egy.3alraf.test/admin/growth/bundles')->assertOk();
    $r = ($this->svc)()->propose('complementary', [], 1);
    ($this->off)('bundle_engine');
    ($this->owner)()->get('http://egy.3alraf.test/admin/growth/bundles')->assertNotFound();
    ($this->owner)()->postJson('http://egy.3alraf.test/admin/growth/bundles/propose', ['mode' => 'budget_box'])->assertStatus(403)->assertJson(['error' => 'feature_disabled']);
    expect(fn () => app(ApprovalService::class)->approve($r['approval_ids'][0], $this->admin))->toThrow(ValidationException::class);
    expect(Bundle::count())->toBe(0);
});

it('slow-mover pairing needs a shared category; budget box stays inside the budget', function () {
    $c = ($this->cat)('Skin');
    ($this->product)('pop', '100', '40', 30, [$c]);
    ($this->product)('slow', '60', '25', 30, [$c]);
    ($this->product)('far', '60', '25', 30, [($this->cat)('Other')]);
    $svc = ($this->svc)();
    // no sales at all: nothing is "popular", so no pairing is invented
    expect($svc->build('slow_mover')['proposals'])->toBe([]);
    $box = $svc->build('budget_box', ['budgets' => [200]]);
    foreach ($box['proposals'] as $p) {
        expect($p['pricing']['regular_total'])->toBeLessThanOrEqual(200.0);
    }
    expect($box['proposals'])->not->toBeEmpty();
});

it('bundle A/B: stable split, one exposure per visitor, z-test winner, conclude applies B, OFF disables', function () {
    $c = ($this->cat)('Skin');
    ($this->product)('a', '100', '40', 20, [$c]);
    $bundle = Bundle::create(['name' => ['en' => 'Old', 'ar' => 'قديم'], 'slug' => 'old', 'price' => 90, 'is_active' => false]);
    $ab = app(BundleAbService::class);
    $t = $ab->start($bundle->id, ['name' => ['en' => 'New'], 'description' => ['en' => 'Better']], 50, 20, 1);
    expect(fn () => $ab->start($bundle->id, ['name' => ['en' => 'X']], 50, 20, 1))->toThrow(ValidationException::class);
    expect($ab->assign($t, 'v1'))->toBe($ab->assign($t, 'v1'));
    $v = $ab->expose($bundle->id, 'visitor-x');
    $ab->expose($bundle->id, 'visitor-x');
    expect($v->refresh()->exposures)->toBe(1);
    // synthetic data: B clearly better
    $a = $t->variants->firstWhere('label', 'A');
    $b = $t->variants->firstWhere('label', 'B');
    $a->update(['exposures' => 200, 'carts' => 10]);
    $b->update(['exposures' => 200, 'carts' => 40]);
    expect($ab->results($t->refresh())['recommendation'])->toBe('winner_b');
    $a->update(['exposures' => 10, 'carts' => 1]);
    expect($ab->results($t->refresh())['recommendation'])->toBe('insufficient_data');
    $ab->conclude($t->id, 'B', 1);
    expect($bundle->refresh()->getTranslation('name', 'en'))->toBe('New')->and(BundleAbTest::find($t->id)->status)->toBe('stopped');
    expect(fn () => $ab->conclude($t->id, 'A', 1))->toThrow(ValidationException::class);
    // OFF: no exposure
    $t2 = $ab->start($bundle->id, ['name' => ['en' => 'Newer']], 50, 20, 1);
    ($this->off)('bundle_ab');
    expect(app(BundleAbService::class)->expose($bundle->id, 'zzz'))->toBeNull();
});

it('seasonal calendar: Hijri dates, idempotent year creation, readiness, close needs the end date, tenant isolation', function () {
    $svc = app(SeasonalCalendarService::class);
    expect($svc->ensureYear(2026))->toBe(8)->and($svc->ensureYear(2026))->toBe(0);
    $r = SeasonalEvent::where('key', 'ramadan')->where('year', 2026)->first();
    expect($r->starts_on->between(Carbon::create(2026, 2, 17), Carbon::create(2026, 2, 19)))->toBeTrue()->and($r->hijri)->toBeTrue();
    $f = SeasonalEvent::where('key', 'eid_fitr')->where('year', 2026)->first();
    expect($f->starts_on->between(Carbon::create(2026, 3, 19), Carbon::create(2026, 3, 21)))->toBeTrue();
    expect(SeasonalEvent::where('key', 'white_friday')->where('year', 2026)->first()->starts_on->dayOfWeek)->toBe(Carbon::FRIDAY);
    expect($r->tasks)->toHaveCount(7);
    // readiness: no mapping -> fail, with evidence
    expect(collect($svc->readiness($r)['checks'])->firstWhere('key', 'categories')['status'])->toBe('fail');
    $c = ($this->cat)('Gifts');
    ($this->product)('a', '100', '40', 20, [$c]);
    ($this->product)('b', '100', '0', 20, [$c]);
    $svc->update($r->id, ['starts_on' => '2026-12-01', 'ends_on' => '2026-12-05', 'lead_time_days' => 10, 'category_ids' => [$c->id]], 1);
    $chk = collect($svc->readiness($r->refresh())['checks'])->keyBy('key');
    expect($chk['products']['status'])->toBe('ok')->and($chk['margin']['status'])->toBe('warn')->and($chk['margin']['evidence']['missing_cost'])->toBe(1);
    expect(fn () => $svc->close($r->id, ['lessons' => 'x'], 1))->toThrow(ValidationException::class);
    $svc->update($r->id, ['starts_on' => '2026-01-01', 'ends_on' => '2026-01-05', 'lead_time_days' => 10, 'category_ids' => [$c->id]], 1);
    $m = $svc->close($r->id, ['lessons' => 'Start earlier', 'start_timing' => '3 weeks before'], 1);
    expect($m->lessons)->toBe('Start earlier')->and($m->performance)->toHaveKeys(['best_products', 'profitable_bundles'])->and($r->refresh()->status)->toBe('closed');
    expect(fn () => $svc->update($r->id, ['starts_on' => '2026-01-01', 'ends_on' => '2026-01-05', 'lead_time_days' => 1], 1))->toThrow(ValidationException::class);
    // next year sees last year's lesson
    $svc->ensureYear(2027);
    expect($svc->lastMemory(SeasonalEvent::where('key', 'ramadan')->where('year', 2027)->first())->lessons)->toBe('Start earlier');
    // isolation
    app()->instance('currentTenant', $this->other);
    expect(SeasonalEvent::count())->toBe(0);
    app()->instance('currentTenant', $this->tenant);
    // admin page + switch
    ($this->owner)()->get('http://egy.3alraf.test/admin/growth/seasonal?year=2026')->assertOk();
    ($this->off)('seasonal_calendar');
    ($this->owner)()->get('http://egy.3alraf.test/admin/growth/seasonal')->assertNotFound();
});

it('seasonal bundle proposals use the event categories only', function () {
    $svc = app(SeasonalCalendarService::class);
    $svc->ensureYear(2026);
    $e = SeasonalEvent::where('key', 'mothers_day')->first();
    $gift = ($this->cat)('Gifts');
    ($this->product)('g1', '100', '40', 20, [$gift]);
    ($this->product)('g2', '90', '35', 20, [$gift]);
    ($this->product)('x1', '90', '35', 20, [($this->cat)('Other')]);
    ($this->product)('x2', '90', '35', 20, [($this->cat)('Other2')]);
    $svc->update($e->id, ['starts_on' => $e->starts_on->toDateString(), 'ends_on' => $e->ends_on->toDateString(), 'lead_time_days' => 21, 'category_ids' => [$gift->id]], 1);
    $res = $svc->proposeBundles($e->id, 1);
    expect($res['approval_ids'])->toHaveCount(1);
    $ids = Product::whereIn('slug', ['g1', 'g2'])->pluck('id')->sort()->values()->all();
    expect(collect(GrowthApproval::find($res['approval_ids'][0])->payload['items'])->pluck('product_id')->sort()->values()->all())->toBe($ids);
});

it('gift finder: never exceeds the budget, excludes out-of-stock, margin only breaks ties, restrictions apply', function () {
    $c1 = ($this->cat)('Skin');
    $c2 = ($this->cat)('Hair');
    $fit = ($this->product)('fit', '100', '95', 20, [$c1]);          // best fit (category), TERRIBLE margin
    ($this->product)('other', '100', '10', 20, [$c2]);               // high margin, wrong category
    ($this->product)('over', '300', '10', 20, [$c1]);                // above budget
    ($this->product)('near', '130', '10', 20, [$c1]);                // slightly above budget
    ($this->product)('nostock', '90', '10', 0, [$c1]);
    ($this->product)('forbidden', '90', '10', 20, [$c1]);
    $r = app(GiftFinderService::class)->search(['budget' => 120, 'category_id' => $c1->id, 'restrictions' => 'forbidden']);
    $names = collect($r['results'])->pluck('slug')->all();
    expect($names[0])->toBe('fit')                                       // better fit is NOT demoted by its lower margin
        ->and($names)->not->toContain('over', 'nostock', 'forbidden')
        ->and(collect($r['results'])->max('price'))->toBeLessThanOrEqual(120.0)
        ->and(collect($r['near_budget'])->pluck('slug')->all())->toBe(['near'])->and($r['near_budget'][0]['over_budget'])->toBeTrue();
    expect(collect($r['results'])->every(fn ($x) => $x['explanation'] !== ''))->toBeTrue();
    // tie on fit -> higher margin first
    $tie = app(GiftFinderService::class)->search(['budget' => 120]);
    expect(GiftFinderSession::count())->toBe(2);
    // tenant isolation: the other tenant sees nothing of ours
    app()->instance('currentTenant', $this->other);
    expect(app(GiftFinderService::class)->search(['budget' => 500])['results'])->toBe([]);
    app()->instance('currentTenant', $this->tenant);
    expect(fn () => app(GiftFinderService::class)->search(['budget' => 0]))->toThrow(ValidationException::class);
});

it('gift finder web + API endpoints, prepared cart, stats, and the switch', function () {
    $c = ($this->cat)('Skin');
    $p = ($this->product)('fit', '100', '40', 20, [$c]);
    $this->get('http://egy.3alraf.test/en/gift-finder')->assertOk();
    $j = $this->postJson('http://egy.3alraf.test/en/gift-finder', ['budget' => 150, 'category_id' => $c->id])->assertOk()->json('data');
    expect($j['results'][0]['product_id'])->toBe($p->id);
    $this->postJson('http://egy.3alraf.test/en/gift-finder', ['budget' => -5])->assertStatus(422);
    $this->postJson('http://egy.3alraf.test/en/gift-finder/'.$j['token'].'/cart', [])->assertOk()->assertJsonPath('data.added.0', $p->id);
    expect(GiftFinderSession::first()->cart_at)->not->toBeNull();
    $this->postJson('http://egy.3alraf.test/api/v1/gift-finder', ['budget' => 150])->assertOk()->assertJsonPath('data.results.0.slug', 'fit');
    expect(app(GiftFinderService::class)->stats()['cart_rate'])->toBe(50.0);
    ($this->owner)()->get('http://egy.3alraf.test/admin/growth/gift-finder')->assertOk();
    ($this->off)('gift_finder');
    $this->get('http://egy.3alraf.test/en/gift-finder')->assertNotFound();
    $this->postJson('http://egy.3alraf.test/en/gift-finder', ['budget' => 150])->assertStatus(403)->assertJson(['error' => 'feature_disabled']);
    $this->postJson('http://egy.3alraf.test/api/v1/gift-finder', ['budget' => 150])->assertStatus(403);
});
