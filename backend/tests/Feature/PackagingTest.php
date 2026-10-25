<?php

use App\DTOs\Orders\CheckoutData;
use App\DTOs\Orders\ReturnRequestData;
use App\Enums\OrderStatus as S;
use App\Enums\StockMovementType as T;
use App\Models\Branch;
use App\Models\Cart;
use App\Models\City;
use App\Models\Customer;
use App\Models\Order;
use App\Models\PackagingOption;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ShippingCompany;
use App\Models\Tenant;
use App\Models\TenantAdmin;
use App\Models\TenantProduct;
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
    $this->city = City::first();
    $this->company = ShippingCompany::first();      // price 40, cost 30
    $this->cod = PaymentMethod::where('type', 'cod')->first();
    $this->p = Product::create(['type' => 'simple', 'name' => ['ar' => 'منتج', 'en' => 'Prod'], 'slug' => 'pk1', 'sku' => 'PK1', 'barcode' => app(\App\Services\BarcodeService::class)->generateUnique()]);
    TenantProduct::create(['product_id' => $this->p->id, 'price' => 100, 'cost' => 60, 'is_active' => true]);
    app(StockLedger::class)->record(Branch::first()->id, $this->p->id, 0, T::PURCHASE_IN, 50, '60.00');
    $this->opt = PackagingOption::create(['name' => ['ar' => 'هدية', 'en' => 'Gift box'], 'price' => 25, 'cost' => 8, 'is_active' => true]);
    $this->lines = [['product_id' => $this->p->id, 'variant_id' => 0, 'qty' => 2, 'bundle_id' => 0]];
    $this->place = fn (array $o = []) => app(OrderPlacementService::class)->place(new CheckoutData(...array_merge([
        'name' => 'Ali', 'phone' => '0100', 'cityId' => $this->city->id, 'address' => 'St', 'shippingCompanyId' => $this->company->id, 'paymentMethodId' => $this->cod->id,
        'packagingOptionId' => $this->opt->id,
    ], $o)), $this->lines)['order'];
    $this->deliver = function (Order $o) {
        foreach ([S::CONFIRMED, S::PROCESSING, S::SHIPPED, S::DELIVERED] as $to) {
            app(OrderStatusService::class)->change($o, $to);
        }

        return $o->refresh();
    };
    $this->owner = fn () => $this->actingAs(TenantAdmin::withoutGlobalScopes()->where('email', 'owner@egy.test')->first(), 'tenant');
});

it('snapshots the option on the order with totals, profit and no discount on packaging', function () {
    $o = ($this->place)();
    // subtotal 200, shipping 40, packaging 25 -> total 265
    expect((string) $o->subtotal)->toBe('200.00')->and((string) $o->packaging_fee)->toBe('25.00')->and((string) $o->packaging_cost)->toBe('8.00')
        ->and($o->packaging_option_id)->toBe($this->opt->id)->and($o->packagingLabel())->toBe('Gift box')->and((string) $o->total)->toBe('265.00')
        ->and((string) $o->cod_amount)->toBe('265.00');
    $this->opt->update(['price' => 99, 'name' => ['ar' => 'غيّر']]);          // later edits never touch the snapshot
    expect((string) $o->fresh()->packaging_fee)->toBe('25.00')->and($o->fresh()->packagingLabel())->toBe('Gift box');
    $o = ($this->deliver)($o);
    expect((string) $o->net_profit)->toBe('107.00');   // (200-120) + 40 - 30 + (25 - 8)
});

