<?php

use App\DTOs\Orders\CheckoutData;
use App\Enums\AffiliateStatus;
use App\Enums\CommissionStatus;
use App\Enums\OrderStatus as S;
use App\Enums\ProductListType as L;
use App\Enums\ReviewStatus;
use App\Enums\StockMovementType as T;
use App\Models\Affiliate;
use App\Models\AffiliateClick;
use App\Models\AffiliateCommission;
use App\Models\AffiliateTier;
use App\Models\Branch;
use App\Models\City;
use App\Models\Customer;
use App\Models\CustomPage;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductListItem;
use App\Models\Review;
use App\Models\ShippingCompany;
use App\Models\Tenant;
use App\Models\TenantAdmin;
use App\Models\TenantProduct;
use App\Services\Affiliates\AffiliateCommissionService;
use App\Services\Affiliates\AffiliateService;
use App\Services\Lists\ProductListResolver;
use App\Services\Orders\OrderPlacementService;
use App\Services\Orders\OrderStatusService;
use App\Services\Stock\StockLedger;
use App\Support\ReservedSlugs;
use Database\Seeders\FoundationSeeder;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(FoundationSeeder::class);
    $this->tenant = Tenant::where('slug', 'egy')->first();
    $this->t = 'http://egy.3alraf.test/admin';
    $this->s = 'http://egy.3alraf.test/ar';
    app()->instance('currentTenant', $this->tenant);
    $this->branch = Branch::first();
    $this->city = City::first();
    $this->company = ShippingCompany::first();
    $this->cod = PaymentMethod::where('type', 'cod')->first();
    $this->mk = function (string $slug, $price = 100) {
        $p = Product::create(['type' => 'simple', 'name' => ['ar' => "منتج $slug", 'en' => "P $slug"], 'slug' => $slug, 'sku' => strtoupper($slug), 'barcode' => app(\App\Services\BarcodeService::class)->generateUnique()]);
        TenantProduct::create(['product_id' => $p->id, 'price' => $price, 'cost' => 60, 'is_active' => true]);
        app(StockLedger::class)->record($this->branch->id, $p->id, 0, T::PURCHASE_IN, 100, '60.00');

        return $p;
    };
    $this->product = ($this->mk)('p1');
    $this->aff = Affiliate::where('email', 'aff@egy.test')->first();
    // tiers from seeder: 0 -> 5%, 1000 -> 7%, 5000 -> 10%
    $this->place = fn (int $qty = 1, ?int $affId = null, ?Product $p = null, array $o = []) => app(OrderPlacementService::class)->place(new CheckoutData(
        ...array_merge(['name' => 'Ali', 'phone' => '0100', 'cityId' => $this->city->id, 'address' => 'St', 'shippingCompanyId' => $this->company->id,
            'paymentMethodId' => $this->cod->id, 'affiliateId' => $affId], $o)),
        [['product_id' => ($p ?? $this->product)->id, 'variant_id' => 0, 'qty' => $qty]])['order'];
    $this->deliver = function (Order $o) {
        $st = app(OrderStatusService::class);
        foreach ([S::CONFIRMED, S::PROCESSING, S::SHIPPED, S::DELIVERED] as $to) {
            $st->change($o, $to);
        }

        return $o->refresh();
    };
});

