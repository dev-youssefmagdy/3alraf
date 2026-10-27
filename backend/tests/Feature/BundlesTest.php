<?php

use App\DTOs\Orders\CheckoutData;
use App\DTOs\Orders\ReturnRequestData;
use App\Enums\OrderStatus as S;
use App\Enums\StockMovementType as T;
use App\Models\Bundle;
use App\Models\BundleItem;
use App\Models\Branch;
use App\Models\City;
use App\Models\Customer;
use App\Models\DiscountCode;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ShippingCompany;
use App\Models\StockLevel;
use App\Models\Tenant;
use App\Models\TenantAdmin;
use App\Models\TenantProduct;
use App\Services\Orders\BundleResolver;
use App\Services\Orders\CartService;
use App\Services\Orders\OrderPlacementService;
use App\Services\Orders\OrderStatusService;
use App\Services\Orders\ReturnService;
use App\Services\Retention\SoftDeletePurger;
use App\Services\Stock\StockLedger;
use Database\Seeders\FoundationSeeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(FoundationSeeder::class);
    Storage::fake('media');
    Storage::fake('private');
    $this->tenant = Tenant::where('slug', 'egy')->first();
    codUpfront($this->tenant, false);
    app()->instance('currentTenant', $this->tenant);
    $this->t = 'http://egy.3alraf.test/admin';
    $this->s = 'http://egy.3alraf.test/ar';
    $this->api = 'http://egy.3alraf.test/api/v1';
    $this->branch = Branch::first();
    $this->city = City::first();
    $this->company = ShippingCompany::first();
    $this->cod = PaymentMethod::where('type', 'cod')->first();
    $this->ledger = app(StockLedger::class);
    $this->mk = function (string $slug, $price, int $stock = 20, $cost = 6) {
        $p = Product::create(['type' => 'simple', 'name' => ['ar' => "منتج $slug", 'en' => "P $slug"], 'slug' => $slug, 'sku' => strtoupper($slug), 'barcode' => app(\App\Services\BarcodeService::class)->generateUnique()]);
        TenantProduct::create(['product_id' => $p->id, 'price' => $price, 'cost' => $cost, 'is_active' => true]);
        $this->ledger->record($this->branch->id, $p->id, 0, T::PURCHASE_IN, $stock, (string) $cost);

        return $p;
    };
    $this->a = ($this->mk)('a', '33.33', 20, '10');
    $this->b = ($this->mk)('b', '10.00', 20, '4');
    $this->c = ($this->mk)('c', '7.00', 20, '3');
    // [product, qty per bundle] => bundle at a fixed price
    $this->bundle = function (string $price, array $parts = null, array $o = []) {
        $parts ??= [[$this->a, 1], [$this->b, 3], [$this->c, 1]];
        $bundle = Bundle::create($o + ['name' => ['ar' => 'باقة', 'en' => 'Pack'], 'slug' => 'pack-'.mt_rand(1000, 99999), 'price' => $price, 'is_active' => true]);
        foreach ($parts as [$p, $q]) {
            BundleItem::create(['bundle_id' => $bundle->id, 'product_id' => $p->id, 'product_variant_id' => 0, 'quantity' => $q]);
        }

        return $bundle->load('items');
    };
    $this->checkout = fn (array $o = []) => new CheckoutData(...array_merge([
        'name' => 'Ali', 'phone' => '01000000100', 'cityId' => $this->city->id, 'address' => 'Street 1',
        'shippingCompanyId' => $this->company->id, 'paymentMethodId' => $this->cod->id,
    ], $o));
    $this->place = fn (array $lines, array $o = []) => app(OrderPlacementService::class)->place(($this->checkout)($o), $lines)['order'];
    $this->bl = fn (Bundle $b, int $q) => [['product_id' => 0, 'variant_id' => 0, 'qty' => $q, 'bundle_id' => $b->id]];
});

function stockOf(Product $p): StockLevel
{
    return StockLevel::where('product_id', $p->id)->first();
}

