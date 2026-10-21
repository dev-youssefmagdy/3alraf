<?php

use App\Enums\PaymentProvider;
use App\Enums\StockMovementType as T;
use App\Models\Branch;
use App\Models\City;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\TenantAdmin;
use App\Models\TenantProduct;
use App\Services\Payments\PaymentGatewayFactory;
use App\Services\Stock\StockLedger;
use Database\Seeders\FoundationSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(FoundationSeeder::class);
    Storage::fake('public');
    $this->tenant = Tenant::where('slug', 'egy')->first();
    $this->t = 'http://egy.3alraf.test/admin';
    app()->instance('currentTenant', $this->tenant);
    $this->branch = Branch::first();
    $this->product = Product::create(['type' => 'simple', 'name' => ['ar' => 'منتج'], 'slug' => 'p1', 'sku' => 'P1', 'barcode' => '2000000000008']);
    TenantProduct::create(['product_id' => $this->product->id, 'price' => 100, 'cost' => 0, 'is_active' => true]);
    $this->ledger = app(StockLedger::class);
});

function actAsEgy(): void
{
    test()->actingAs(TenantAdmin::withoutGlobalScopes()->where('email', 'owner@egy.test')->first(), 'tenant');
}

it('keeps weighted average cost and an exact ledger', function () {
    $b = $this->branch->id;
    $p = $this->product->id;
    $this->ledger->record($b, $p, 0, T::PURCHASE_IN, 10, '10.00');
    expect($this->ledger->avgCost($b, $p))->toBe('10.00');
    $this->ledger->record($b, $p, 0, T::PURCHASE_IN, 10, '20.00');          // (100+200)/20 = 15
    expect($this->ledger->avgCost($b, $p))->toBe('15.00')->and($this->ledger->onHand($b, $p))->toBe(20);

    $out = $this->ledger->record($b, $p, 0, T::SALE_OUT, 10);              // valued at avg, avg unchanged
    expect((string) $out->unit_cost)->toBe('15.00')->and($out->quantity)->toBe(-10)->and($out->balance_after)->toBe(10)->and($this->ledger->avgCost($b, $p))->toBe('15.00');

    $this->ledger->record($b, $p, 0, T::PURCHASE_IN, 10, '25.00');          // (10*15+10*25)/20 = 20
    expect($this->ledger->avgCost($b, $p))->toBe('20.00');

    // half-up rounding: (3*10 + 4*10.33)/7 = 10.188571 -> 10.19
    expect($this->ledger->weightedAverage(3, '10.00', 4, '10.33'))->toBe('10.19');

    // invariant: on_hand == sum of signed movements
    expect((int) StockMovement::where('product_id', $p)->sum('quantity'))->toBe(StockLevel::where('product_id', $p)->value('on_hand'))->toBe(20);

    // tenant listing cost follows the weighted average of purchases
    expect((string) TenantProduct::where('product_id', $p)->value('cost'))->toBe('20.00');
});

it('refuses negative stock unless backorder allowed and resets average when empty', function () {
    $b = $this->branch->id;
    $p = $this->product->id;
    $this->ledger->record($b, $p, 0, T::PURCHASE_IN, 5, '8.00');
    expect(fn () => $this->ledger->record($b, $p, 0, T::SALE_OUT, 6))->toThrow(ValidationException::class);
    expect($this->ledger->onHand($b, $p))->toBe(5)->and(StockMovement::count())->toBe(1);

    $this->ledger->record($b, $p, 0, T::SALE_OUT, 5);
    $this->ledger->record($b, $p, 0, T::PURCHASE_IN, 2, '12.00');           // empty shelf -> avg = new cost
    expect($this->ledger->avgCost($b, $p))->toBe('12.00');

    $this->tenant->update(['settings' => ['allow_backorder' => true]]);
    app()->instance('currentTenant', $this->tenant->fresh());
    $this->ledger->record($b, $p, 0, T::SALE_OUT, 5);
    expect($this->ledger->onHand($b, $p))->toBe(-3);
});