it('picks the commission tier from approved lifetime delivered sales before the order', function () {
    $svc = app(AffiliateCommissionService::class);
    expect($svc->percentFor($this->aff))->toBe('5.00')
        ->and((string) $svc->tierFor('999.99')->percent)->toBe('5.00')
        ->and((string) $svc->tierFor('1000')->percent)->toBe('7.00')
        ->and((string) $svc->tierFor('99999')->percent)->toBe('10.00');

    // order 1: 10 x 100 = 1000 at 5% -> 50.00 (pending); not delivered yet so it does not count for tiers
    $o1 = ($this->place)(10, $this->aff->id);
    expect((string) $o1->affiliate_commission)->toBe('50.00')->and($o1->affiliate_id)->toBe($this->aff->id);
    $c1 = AffiliateCommission::where('order_id', $o1->id)->first();
    expect($c1->status)->toBe(CommissionStatus::PENDING)->and((string) $c1->amount)->toBe('50.00')->and((string) $c1->base)->toBe('1000.00')->and((string) $c1->percent)->toBe('5.00');
    expect($svc->percentFor($this->aff))->toBe('5.00')->and($svc->lifetimeSales($this->aff))->toBe('0.00');

    ($this->deliver)($o1);
    expect($svc->lifetimeSales($this->aff))->toBe('1000.00')->and($svc->percentFor($this->aff))->toBe('7.00');

    // order 2 is now in the 7% tier: base 200 -> 14.00 ; discount code shrinks the base
    $o2 = ($this->place)(2, $this->aff->id);
    expect((string) $o2->affiliate_commission)->toBe('14.00');
    $o3 = ($this->place)(2, $this->aff->id, null, ['discountCode' => 'WELCOME10']);
    expect((string) $o3->discount)->toBe('20.00')->and((string) $o3->affiliate_commission)->toBe('12.60');   // (200-20) * 7%
});

it('ignores unknown/unapproved affiliates and records no commission without one', function () {
    $o = ($this->place)(1);
    expect($o->affiliate_id)->toBeNull()->and(AffiliateCommission::count())->toBe(0);
    app(AffiliateService::class)->setStatus($this->aff, AffiliateStatus::SUSPENDED);
    $o = ($this->place)(1, $this->aff->id);
    expect($o->affiliate_id)->toBeNull()->and((string) $o->affiliate_commission)->toBe('0.00');
});

it('voids commission on cancel, reduces it on partial return and approves after the return window; payout flow', function () {
    $svc = app(AffiliateCommissionService::class);
    $cancelled = ($this->place)(2, $this->aff->id);
    app(OrderStatusService::class)->change($cancelled, S::CANCELLED);
    expect(AffiliateCommission::where('order_id', $cancelled->id)->first()->status)->toBe(CommissionStatus::VOIDED);

    $o = ($this->place)(4, $this->aff->id);                    // 400 @5% = 20.00
    ($this->deliver)($o);
    $svc->approveDue();                                        // window not elapsed
    $c = AffiliateCommission::where('order_id', $o->id)->first();
    expect($c->status)->toBe(CommissionStatus::PENDING);

    // partial return of 1 of 4 units -> commission reduced pro-rata (20 * 100/400 = 5)
    $ret = app(\App\Services\Orders\ReturnService::class);
    $r = $ret->request($o, new \App\DTOs\Orders\ReturnRequestData([$o->items->first()->id => 1], 'x', true, false));
    $ret->approve($r);
    expect((string) $c->fresh()->amount)->toBe('15.00');

    $this->travel(15)->days();
    expect($svc->approveDue())->toBe(1)->and($c->fresh()->status)->toBe(CommissionStatus::APPROVED);
    expect($svc->totals($this->aff->fresh())['available'])->toBe('15.00');

    $affs = app(AffiliateService::class);
    $payout = $affs->requestPayout($this->aff);
    expect((string) $payout->amount)->toBe('15.00')->and($svc->totals($this->aff)['available'])->toBe('0.00');
    expect(fn () => $affs->requestPayout($this->aff))->toThrow(ValidationException::class);
    $affs->markPaid($payout);
    expect($c->fresh()->status)->toBe(CommissionStatus::PAID)->and($svc->totals($this->aff)['paid'])->toBe('15.00');
    expect(fn () => $affs->markPaid($payout->fresh()))->toThrow(ValidationException::class);
});