function ownerActs(): void
{
    test()->actingAs(TenantAdmin::withoutGlobalScopes()->where('email', 'owner@egy.test')->first(), 'tenant');
}

// ------------------------------------------------------------------ money allocation

it('allocates the bundle price cent-exactly across components (Σ = price × qty, none negative)', function () {
    $r = app(BundleResolver::class);
    mt_srand(7);
    for ($i = 0; $i < 300; $i++) {
        $n = mt_rand(1, 5);
        $total = number_format(mt_rand(1, 500000) / 100, 2, '.', '');
        $w = [];
        for ($k = 0; $k < $n; $k++) {
            $w[] = (string) mt_rand(0, 90000);
        }
        $shares = $r->allocate($total, $w);
        $sum = array_reduce($shares, fn ($c, $s) => bcadd($c, $s, 2), '0');
        expect($sum)->toBe($total);
        foreach ($shares as $s) {
            expect(bccomp($s, '0', 2))->toBeGreaterThanOrEqual(0);
        }
        // a share over n units always splits into whole-cent unit prices that reproduce it exactly
        $units = mt_rand(1, 9);
        $rebuilt = '0.00';
        foreach ($r->splitUnits($shares[0], $units) as [$unit, $q]) {
            $rebuilt = bcadd($rebuilt, bcmul($unit, (string) $q, 2), 2);
            expect(strlen(substr(strrchr($unit, '.'), 1)))->toBe(2);
        }
        expect($rebuilt)->toBe($shares[0]);
    }
    expect($r->allocate('0.01', ['1', '1', '1']))->toBe(['0.00', '0.00', '0.01'])   // last component takes the remainder
        ->and($r->allocate('10.00', ['0', '0']))->toBe(['0.00', '10.00']);
});

it('expands a bundle line into component lines whose totals equal the bundle price x qty', function () {
    $b = ($this->bundle)('77.77');                       // regular 33.33 + 30 + 7 = 70.33 -> priced ABOVE regular on purpose
    foreach ([1, 2, 3, 7] as $qty) {
        $q = app(\App\Services\Orders\OrderQuoteService::class)->quote(($this->bl)($b, $qty), ($this->checkout)());
        $sum = '0';
        foreach ($q['lines'] as $l) {
            $sum = bcadd($sum, bcmul($l['unit_price'], (string) $l['qty'], 2), 2);
            expect($l['bundle_id'])->toBe($b->id)->and($l['flash'])->toBeNull();
        }
        expect($sum)->toBe(bcmul('77.77', (string) $qty, 2))->and((string) $q['totals']->subtotal)->toBe($sum);
        expect(array_sum(array_map(fn ($l) => $l['qty'], array_filter($q['lines'], fn ($l) => $l['product_id'] === $this->b->id))))->toBe(3 * $qty);
    }
});

it('never lets a flash sale touch bundle components', function () {
    $sale = \App\Models\FlashSale::create(['name' => ['ar' => 'x'], 'starts_at' => now()->subHour(), 'ends_at' => now()->addHour(), 'is_active' => true]);
    \App\Models\FlashSaleItem::create(['flash_sale_id' => $sale->id, 'product_id' => $this->a->id, 'product_variant_id' => 0, 'discount_type' => 'percent', 'value' => 50, 'qty_limit' => 5]);
    $b = ($this->bundle)('50.00', [[$this->a, 1], [$this->b, 1]]);
    $order = ($this->place)(($this->bl)($b, 2));
    expect($order->items->every(fn ($i) => $i->flash_sale_item_id === null))->toBeTrue()->and((string) $order->subtotal)->toBe('100.00');
    expect(\App\Models\FlashSaleItem::first()->sold)->toBe(0);
});

// ------------------------------------------------------------------ placement, totals, cogs, discount