it('refuses inactive, foreign and unknown packaging options (422)', function () {
    $this->opt->update(['is_active' => false]);
    expect(fn () => ($this->place)())->toThrow(ValidationException::class);
    $sa = Tenant::create(['slug' => 'sa', 'name' => 'KSA', 'country' => 'SA', 'currency_code' => 'SAR', 'currency_symbol' => 'SAR', 'currency_rate' => 1, 'default_locale' => 'ar', 'locales' => ['ar', 'en'], 'timezone' => 'Asia/Riyadh', 'is_active' => true]);
    $foreign = PackagingOption::withoutGlobalScopes()->create(['tenant_id' => $sa->id, 'name' => ['ar' => 'x'], 'price' => 1, 'is_active' => true]);
    expect(fn () => ($this->place)(['packagingOptionId' => $foreign->id]))->toThrow(ValidationException::class);
    expect(fn () => ($this->place)(['packagingOptionId' => 99999]))->toThrow(ValidationException::class);
    expect(Order::count())->toBe(0);
    // HTTP layer
    $res = $this->postJson("{$this->api}/cart/items", ['product_id' => $this->p->id, 'quantity' => 1])->assertSuccessful();
    $h = ['X-Cart-Token' => $res->headers->get('X-Cart-Token')];
    $this->postJson("{$this->api}/cart/quote", ['packaging_option_id' => $this->opt->id], $h)->assertStatus(422)->assertJsonValidationErrors('packaging_option_id');
    $this->postJson("{$this->api}/cart/quote", ['packaging_option_id' => $foreign->id], $h)->assertStatus(422);
});

it('serves identical totals through quote, placement, invoice, admin and the mobile API (never the cost)', function () {
    $this->tenant->update(['settings' => array_merge($this->tenant->settings ?? [], ['tax_rate' => '14'])]);
    $this->getJson("{$this->api}/checkout/packaging")->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.price', '25.00')->assertJsonMissingPath('data.0.cost');
    $res = $this->postJson("{$this->api}/cart/items", ['product_id' => $this->p->id, 'quantity' => 2])->assertSuccessful();
    $h = ['X-Cart-Token' => $res->headers->get('X-Cart-Token')];
    $body = ['city_id' => $this->city->id, 'shipping_company_id' => $this->company->id, 'payment_method_id' => $this->cod->id, 'packaging_option_id' => $this->opt->id];
    $q = $this->postJson("{$this->api}/cart/quote", $body, $h)->assertOk()->assertJsonPath('data.totals.packaging_fee', '25.00')->assertJsonPath('data.packaging.id', $this->opt->id);
    expect(json_encode($q->json()))->not->toContain('"cost"');
    // 200 + 40 + tax (200*14% = 28) + 25 + 3.50 = 296.50 ; shown tax = 28 + 3.50
    $q->assertJsonPath('data.totals.total', '296.50')->assertJsonPath('data.totals.tax', '31.50');
    $this->getJson("{$this->api}/cart", $h)->assertJsonPath('data.totals.packaging_fee', '25.00');   // choice persisted on the cart
    $placed = $this->postJson("{$this->api}/checkout", ['name' => 'Sara', 'phone' => '0111', 'address' => 'St'] + $body, $h)->assertSuccessful();
    $order = Order::where('number', $placed->json('data.number'))->first();
    expect((string) $order->total)->toBe('296.50')->and((string) $order->packaging_tax)->toBe('3.50')->and((string) $order->tax)->toBe('28.00');
    $this->getJson("{$this->api}/orders/{$order->public_token}")->assertOk()->assertJsonPath('data.total', '296.50')->assertJsonPath('data.packaging_fee', '25.00')
        ->assertJsonPath('data.packaging_name', 'Gift box')->assertJsonPath('data.tax', '31.50')->assertJsonMissingPath('data.packaging_cost');
    $this->get("{$this->s}/order/{$order->public_token}")->assertOk()->assertSee('هدية')->assertSee('25.00');
    ($this->owner)();
    $this->get("{$this->t}/orders/{$order->id}")->assertOk()->assertSee('هدية');
    $this->get("{$this->t}/orders/{$order->id}/invoice")->assertOk()->assertSee('هدية')->assertSee('296.50');
    $this->get("{$this->t}/orders/{$order->id}/invoice.pdf")->assertOk();
});

it('does not refund packaging on a partial return, but refunds it on cancel before shipping', function () {
    $o = ($this->place)(['paymentMethodId' => $this->cod->id]);
    $o->update(['paid_amount' => $o->total]);                                   // customer paid everything
    $o = ($this->deliver)($o)->load('items');
    $rs = app(ReturnService::class);
    $line = $o->items->first();
    $ret = $rs->request($o, ReturnRequestData::fromArray(['items' => [$line->id => ['quantity' => 2]], 'restock' => true]));
    $rs->approve($ret);
    expect((string) $ret->fresh()->return_value)->toBe('200.00')
        ->and($rs->refundable($ret->fresh()))->toBe('200.00');                  // goods only: no packaging, no shipping
    $o2 = ($this->place)();
    $o2->update(['paid_amount' => $o2->total]);
    app(OrderStatusService::class)->change($o2, S::CONFIRMED);
    app(OrderStatusService::class)->change($o2, S::CANCELLED);
    expect((string) $o2->fresh()->refunded_amount)->toBe('265.00');             // whole paid money incl. packaging
});