it('tracks affiliate clicks with a 30 day cookie and attributes the order to the last click', function () {
    $url = "{$this->s}/product/{$this->product->slug}?utm_source=affiliate&aff={$this->aff->uuid}";
    $res = $this->get($url)->assertOk()->assertCookie('aff', $this->aff->uuid);
    expect(AffiliateClick::where('affiliate_id', $this->aff->id)->where('product_id', $this->product->id)->count())->toBe(1);
    $this->get("{$this->s}/product/{$this->product->slug}?utm_source=affiliate&aff=bogus")->assertOk();      // unknown uuid: no click
    $this->get("{$this->s}/product/{$this->product->slug}?aff={$this->aff->uuid}")->assertOk();               // missing utm_source: no click
    expect(AffiliateClick::count())->toBe(1);

    $payload = ['name' => 'Sara', 'phone' => '0111', 'city_id' => $this->city->id, 'address' => 'St 2', 'shipping_company_id' => $this->company->id, 'payment_method_id' => $this->cod->id];
    $this->postJson("{$this->s}/cart/items", ['product_id' => $this->product->id, 'variant_id' => 0, 'quantity' => 2])->assertOk();
    $res = $this->withCredentials()->withCookie('aff', $this->aff->uuid)->postJson("{$this->s}/checkout", $payload)->assertOk();
    $order = Order::where('number', $res->json('data.number'))->first();
    expect($order->affiliate_id)->toBe($this->aff->id)->and((string) $order->affiliate_commission)->toBe('10.00');

    // self referral (same email) is not attributed
    $this->postJson("{$this->s}/cart/items", ['product_id' => $this->product->id, 'variant_id' => 0, 'quantity' => 1])->assertOk();
    $res = $this->withCredentials()->withCookie('aff', $this->aff->uuid)->postJson("{$this->s}/checkout", $payload + ['email' => 'AFF@egy.test'])->assertOk();
    expect(Order::where('number', $res->json('data.number'))->value('affiliate_id'))->toBeNull();
});

it('registers affiliates as pending, blocks login until approved, and shows dashboard + product links with QR', function () {
    $d = ['name' => 'New Aff', 'email' => 'new@egy.test', 'phone' => '010', 'password' => 'secret123', 'password_confirmation' => 'secret123'];
    $this->postJson("{$this->s}/affiliate/register/validate", ['email' => 'aff@egy.test'] + $d)->assertStatus(422)->assertJsonValidationErrors(['email']);
    $this->postJson("{$this->s}/affiliate/register", $d)->assertOk();
    $new = Affiliate::where('email', 'new@egy.test')->first();
    expect($new->status)->toBe(AffiliateStatus::PENDING)->and($new->uuid)->not->toBeEmpty();
    $this->postJson("{$this->s}/affiliate/login", ['email' => 'new@egy.test', 'password' => 'secret123'])->assertStatus(422)->assertJsonValidationErrors(['email']);
    app(AffiliateService::class)->setStatus($new, AffiliateStatus::APPROVED);
    $this->postJson("{$this->s}/affiliate/login", ['email' => 'new@egy.test', 'password' => 'wrong'])->assertStatus(422);
    $this->postJson("{$this->s}/affiliate/login", ['email' => 'new@egy.test', 'password' => 'secret123'])->assertOk();

    $this->get("{$this->s}/affiliate")->assertOk()->assertSee(__('growth.dashboard'));
    $link = "http://egy.3alraf.test/ar/product/p1?utm_source=affiliate&aff={$new->uuid}";
    $this->get("{$this->s}/affiliate/products")->assertOk()->assertSee(e($link), false)->assertSee($this->product->barcode)->assertSee('/affiliate/qr/p1', false);
    $this->get("{$this->s}/affiliate/qr/p1")->assertOk()->assertHeader('Content-Type', 'image/svg+xml');
    $this->post("{$this->s}/affiliate/logout")->assertRedirect();
    $this->get("{$this->s}/affiliate")->assertRedirect("{$this->s}/affiliate/login");
});