it('runs purchase, transfer and adjustment flows through the admin', function () {
    actAsEgy();
    $b2 = Branch::withoutGlobalScopes()->create(['tenant_id' => $this->tenant->id, 'name' => ['ar' => 'فرع 2'], 'is_active' => true]);
    $supplier = Supplier::first();
    $item = $this->product->id.'-0';

    // validation + creation received immediately
    $this->postJson("{$this->t}/purchases/validate", [])->assertStatus(422)->assertJsonValidationErrors(['supplier_id', 'items']);
    $this->postJson("{$this->t}/purchases/validate", ['supplier_id' => $supplier->id, 'branch_id' => $this->branch->id, 'ordered_at' => '2026-10-01', 'items' => [['item' => '999-0', 'quantity' => 1, 'unit_cost' => 1]]])->assertJsonValidationErrors(['items.0.item']);
    $this->postJson("{$this->t}/purchases", ['supplier_id' => $supplier->id, 'branch_id' => $this->branch->id, 'ordered_at' => '2026-10-01', 'shipping_cost' => 5, 'items' => [['item' => $item, 'quantity' => 10, 'unit_cost' => 10]]])->assertOk();
    $po = \App\Models\PurchaseOrder::first();
    expect($po->number)->toBe(sprintf('PO-%06d', $po->id))->and((string) $po->total_cost)->toBe('105.00')->and($po->isDraft())->toBeTrue()
        ->and($this->ledger->onHand($this->branch->id, $this->product->id))->toBe(0);
    $this->postJson("{$this->t}/purchases/{$po->id}/receive")->assertOk();
    $this->postJson("{$this->t}/purchases", ['supplier_id' => $supplier->id, 'branch_id' => $this->branch->id, 'ordered_at' => '2026-10-02', 'receive' => 1, 'items' => [['item' => $item, 'quantity' => 10, 'unit_cost' => 20]]])->assertOk();
    expect($this->ledger->onHand($this->branch->id, $this->product->id))->toBe(20)->and($this->ledger->avgCost($this->branch->id, $this->product->id))->toBe('15.00');
    $this->postJson("{$this->t}/purchases/{$po->id}/receive")->assertStatus(422); // locked
    $this->get("{$this->t}/purchases")->assertOk();
    $this->get("{$this->t}/purchases/{$po->id}")->assertOk()->assertSee($po->number);
    $this->get("{$this->t}/purchases/create")->assertOk();
    $this->getJson("{$this->t}/purchases/datatable?draw=1&start=0&length=10")->assertOk()->assertJsonPath('recordsTotal', 2);

    // transfer: draft -> in transit -> received, cost carried
    $this->postJson("{$this->t}/stock-transfers", ['from_branch_id' => $this->branch->id, 'to_branch_id' => $this->branch->id, 'items' => [['item' => $item, 'quantity' => 1]]])->assertJsonValidationErrors(['to_branch_id']);
    $this->postJson("{$this->t}/stock-transfers", ['from_branch_id' => $this->branch->id, 'to_branch_id' => $b2->id, 'items' => [['item' => $item, 'quantity' => 50]]])->assertOk();
    $tr = \App\Models\StockTransfer::first();
    $this->postJson("{$this->t}/stock-transfers/{$tr->id}/send")->assertStatus(422);           // insufficient
    expect($tr->fresh()->status->value)->toBe('draft')->and($this->ledger->onHand($this->branch->id, $this->product->id))->toBe(20);
    $this->putJson("{$this->t}/stock-transfers/{$tr->id}", ['from_branch_id' => $this->branch->id, 'to_branch_id' => $b2->id, 'items' => [['item' => $item, 'quantity' => 8]]])->assertOk();
    $this->postJson("{$this->t}/stock-transfers/{$tr->id}/send")->assertOk();
    expect($this->ledger->onHand($this->branch->id, $this->product->id))->toBe(12)->and($this->ledger->onHand($b2->id, $this->product->id))->toBe(0);
    $this->postJson("{$this->t}/stock-transfers/{$tr->id}/receive")->assertOk();
    expect($this->ledger->onHand($b2->id, $this->product->id))->toBe(8)->and($this->ledger->avgCost($b2->id, $this->product->id))->toBe('15.00')
        ->and($tr->fresh()->status->value)->toBe('received');
    $this->get("{$this->t}/stock-transfers/{$tr->id}")->assertOk();

    // adjustment
    $this->postJson("{$this->t}/stock-adjustments", ['branch_id' => $b2->id, 'type' => 'decrease', 'reason' => 'damaged', 'items' => [['item' => $item, 'quantity' => 3]]])->assertOk();
    $this->postJson("{$this->t}/stock-adjustments", ['branch_id' => $b2->id, 'type' => 'decrease', 'reason' => 'lost', 'items' => [['item' => $item, 'quantity' => 30]]])->assertStatus(422);
    $this->postJson("{$this->t}/stock-adjustments", ['branch_id' => $b2->id, 'type' => 'increase', 'reason' => 'found', 'items' => [['item' => $item, 'quantity' => 5, 'unit_cost' => 25]]])->assertOk();
    // (5*15 + 5*25)/10 = 20
    expect($this->ledger->onHand($b2->id, $this->product->id))->toBe(10)->and($this->ledger->avgCost($b2->id, $this->product->id))->toBe('20.00');
    $this->get("{$this->t}/stock-adjustments")->assertOk();
    $this->get("{$this->t}/stock-adjustments/create")->assertOk();

    // ledger/stock pages
    $this->get("{$this->t}/stock")->assertOk();
    $this->get("{$this->t}/stock/movements")->assertOk();
    $this->getJson("{$this->t}/stock/datatable?draw=1&start=0&length=10")->assertOk()->assertJsonPath('recordsTotal', 2);
    $this->getJson("{$this->t}/stock/movements/datatable?draw=1&start=0&length=50&type=purchase_in")->assertOk()->assertJsonPath('recordsFiltered', 2);
});