it('places a bundle order with COGS/profit identical to its components', function () {
    $b = ($this->bundle)('80.00');
    $order = ($this->place)(($this->bl)($b, 3));
    $order->load('items');
    $unitCost = 10 + 3 * 4 + 3;                           // per bundle: a 10 + 3×b 4 + c 3 = 25
    expect((string) $order->subtotal)->toBe('240.00')->and((string) $order->cogs)->toBe(number_format(3 * $unitCost, 2, '.', ''));
    expect($order->items->every(fn ($i) => $i->bundle_id === $b->id && $i->bundle_name === 'Pack' && strlen($i->bundle_group) === 36))->toBeTrue()
        ->and($order->items->pluck('bundle_group')->unique())->toHaveCount(1);
    foreach ($order->items as $i) {
        expect((string) $i->line_subtotal)->toBe(bcmul((string) $i->unit_price, (string) $i->quantity, 2));
    }
    // checkout total = Σ component lines + shipping
    expect((string) $order->total)->toBe(bcadd(bcadd((string) $order->subtotal, (string) $order->shipping_net, 2), (string) $order->cod_fee, 2));
    // stock reserved per component
    expect(stockOf($this->a)->reserved)->toBe(3)->and(stockOf($this->b)->reserved)->toBe(9)->and(stockOf($this->c)->reserved)->toBe(3);

    $st = app(OrderStatusService::class);
    $st->change($order, S::CONFIRMED);
    $o = $order->fresh();
    $expected = bcadd(bcsub(bcsub((string) $o->subtotal, (string) $o->discount, 2), (string) $o->cogs, 2), bcsub((string) $o->shipping_net, (string) $o->shipping_cost, 2), 2);
    expect((string) $o->net_profit)->toBe($expected);
});

it('treats bundle components as ordinary lines for discount codes', function () {
    $b = ($this->bundle)('100.00', [[$this->a, 1], [$this->b, 1]]);                      // a share 76.92? proportional to 33.33:10.00
    $all = DiscountCode::create(['code' => 'ALL10', 'type' => 'percent', 'value' => 10, 'scope' => 'all', 'is_active' => true, 'min_order' => 0]);
    $order = ($this->place)(($this->bl)($b, 1), ['discountCode' => 'ALL10']);
    expect((string) $order->discount)->toBe('10.00')->and($order->items->sum(fn ($i) => (float) $i->line_discount))->toEqualWithDelta(10.0, 0.001);

    DiscountCode::create(['code' => 'ONLYA', 'type' => 'percent', 'value' => 50, 'scope' => 'products', 'product_ids' => [$this->a->id], 'is_active' => true, 'min_order' => 0]);
    $o2 = ($this->place)(($this->bl)($b, 1), ['discountCode' => 'ONLYA']);
    $lineA = $o2->items->firstWhere('product_id', $this->a->id);
    expect((string) $o2->discount)->toBe(bcdiv((string) $lineA->line_subtotal, '2', 2) === '0.00' ? '0.00' : number_format(round((float) $lineA->line_subtotal / 2, 2), 2, '.', ''))
        ->and($o2->items->where('product_id', $this->b->id)->sum(fn ($i) => (float) $i->line_discount))->toEqual(0.0);
});

// ------------------------------------------------------------------ returns

it('returns a single bundle component line partially (per-line money and stock)', function () {
    $b = ($this->bundle)('80.00');
    $order = ($this->place)(($this->bl)($b, 2));
    $st = app(OrderStatusService::class);
    foreach ([S::CONFIRMED, S::PROCESSING, S::SHIPPED, S::DELIVERED] as $to) {
        $st->change($order, $to);
    }
    $order->refresh()->load('items');
    $line = $order->items->first(fn ($i) => $i->product_id === $this->b->id);
    $onHandB = stockOf($this->b)->on_hand;
    $rs = app(ReturnService::class);
    $ret = $rs->request($order, ReturnRequestData::fromArray(['items' => [$line->id => ['quantity' => 1]], 'restock' => true]));
    expect((string) $ret->return_value)->toBe((string) $line->unit_price);
    $rs->approve($ret);
    expect(stockOf($this->b)->on_hand)->toBe($onHandB + 1)->and($line->fresh()->returned_qty)->toBe(1)
        ->and($order->items->where('id', '!=', $line->id)->every(fn ($i) => $i->fresh()->returned_qty === 0))->toBeTrue();
    expect($order->fresh()->status)->toBe(S::DELIVERED);   // other components still kept
});