it('resolves lists: pinned first in order, algorithmic fill, de-duplicated, limited, only sellable', function () {
    $r = app(ProductListResolver::class);
    $b = ($this->mk)('p2');
    $c = ($this->mk)('p3');
    $d = ($this->mk)('p4');
    $hidden = ($this->mk)('p5');

    // sales: p3 sells 5, p2 sells 2, p1 sells 1 (delivered)
    foreach ([[$c, 5], [$b, 2], [$this->product, 1], [$hidden, 9]] as [$p, $q]) {
        ($this->deliver)(($this->place)($q, null, $p));
    }
    ($this->place)(50, null, $d);   // pending order must not count
    TenantProduct::where('product_id', $hidden->id)->update(['is_active' => false]);

    $ids = fn ($col) => $col->pluck('product_id')->all();
    expect($ids($r->resolve(L::MOST_SOLD, 3)))->toBe([$c->id, $b->id, $this->product->id]);

    // pin d then p1 (admin order wins), rest filled by algorithm without duplicates
    ProductListItem::create(['list_type' => 'most_sold', 'product_id' => $d->id, 'sort_order' => 0]);
    ProductListItem::create(['list_type' => 'most_sold', 'product_id' => $this->product->id, 'sort_order' => 1]);
    ProductListItem::create(['list_type' => 'most_sold', 'product_id' => $hidden->id, 'sort_order' => 2]);   // inactive pin is skipped
    expect($ids($r->resolve(L::MOST_SOLD, 4)))->toBe([$d->id, $this->product->id, $c->id, $b->id])
        ->and($ids($r->resolve(L::MOST_SOLD, 2)))->toBe([$d->id, $this->product->id])
        ->and($r->resolve(L::MOST_SOLD, 10)->pluck('product_id')->unique()->count())->toBe($r->resolve(L::MOST_SOLD, 10)->count());

    // most reviewed: approved reviews only (count, then average)
    $cust = Customer::first() ?? Customer::create(['name' => 'C', 'email' => 'c@x.test', 'password' => 'password', 'is_active' => true]);
    $cust2 = Customer::create(['name' => 'C2', 'email' => 'c2@x.test', 'password' => 'password', 'is_active' => true]);
    Review::create(['product_id' => $b->id, 'customer_id' => $cust->id, 'rating' => 3, 'status' => ReviewStatus::APPROVED]);
    Review::create(['product_id' => $b->id, 'customer_id' => $cust2->id, 'rating' => 4, 'status' => ReviewStatus::APPROVED]);
    Review::create(['product_id' => $c->id, 'customer_id' => $cust->id, 'rating' => 5, 'status' => ReviewStatus::APPROVED]);
    Review::create(['product_id' => $d->id, 'customer_id' => $cust->id, 'rating' => 5, 'status' => ReviewStatus::PENDING]);
    expect($ids($r->resolve(L::MOST_REVIEWED, 5)))->toBe([$b->id, $c->id]);

    // recommended with context: bought-together first, then same category / popularity, never the context product
    $mixed = app(OrderPlacementService::class)->place(new CheckoutData(name: 'A', phone: '1', cityId: $this->city->id, address: 'x', shippingCompanyId: $this->company->id, paymentMethodId: $this->cod->id),
        [['product_id' => $this->product->id, 'variant_id' => 0, 'qty' => 1], ['product_id' => $b->id, 'variant_id' => 0, 'qty' => 1]])['order'];
    expect($ids($r->resolve(L::RECOMMENDED, 3, $this->product->id))[0])->toBe($b->id)
        ->and($ids($r->resolve(L::RECOMMENDED, 3, $this->product->id)))->not->toContain($this->product->id);
    expect($r->resolve(L::RECOMMENDED, 3))->toHaveCount(3);
});

it('admin pins lists through the UI endpoints and respects the limit setting', function () {
    $b = ($this->mk)('p2');
    $this->actingAs(TenantAdmin::withoutGlobalScopes()->where('email', 'owner@egy.test')->first(), 'tenant');
    $this->get("{$this->t}/product-lists")->assertOk();
    $this->putJson("{$this->t}/product-lists/most_sold", ['limit' => 0, 'product_ids' => [$b->id]])->assertStatus(422)->assertJsonValidationErrors(['limit']);
    $this->postJson("{$this->t}/product-lists/most_sold/validate", ['limit' => 1, 'product_ids' => [999999]])->assertStatus(422);
    $this->putJson("{$this->t}/product-lists/most_sold", ['limit' => 1, 'product_ids' => [$b->id, $this->product->id]])->assertOk();
    expect(ProductListItem::where('list_type', 'most_sold')->orderBy('sort_order')->pluck('product_id')->all())->toBe([$b->id, $this->product->id])
        ->and(app(ProductListResolver::class)->limitFor(L::MOST_SOLD))->toBe(1)
        ->and(app(ProductListResolver::class)->resolve(L::MOST_SOLD)->pluck('product_id')->all())->toBe([$b->id]);
    $this->putJson("{$this->t}/product-lists/bogus", ['limit' => 3])->assertNotFound();
});