it('manages cities, branches, suppliers, shipping prices, expenses and banners with tenant isolation', function () {
    actAsEgy();
    $this->postJson("{$this->t}/cities/validate", [])->assertJsonValidationErrors(['name.ar']);
    $this->postJson("{$this->t}/cities", ['name' => ['ar' => 'أسوان', 'en' => 'Aswan'], 'is_active' => 1])->assertOk();
    $city = City::where('name->en', 'Aswan')->first();
    expect($city->tenant_id)->toBe($this->tenant->id);
    $this->get("{$this->t}/cities")->assertOk();
    $this->get("{$this->t}/cities/create")->assertOk();
    $this->get("{$this->t}/cities/{$city->id}/edit")->assertOk();

    // shipping company with per-city price list (+ duplicate city rejected)
    $dup = ['name' => ['ar' => 'ش'], 'rates' => [['city_id' => $city->id, 'price' => 30], ['city_id' => $city->id, 'price' => 31]]];
    $this->postJson("{$this->t}/shipping-companies/validate", $dup)->assertJsonValidationErrors(['rates.0.city_id']);
    $this->postJson("{$this->t}/shipping-companies", ['name' => ['ar' => 'بوسطة'], 'is_active' => 1, 'rates' => [['city_id' => $city->id, 'price' => 30, 'cost' => 22, 'free_over' => 500, 'eta_min_days' => 2, 'eta_max_days' => 4, 'supports_cod' => 1]]])->assertOk();
    $co = \App\Models\ShippingCompany::latest('id')->first();
    expect($co->rates)->toHaveCount(1)->and((string) $co->rates[0]->free_over)->toBe('500.00')->and($co->rates[0]->etaLabel())->toBe('2-4');
    $this->get("{$this->t}/shipping-companies/{$co->id}/edit")->assertOk();
    $this->putJson("{$this->t}/shipping-companies/{$co->id}", ['name' => ['ar' => 'بوسطة'], 'rates' => []])->assertOk();
    expect($co->rates()->count())->toBe(0);
    // city still referenced -> cannot delete
    $this->postJson("{$this->t}/shipping-companies/{$co->id}", ['_method' => 'PUT', 'name' => ['ar' => 'بوسطة'], 'rates' => [['city_id' => $city->id, 'price' => 1]]])->assertOk();
    $this->deleteJson("{$this->t}/cities/{$city->id}")->assertStatus(422);
    $this->getJson("{$this->t}/shipping-companies/datatable?draw=1&start=0&length=10")->assertOk()->assertJsonPath('recordsTotal', 2);

    // branches: single default
    $this->postJson("{$this->t}/branches", ['name' => ['ar' => 'فرع جديد'], 'city_id' => $city->id, 'is_default' => 1, 'is_active' => 1])->assertOk();
    expect(Branch::where('is_default', true)->count())->toBe(1)->and(Branch::where('is_default', true)->first()->name)->toBe('فرع جديد');
    $this->deleteJson("{$this->t}/branches/".Branch::where('is_default', true)->first()->id)->assertStatus(422);
    $this->get("{$this->t}/branches/create")->assertOk();

    $this->postJson("{$this->t}/suppliers", ['name' => 'Acme', 'email' => 'bad'])->assertJsonValidationErrors(['email']);
    $this->postJson("{$this->t}/suppliers", ['name' => 'Acme', 'email' => 'a@acme.test'])->assertOk();

    // expenses
    $this->postJson("{$this->t}/expense-categories", ['name' => ['ar' => 'صيانة']])->assertOk();
    $cat = \App\Models\ExpenseCategory::where('name->ar', 'صيانة')->first();
    $this->post("{$this->t}/expenses", ['expense_category_id' => $cat->id, 'amount' => '150.50', 'expense_date' => '2026-10-05', 'attachment' => UploadedFile::fake()->create('r.pdf', 20, 'application/pdf')], ['Accept' => 'application/json'])->assertOk();
    $exp = \App\Models\Expense::first();
    expect((string) $exp->amount)->toBe('150.50');
    Storage::disk('public')->assertExists($exp->attachment);
    $this->deleteJson("{$this->t}/expense-categories/{$cat->id}")->assertStatus(422);
    $this->get("{$this->t}/expenses/{$exp->id}/edit")->assertOk();
    $this->deleteJson("{$this->t}/expenses/{$exp->id}")->assertOk();
    Storage::disk('public')->assertMissing($exp->attachment);

    // banners: per-locale image + schedule validation
    $this->postJson("{$this->t}/banners/validate", ['placement' => 'home_hero'])->assertJsonValidationErrors(['images']);
    $this->post("{$this->t}/banners", ['placement' => 'home_hero', 'title' => ['ar' => 'عرض'], 'images' => ['ar' => UploadedFile::fake()->image('b.jpg', 800, 300)], 'starts_at' => '2026-10-02 10:00', 'ends_at' => '2026-10-01 10:00'], ['Accept' => 'application/json'])->assertJsonValidationErrors(['ends_at']);
    $this->post("{$this->t}/banners", ['placement' => 'home_hero', 'title' => ['ar' => 'عرض'], 'images' => ['ar' => UploadedFile::fake()->image('b.jpg', 800, 300)], 'is_active' => 1], ['Accept' => 'application/json'])->assertOk();
    $banner = \App\Models\Banner::first();
    Storage::disk('public')->assertExists($banner->images['ar']);
    expect(\App\Models\Banner::live()->count())->toBe(1)->and($banner->imageUrl('ar'))->not->toBeNull();
    $this->get("{$this->t}/banners/{$banner->id}/edit")->assertOk();
    $this->get("{$this->t}/banners")->assertOk();

    // isolation: another tenant cannot see/edit egy's records
    $sa = Tenant::create(['slug' => 'sa', 'name' => 'SA', 'country' => 'SA', 'currency_code' => 'SAR', 'currency_symbol' => 'SAR', 'currency_rate' => 1, 'default_locale' => 'ar', 'locales' => ['ar'], 'timezone' => 'Asia/Riyadh', 'is_active' => true]);
    $owner = TenantAdmin::withoutGlobalScopes()->create(['tenant_id' => $sa->id, 'name' => 'o', 'email' => 'o@sa.test', 'password' => 'password', 'is_active' => true]);
    $owner->syncRoles('tenant-owner');
    $this->actingAs($owner, 'tenant');
    $this->getJson('http://sa.3alraf.test/admin/cities/datatable?draw=1&start=0&length=10')->assertOk()->assertJsonPath('recordsTotal', 0);
    $this->getJson("http://sa.3alraf.test/admin/cities/{$city->id}/edit")->assertNotFound();
    $this->deleteJson("http://sa.3alraf.test/admin/banners/{$banner->id}")->assertNotFound();
    $this->postJson('http://sa.3alraf.test/admin/purchases/validate', ['supplier_id' => Supplier::withoutGlobalScopes()->first()->id])->assertJsonValidationErrors(['supplier_id']);
});