// ------------------------------------------------------------------ stock

it('limits a bundle to min floor(available / qty_per) and refuses overselling at add-to-cart and placement', function () {
    $b = ($this->bundle)('80.00', [[$this->a, 2], [$this->b, 3]]);       // stock 20 / 20 -> min(10, 6) = 6
    $r = app(BundleResolver::class);
    expect($r->available($b))->toBe(6);
    $cart = app(CartService::class);
    $cart->addBundle($b->id, 6);
    expect(fn () => $cart->addBundle($b->id, 1))->toThrow(ValidationException::class);

    // stock disappears after the add -> placement (under lock) refuses
    $this->ledger->record($this->branch->id, $this->b->id, 0, T::ADJUSTMENT_OUT, 15);
    expect(fn () => ($this->place)(($this->bl)($b, 2)))->toThrow(ValidationException::class);
    expect(Order::count())->toBe(0)->and(stockOf($this->a)->reserved)->toBe(0);
    ($this->place)(($this->bl)($b, 1));                                    // 5 b left -> 1 bundle fits
    expect(stockOf($this->b)->reserved)->toBe(3);

    $this->tenant->update(['settings' => array_merge($this->tenant->settings ?? [], ['allow_backorder' => true])]);
    app()->instance('currentTenant', $this->tenant->refresh());
    expect($r->available($b))->toBeNull();
});

it('counts plain cart lines and other bundles when checking a bundle against stock', function () {
    $b = ($this->bundle)('50.00', [[$this->a, 1]]);
    $cart = app(CartService::class);
    $cart->add($this->a->id, 0, 15);
    expect(fn () => $cart->addBundle($b->id, 6))->toThrow(ValidationException::class);
    $cart->addBundle($b->id, 5);
    expect($cart->cart()->items()->where('bundle_id', $b->id)->first()->quantity)->toBe(5);
});

// ------------------------------------------------------------------ availability windows

it('refuses inactive, not-started, ended and component-unavailable bundles everywhere', function () {
    $cart = app(CartService::class);
    $cases = [
        'inactive' => ['is_active' => false],
        'future' => ['starts_at' => now()->addDay()],
        'ended' => ['ends_at' => now()->subMinute()],
    ];
    foreach ($cases as $name => $o) {
        $b = ($this->bundle)('50.00', [[$this->a, 1]], $o);
        expect(fn () => $cart->addBundle($b->id, 1))->toThrow(ValidationException::class);
        expect(fn () => ($this->place)(($this->bl)($b, 1)))->toThrow(ValidationException::class);
        $this->get("{$this->s}/bundle/{$b->slug}")->assertNotFound();
        expect(app(\App\Services\Storefront\BundleCatalog::class)->find($b->slug))->toBeNull();
    }
    $live = ($this->bundle)('50.00', [[$this->a, 1], [$this->b, 1]]);
    $this->get("{$this->s}/bundle/{$live->slug}")->assertOk()->assertSee('باقة');
    $this->get("{$this->s}/bundles")->assertOk();
    $this->get("{$this->s}")->assertOk();
    // a component listing switched off hides the bundle and blocks an existing cart line / order
    $cart->addBundle($live->id, 1);
    TenantProduct::where('product_id', $this->b->id)->update(['is_active' => false]);
    $this->get("{$this->s}/bundle/{$live->slug}")->assertNotFound();
    expect($cart->view()[0]['available'])->toBeFalse()->and(fn () => ($this->place)(($this->bl)($live, 1)))->toThrow(ValidationException::class);
    expect(app(BundleResolver::class)->available($live))->toBe(0);
});