it('lets only buyers review, moderates reviews, and manages wishlist', function () {
    $cust = Customer::create(['name' => 'Buyer', 'email' => 'buyer@x.test', 'password' => 'password', 'is_active' => true]);
    $this->actingAs($cust, 'web');
    $this->postJson("{$this->s}/product/p1/reviews", ['rating' => 5])->assertStatus(422);
    ($this->deliver)(($this->place)(1, null, null, ['customerId' => $cust->id]));
    $this->postJson("{$this->s}/product/p1/reviews", ['rating' => 9])->assertStatus(422)->assertJsonValidationErrors(['rating']);
    $this->postJson("{$this->s}/product/p1/reviews", ['rating' => 5, 'body' => 'great'])->assertOk();
    $this->postJson("{$this->s}/product/p1/reviews", ['rating' => 4, 'body' => 'edited'])->assertOk();
    expect(Review::count())->toBe(1)->and(Review::first()->status)->toBe(ReviewStatus::PENDING)->and(Review::first()->body)->toBe('edited');
    $this->get("{$this->s}/product/p1")->assertOk()->assertDontSee('Buyer</strong>', false);

    $rev = Review::first();
    $this->actingAs(TenantAdmin::withoutGlobalScopes()->where('email', 'owner@egy.test')->first(), 'tenant');
    $this->get("{$this->t}/reviews")->assertOk();
    $this->getJson("{$this->t}/reviews/datatable?draw=1&start=0&length=10&status=pending")->assertOk()->assertJsonPath('recordsFiltered', 1);
    $this->postJson("{$this->t}/reviews/{$rev->id}/approve")->assertOk();
    $this->actingAs($cust, 'web')->get("{$this->s}/product/p1")->assertOk()->assertSee('Buyer</strong>', false);
    $this->postJson("{$this->t}/reviews/{$rev->id}/reject")->assertOk();
    expect($rev->fresh()->status)->toBe(ReviewStatus::REJECTED);

    $this->actingAs($cust, 'web')->postJson("{$this->s}/wishlist/{$this->product->id}/toggle")->assertOk()->assertJsonPath('data.in_wishlist', true);
    $this->get("{$this->s}/wishlist")->assertOk()->assertSee($this->product->name);
    $this->postJson("{$this->s}/wishlist/{$this->product->id}/toggle")->assertJsonPath('data.in_wishlist', false);
});

it('serves custom pages at /{slug} with pinned products first, filters and reserved slug rules', function () {
    $b = ($this->mk)('p2', 500);
    $c = ($this->mk)('p3', 50);
    $this->actingAs(TenantAdmin::withoutGlobalScopes()->where('email', 'owner@egy.test')->first(), 'tenant');
    $base = ['title' => ['ar' => 'صفحة', 'en' => 'Page'], 'is_active' => 1];
    foreach (['admin', 'cart', 'ar', 'xyz'] as $bad) {   // reserved / locale-like
        $this->postJson("{$this->t}/custom-pages/validate", ['slug' => $bad] + $base)->assertStatus(422)->assertJsonValidationErrors(['slug']);
    }
    $this->postJson("{$this->t}/custom-pages/validate", ['slug' => 'Bad Slug'] + $base)->assertStatus(422);
    expect(ReservedSlugs::isReserved('webhooks'))->toBeTrue();
    $this->postJson("{$this->t}/custom-pages", ['slug' => 'cheap-stuff', 'filters' => ['price_max' => 120, 'sort' => 'price_asc'], 'product_ids' => [$b->id]] + $base)->assertOk();
    $this->postJson("{$this->t}/custom-pages", ['slug' => 'cheap-stuff'] + $base)->assertStatus(422)->assertJsonValidationErrors(['slug']);   // unique per tenant
    $this->get("{$this->t}/custom-pages")->assertOk();
    $this->get("{$this->t}/custom-pages/create")->assertOk();

    auth('tenant')->logout();
    $html = $this->get('http://egy.3alraf.test/cheap-stuff')->assertOk()->assertSee('صفحة')->getContent();
    // pinned p2 (500) first although outside the price filter, then <=120 sorted by price: p3 (50), p1 (100)
    $pos = fn ($slug) => strpos($html, "/product/$slug");
    expect($pos('p2'))->toBeInt()->toBeLessThan($pos('p3'))->and($pos('p3'))->toBeLessThan($pos('p1'));
    $this->get('http://egy.3alraf.test/cheap-stuff?price_max=60')->assertOk()->assertSee('/product/p3', false)->assertDontSee('/product/p1"', false);
    $this->get('http://egy.3alraf.test/nope-nope')->assertNotFound();
    $this->get('http://egy.3alraf.test/offers')->assertOk();      // seeded page (flash sale filter)
    $this->get('http://egy.3alraf.test/ar')->assertOk();          // static routes still win
    CustomPage::where('slug', 'cheap-stuff')->update(['is_active' => false]);
    $this->get('http://egy.3alraf.test/cheap-stuff')->assertNotFound();
});