it('keeps packaging out of an inclusive order partial-return refund and reverses only goods', function () {
    $this->tenant->update(['settings' => array_merge($this->tenant->settings ?? [], ['tax_rate' => '14', 'prices_include_tax' => true])]);
    $o = ($this->place)();                                                      // total = 200 + 40 + 25 = 265, VAT inside
    expect((string) $o->total)->toBe('265.00')->and($o->taxIncluded())->toBeTrue();
    $o->update(['paid_amount' => $o->total]);
    $o = ($this->deliver)($o)->load('items');
    // profit excludes the VAT inside goods (200*14/114) and inside the packaging (25*14/114)
    $vatGoods = (string) $o->tax; $vatPack = (string) $o->packaging_tax;
    expect(bcadd($vatGoods, $vatPack, 2))->not->toBe('0.00');
    // net = (200 - vat) - 120 + 40 - 30 + (25 - vatPack - 8)
    $net = bcadd(bcadd(bcsub(bcsub('200', $vatGoods, 2), '120', 2), '10', 2), bcsub(bcsub('25', $vatPack, 2), '8', 2), 2);
    expect((string) $o->net_profit)->toBe($net);
    $rs = app(ReturnService::class);
    $ret = $rs->request($o, ReturnRequestData::fromArray(['items' => [$o->items->first()->id => ['quantity' => 2]], 'restock' => true]));
    $rs->approve($ret);
    expect($rs->refundable($ret->fresh()))->toBe('200.00');                     // inclusive: return_value only
});

it('persists the choice on the cart and merges a guest choice into the customer cart', function () {
    $cart = app(CartService::class);
    $cart->add($this->p->id, 0, 1);
    $cart->setPackaging($this->opt->id);
    expect($cart->packagingOptionId())->toBe($this->opt->id);
    expect(fn () => $cart->setPackaging(424242))->toThrow(ValidationException::class);
    $customer = Customer::where('email', 'customer@egy.test')->first();
    $mine = Cart::create(['customer_id' => $customer->id]);
    $mine->items()->create(['bundle_id' => 0, 'product_id' => $this->p->id, 'product_variant_id' => 0, 'quantity' => 1]);
    $this->actingAs($customer, 'web');
    $merged = $cart->cart();
    expect($merged->id)->toBe($mine->id)->and($merged->fresh()->packaging_option_id)->toBe($this->opt->id);
    $this->opt->update(['is_active' => false]);                                  // stale choice is ignored, not fatal
    expect($cart->packagingOptionId())->toBeNull();
    $this->opt->update(['is_active' => true]);
    $cart->setPackaging(null);
    expect($cart->packagingOptionId())->toBeNull();
});

it('shows the packaging picker in the storefront cart and checkout pages', function () {
    $this->post("{$this->s}/cart/items", ['product_id' => $this->p->id, 'quantity' => 1], ['Accept' => 'application/json'])->assertSuccessful();
    $this->get("{$this->s}/cart")->assertOk()->assertSee('data-packaging', false)->assertSee('هدية');
    $this->get("{$this->s}/checkout")->assertOk()->assertSee('name="packaging_option_id"', false);
    $this->opt->update(['is_active' => false]);
    $this->get("{$this->s}/cart")->assertOk()->assertDontSee('data-packaging-toggle', false);
});