// ------------------------------------------------------------------ tenant isolation

it('keeps bundles and their components inside the tenant', function () {
    $sa = Tenant::create(['slug' => 'sa', 'name' => 'KSA', 'country' => 'SA', 'currency_code' => 'SAR', 'currency_symbol' => 'SAR', 'currency_rate' => 1, 'default_locale' => 'ar', 'locales' => ['ar', 'en'], 'timezone' => 'Asia/Riyadh', 'is_active' => true]);
    $foreign = Bundle::withoutGlobalScopes()->create(['tenant_id' => $sa->id, 'name' => ['ar' => 'x'], 'slug' => 'foreign', 'price' => 10, 'is_active' => true]);
    BundleItem::withoutGlobalScopes()->create(['tenant_id' => $sa->id, 'bundle_id' => $foreign->id, 'product_id' => $this->a->id, 'product_variant_id' => 0, 'quantity' => 1]);
    expect(Bundle::find($foreign->id))->toBeNull();
    expect(fn () => app(CartService::class)->addBundle($foreign->id, 1))->toThrow(ValidationException::class);
    expect(fn () => ($this->place)(($this->bl)($foreign, 1)))->toThrow(ValidationException::class);
    $this->get("{$this->s}/bundle/foreign")->assertNotFound();
    $this->getJson("{$this->api}/bundles/foreign")->assertNotFound();

    // a component that the tenant has NOT listed cannot be added by the admin form
    $other = Product::create(['type' => 'simple', 'name' => ['ar' => 'غريب'], 'slug' => 'unlisted', 'sku' => 'UNL']);
    ownerActs();
    $this->postJson("{$this->t}/bundles/validate", ['name' => ['ar' => 'ب'], 'price' => '10', 'items' => [['item' => $other->id.'-0', 'quantity' => 1]]])
        ->assertStatus(422)->assertJsonValidationErrors(['items.0.item']);
    $this->get("{$this->t}/bundles/{$foreign->id}/edit")->assertNotFound();
    $this->deleteJson("{$this->t}/bundles/{$foreign->id}")->assertNotFound();
    expect(Bundle::withoutGlobalScopes()->find($foreign->id))->not->toBeNull();
});

// ------------------------------------------------------------------ cart

it('merges a guest bundle cart into the customer cart on login', function () {
    $b = ($this->bundle)('80.00', [[$this->a, 1]]);
    $cart = app(CartService::class);
    $cart->addBundle($b->id, 2);                         // guest
    $cart->add($this->c->id, 0, 1);
    $customer = Customer::where('email', 'customer@egy.test')->first();
    $mine = \App\Models\Cart::create(['customer_id' => $customer->id]);
    $mine->items()->create(['bundle_id' => $b->id, 'product_id' => null, 'product_variant_id' => 0, 'quantity' => 1]);
    $this->actingAs($customer, 'web');
    $merged = $cart->cart();
    expect($merged->id)->toBe($mine->id);
    $rows = $merged->items()->get();
    expect($rows)->toHaveCount(2)->and($rows->firstWhere('bundle_id', $b->id)->quantity)->toBe(3)->and($rows->firstWhere('bundle_id', 0)->product_id)->toBe($this->c->id);
    $view = $cart->view();
    expect(collect($view)->firstWhere('is_bundle', true)['components'])->toHaveCount(1);
});