it('manages static pages, tiers, affiliates and payouts from the tenant admin', function () {
    $this->actingAs(TenantAdmin::withoutGlobalScopes()->where('email', 'owner@egy.test')->first(), 'tenant');
    $this->postJson("{$this->t}/static-pages", ['slug' => 'faq', 'title' => ['ar' => 'أسئلة', 'en' => 'FAQ'], 'content' => ['ar' => 'نص', 'en' => 'Body text'], 'is_active' => 1])->assertOk();
    $this->get("{$this->t}/static-pages")->assertOk();
    $this->get("{$this->s}/page/faq")->assertOk()->assertSee('أسئلة')->assertSee('نص');
    $this->get("{$this->s}/page/privacy-policy")->assertOk();
    $this->get("{$this->s}/page/missing")->assertNotFound();

    $this->postJson("{$this->t}/affiliate-tiers/validate", ['name' => 'X', 'min_sales' => 0, 'percent' => 3])->assertStatus(422)->assertJsonValidationErrors(['min_sales']);   // duplicate threshold
    $this->postJson("{$this->t}/affiliate-tiers/validate", ['name' => 'X', 'min_sales' => 10, 'percent' => 101])->assertStatus(422)->assertJsonValidationErrors(['percent']);
    $this->postJson("{$this->t}/affiliate-tiers", ['name' => 'Platinum', 'min_sales' => 20000, 'percent' => 15])->assertOk();
    expect((string) app(AffiliateCommissionService::class)->tierFor('25000')->percent)->toBe('15.00');
    $this->get("{$this->t}/affiliate-tiers")->assertOk();

    $pending = Affiliate::create(['name' => 'P', 'email' => 'p@x.test', 'password' => 'password', 'status' => AffiliateStatus::PENDING]);
    $this->get("{$this->t}/affiliates")->assertOk();
    $this->getJson("{$this->t}/affiliates/datatable?draw=1&start=0&length=10")->assertOk()->assertJsonPath('recordsTotal', 2);
    $this->postJson("{$this->t}/affiliates/{$pending->id}/approve")->assertOk();
    expect($pending->fresh()->status)->toBe(AffiliateStatus::APPROVED);
    $this->postJson("{$this->t}/affiliates/{$pending->id}/suspend")->assertOk();

    $o = ($this->place)(2, $this->aff->id);
    ($this->deliver)($o);
    $this->travel(20)->days();
    app(AffiliateCommissionService::class)->approveDue();
    $payout = app(AffiliateService::class)->requestPayout($this->aff);
    $this->get("{$this->t}/affiliate-payouts")->assertOk();
    $this->getJson("{$this->t}/affiliate-payouts/datatable?draw=1&start=0&length=10")->assertOk()->assertJsonPath('recordsTotal', 1);
    $this->postJson("{$this->t}/affiliate-payouts/{$payout->id}/reject")->assertOk();
    expect(AffiliateCommission::first()->payout_id)->toBeNull();
    $p2 = app(AffiliateService::class)->requestPayout($this->aff);
    $this->postJson("{$this->t}/affiliate-payouts/{$p2->id}/pay")->assertOk();
    expect(AffiliateCommission::first()->status)->toBe(CommissionStatus::PAID);
    $this->postJson("{$this->t}/affiliate-payouts/{$p2->id}/pay")->assertStatus(422);
});