it('stores payment gateway credentials encrypted and routes webhooks through the factory', function () {
    actAsEgy();
    $this->postJson("{$this->t}/payment-methods/validate", ['type' => 'online', 'provider' => 'paymob', 'name' => ['ar' => 'x'], 'fee_percent' => 2, 'fee_fixed' => 1, 'fee_bearer' => 'customer'])
        ->assertJsonValidationErrors(['credentials.api_key', 'credentials.hmac_secret']);
    $this->postJson("{$this->t}/payment-methods/validate", ['type' => 'wallet', 'provider' => 'stripe', 'name' => ['ar' => 'x'], 'fee_percent' => 0, 'fee_fixed' => 0, 'fee_bearer' => 'merchant'])
        ->assertJsonValidationErrors(['provider', 'account_number']);

    $payload = ['type' => 'online', 'provider' => 'paymob', 'name' => ['ar' => 'بطاقة'], 'fee_percent' => '2.5', 'fee_fixed' => '1', 'fee_bearer' => 'customer', 'is_active' => 1, 'is_sandbox' => 1,
        'credentials' => ['api_key' => 'KEY-123', 'integration_id' => '77', 'iframe_id' => '5', 'hmac_secret' => 'topsecret']];
    $this->postJson("{$this->t}/payment-methods", $payload)->assertOk();
    $pm = PaymentMethod::where('provider', 'paymob')->latest('id')->first();
    expect($pm->credentials['api_key'])->toBe('KEY-123');
    $raw = DB::table('payment_methods')->where('id', $pm->id)->value('credentials');
    expect($raw)->not->toContain('KEY-123')->and($raw)->not->toContain('topsecret');

    // blank secret on edit keeps stored value
    $this->putJson("{$this->t}/payment-methods/{$pm->id}", ['credentials' => ['api_key' => '', 'integration_id' => '88', 'iframe_id' => '5', 'hmac_secret' => ''], 'fee_percent' => '3'] + $payload)->assertOk();
    $pm->refresh();
    expect($pm->credentials['hmac_secret'])->toBe('topsecret')->and($pm->credentials['integration_id'])->toBe('88')->and((string) $pm->fee_percent)->toBe('3.000');
    $this->get("{$this->t}/payment-methods/{$pm->id}/edit")->assertOk()->assertDontSee('topsecret');

    // fee math (CALCULATIONS.md step 4): 260 * 2.5% + 1 = 7.50
    $pm->forceFill(['fee_percent' => '2.5', 'fee_fixed' => '1'])->save();
    expect($pm->feeFor('260'))->toBe('7.50');

    // factory + webhook signature
    expect(app(PaymentGatewayFactory::class)->make($pm)->key())->toBe('paymob');
    expect(fn () => app(PaymentGatewayFactory::class)->make(PaymentMethod::where('provider', PaymentProvider::COD)->first()))->toThrow(InvalidArgumentException::class);
    $obj = ['id' => 991, 'amount_cents' => 10000, 'currency' => 'EGP', 'success' => true, 'pending' => false, 'order' => ['id' => 5, 'merchant_order_id' => 'ORD-UNKNOWN']];
    $body = json_encode(['type' => 'TRANSACTION', 'obj' => $obj]);
    $url = "http://egy.3alraf.test/webhooks/payments/{$pm->id}";
    $this->call('POST', $url.'?hmac=bad', [], [], [], ['CONTENT_TYPE' => 'application/json'], $body)->assertStatus(400);
    $good = \App\Services\Payments\Drivers\PaymobGateway::hmac($obj, 'topsecret');
    $this->call('POST', $url.'?hmac='.$good, [], [], [], ['CONTENT_TYPE' => 'application/json'], $body)->assertOk()->assertJson(['ignored' => true]); // unknown order: acked, nothing settled

    $this->get("{$this->t}/payment-methods")->assertOk();
    $this->get("{$this->t}/payment-methods/create")->assertOk();
    $this->deleteJson("{$this->t}/payment-methods/{$pm->id}")->assertOk();
});