it('adds bundles to the web cart and shows the bundle row with components', function () {
    $b = ($this->bundle)('80.00');
    $this->postJson("{$this->s}/cart/items", ['bundle_id' => $b->id, 'quantity' => 2])->assertOk()
        ->assertJsonPath('data.items.0.is_bundle', true)->assertJsonPath('data.items.0.name', 'باقة')->assertJsonPath('data.items.0.unit_price', '80.00')
        ->assertJsonPath('data.totals.subtotal', '160.00');
    $this->postJson("{$this->s}/cart/items", ['bundle_id' => $b->id, 'quantity' => 999])->assertStatus(422);
    $item = $this->getJson("{$this->s}/cart")->json('data.items.0');
    expect($item['components'])->toHaveCount(3);
    $this->putJson("{$this->s}/cart/items/{$item['id']}", ['quantity' => 3])->assertOk()->assertJsonPath('data.totals.subtotal', '240.00');
    $this->deleteJson("{$this->s}/cart/items/{$item['id']}")->assertOk()->assertJsonPath('data.items', []);
});

// ------------------------------------------------------------------ pages, API, order pages

it('serves the same bundle data through the mobile API and shows grouped order lines everywhere', function () {
    $b = ($this->bundle)('80.00');
    $this->getJson("{$this->api}/bundles")->assertOk()->assertJsonPath('data.0.id', $b->id)->assertJsonPath('data.0.price', '80.00')->assertJsonPath('data.0.in_stock', true)
        ->assertJsonCount(3, 'data.0.components');
    $this->getJson("{$this->api}/bundles/{$b->slug}")->assertOk()->assertJsonPath('data.regular_total', '70.33')->assertJsonPath('data.savings', '0.00');
    $res = $this->postJson("{$this->api}/cart/items", ['bundle_id' => $b->id, 'quantity' => 2])->assertSuccessful()->assertJsonPath('data.items.0.is_bundle', true)->assertJsonPath('data.items.0.bundle_id', $b->id);
    $h = ['X-Cart-Token' => $res->headers->get('X-Cart-Token')];
    $this->getJson("{$this->api}/cart", $h)->assertJsonPath('data.totals.subtotal', '160.00')->assertJsonPath('data.items.0.quantity', 2);
    $payload = ['name' => 'Sara', 'phone' => '01100000111', 'city_id' => $this->city->id, 'address' => 'St', 'shipping_company_id' => $this->company->id, 'payment_method_id' => $this->cod->id];
    $placed = $this->postJson("{$this->api}/checkout", $payload, $h)->assertSuccessful();
    $order = Order::where('number', $placed->json('data.number'))->first();
    expect((string) $order->subtotal)->toBe('160.00')->and($order->items)->not->toBeEmpty();
    $show = $this->getJson("{$this->api}/orders/{$order->public_token}")->assertOk();
    expect(collect($show->json('data.items'))->every(fn ($i) => $i['bundle_name'] === 'Pack' && $i['bundle_id'] === $b->id))->toBeTrue();
    $this->getJson("{$this->api}/cart", $h)->assertJsonPath('data.items', []);

    $this->get("{$this->s}/order/{$order->public_token}")->assertOk()->assertSee('Pack');
    ownerActs();
    $this->get("{$this->t}/orders/{$order->id}")->assertOk()->assertSee('Pack');
    $this->get("{$this->t}/orders/{$order->id}/invoice")->assertOk()->assertSee('Pack');
    $this->get("{$this->t}/orders/{$order->id}/invoice.pdf")->assertOk();
});

// ------------------------------------------------------------------ admin CRUD + permissions