it('lets an authorised admin manage packaging options and rejects bad input', function () {
    ($this->owner)();
    $this->get("{$this->t}/packaging")->assertOk();
    $this->get("{$this->t}/packaging/create")->assertOk();
    $good = ['name' => ['ar' => 'علبة', 'en' => 'Box'], 'price' => '12.50', 'cost' => '3', 'is_active' => 1, 'sort_order' => 2];
    $this->postJson("{$this->t}/packaging/validate", ['price' => '-1'] + $good)->assertStatus(422)->assertJsonValidationErrors('price');
    $this->postJson("{$this->t}/packaging/validate", ['name' => []] + $good)->assertStatus(422);
    $this->postJson("{$this->t}/packaging", $good + ['image' => \Illuminate\Http\UploadedFile::fake()->image('p.png')])->assertOk();
    $o = PackagingOption::where('price', 12.50)->first();
    expect($o->image)->not->toBeNull();
    $old = $o->image;
    $this->putJson("{$this->t}/packaging/{$o->id}", ['price' => '15', 'remove_image' => 1] + $good)->assertOk();
    expect((string) $o->fresh()->price)->toBe('15.00')->and($o->fresh()->image)->toBeNull();
    Storage::disk('media')->assertMissing($old);
    $this->get("{$this->t}/packaging/datatable?draw=1&start=0&length=10")->assertOk()->assertJsonPath('recordsTotal', 2);
    $this->deleteJson("{$this->t}/packaging/{$o->id}")->assertOk();
    expect(PackagingOption::count())->toBe(1)->and(PackagingOption::withTrashed()->count())->toBe(2);
});

it('enforces packaging.* permissions', function () {
    $role = Role::create(['name' => 't'.$this->tenant->id.':viewer', 'guard_name' => 'tenant']);
    $role->givePermissionTo('packaging.view');
    $u = TenantAdmin::withoutGlobalScopes()->create(['tenant_id' => $this->tenant->id, 'name' => 'V', 'email' => 'v@egy.test', 'password' => 'password', 'is_active' => true]);
    $u->assignRole($role);
    $this->actingAs($u, 'tenant');
    $this->get("{$this->t}/packaging")->assertOk();
    $this->get("{$this->t}/packaging/create")->assertForbidden();
    $this->postJson("{$this->t}/packaging", ['name' => ['ar' => 'x'], 'price' => 1])->assertForbidden();
    $this->putJson("{$this->t}/packaging/{$this->opt->id}", [])->assertForbidden();
    $this->deleteJson("{$this->t}/packaging/{$this->opt->id}")->assertForbidden();
    expect(PackagingOption::count())->toBe(1);
});

it('adds a packaging_fee column/card to the sales report and a line to net profit', function () {
    $o = ($this->deliver)(($this->place)());
    ($this->owner)();
    $sales = $this->get("{$this->t}/reports/sales")->assertOk()->viewData('data');
    expect(collect($sales['columns'])->pluck('data')->all())->toContain('packaging_fee');
    expect(collect($sales['cards'])->firstWhere('label', __('reports.card.packaging_fee'))['value'])->toBe('25.00');
    $csv = $this->get("{$this->t}/reports/sales/export?from=2020-01-01")->assertOk()->getContent();
    expect($csv)->toContain(__('reports.col.packaging_fee'));
    $np = $this->get("{$this->t}/reports/net-profit")->assertOk()->viewData('data');
    $line = collect($np['rows'])->firstWhere('line', __('reports.line.packaging'));
    expect($line['amount'])->toBe('17.00');                                     // 25 - 8
    expect(collect($np['cards'])->firstWhere('label', __('reports.card.net_profit'))['value'])->toBe(number_format((float) $o->net_profit, 2));
});

it('keeps soft-deleted options referenced by orders and purges unreferenced ones', function () {
    $kept = $this->opt;
    ($this->place)();
    $free = PackagingOption::create(['name' => ['ar' => 'ح'], 'price' => 1, 'is_active' => true]);
    $kept->delete();
    $free->delete();
    PackagingOption::withTrashed()->whereIn('id', [$kept->id, $free->id])->update(['deleted_at' => now()->subDays(90)]);
    $res = app(SoftDeletePurger::class)->run(60, false, ['packaging_option']);
    expect($res['packaging_option']['purged'])->toBe(1)->and($res['packaging_option']['retained'])->toBe(1);
    expect(PackagingOption::withoutGlobalScopes()->withTrashed()->find($kept->id))->not->toBeNull()->and(PackagingOption::withoutGlobalScopes()->withTrashed()->find($free->id))->toBeNull();
});