it('lets an authorised admin create, edit and delete bundles and rejects bad input', function () {
    ownerActs();
    $this->get("{$this->t}/bundles")->assertOk();
    $this->get("{$this->t}/bundles/create")->assertOk();
    $good = ['name' => ['ar' => 'باقة جديدة', 'en' => 'New pack'], 'price' => '55.50', 'is_active' => 1, 'sort_order' => 1,
        'items' => [['item' => $this->a->id.'-0', 'quantity' => 1], ['item' => $this->a->id.'-0', 'quantity' => 2], ['item' => $this->b->id.'-0', 'quantity' => 1]]];
    $this->postJson("{$this->t}/bundles/validate", ['price' => '0'] + $good)->assertStatus(422)->assertJsonValidationErrors(['price']);
    $this->postJson("{$this->t}/bundles/validate", ['items' => []] + $good)->assertStatus(422)->assertJsonValidationErrors(['items']);
    $this->postJson("{$this->t}/bundles/validate", ['starts_at' => '2030-01-02', 'ends_at' => '2030-01-01'] + $good)->assertStatus(422)->assertJsonValidationErrors(['ends_at']);
    $this->postJson("{$this->t}/bundles", $good)->assertOk();
    $bundle = Bundle::where('price', 55.50)->first();
    expect($bundle->slug)->not->toBe('')->and($bundle->items)->toHaveCount(2)->and($bundle->items->firstWhere('product_id', $this->a->id)->quantity)->toBe(3);   // duplicates merged
    $this->get("{$this->t}/bundles/datatable?draw=1&start=0&length=10")->assertOk()->assertJsonPath('recordsTotal', 1);
    $this->get("{$this->t}/bundles/{$bundle->id}/edit")->assertOk();
    $this->putJson("{$this->t}/bundles/{$bundle->id}", ['price' => '60', 'items' => [['item' => $this->c->id.'-0', 'quantity' => 2]]] + $good)->assertOk();
    $bundle->refresh()->load('items');
    expect((string) $bundle->price)->toBe('60.00')->and($bundle->items->count())->toBe(1)->and($bundle->items->first()->product_id)->toBe($this->c->id);
    $this->deleteJson("{$this->t}/bundles/{$bundle->id}")->assertOk();
    expect(Bundle::count())->toBe(0)->and(Bundle::withTrashed()->count())->toBe(1);
    $this->postJson("{$this->t}/bundles", $good)->assertOk();                   // slug freed by the soft delete (tombstoned)
});

it('enforces bundles.* permissions', function () {
    $role = Role::create(['name' => 't'.$this->tenant->id.':viewer', 'guard_name' => 'tenant']);
    $role->givePermissionTo('bundles.view');
    $u = TenantAdmin::withoutGlobalScopes()->create(['tenant_id' => $this->tenant->id, 'name' => 'V', 'email' => 'v@egy.test', 'password' => 'password', 'is_active' => true]);
    $u->assignRole($role);
    $b = ($this->bundle)('50.00', [[$this->a, 1]]);
    $this->actingAs($u, 'tenant');
    $this->get("{$this->t}/bundles")->assertOk();
    $this->get("{$this->t}/bundles/create")->assertForbidden();
    $this->postJson("{$this->t}/bundles", ['name' => ['ar' => 'x'], 'price' => 5, 'items' => [['item' => $this->a->id.'-0', 'quantity' => 1]]])->assertForbidden();
    $this->putJson("{$this->t}/bundles/{$b->id}", [])->assertForbidden();
    $this->deleteJson("{$this->t}/bundles/{$b->id}")->assertForbidden();
    expect(Bundle::count())->toBe(1);
    $this->actingAs(TenantAdmin::withoutGlobalScopes()->where('email', 'owner@egy.test')->first(), 'tenant');
    $this->get("{$this->t}/bundles/create")->assertOk();
});

// ------------------------------------------------------------------ retention

it('keeps soft-deleted bundles referenced by order lines and purges unreferenced ones', function () {
    $kept = ($this->bundle)('50.00', [[$this->a, 1]]);
    $free = ($this->bundle)('50.00', [[$this->b, 1]]);
    ($this->place)(($this->bl)($kept, 1));
    $kept->delete();
    $free->delete();
    Bundle::withTrashed()->whereIn('id', [$kept->id, $free->id])->update(['deleted_at' => now()->subDays(90)]);
    $res = app(SoftDeletePurger::class)->run(60, false, ['bundle']);
    expect($res['bundle']['purged'])->toBe(1)->and($res['bundle']['retained'])->toBe(1);
    expect(Bundle::withoutGlobalScopes()->withTrashed()->find($kept->id))->not->toBeNull()->and(Bundle::withoutGlobalScopes()->withTrashed()->find($free->id))->toBeNull();
});
