<?php

use App\DTOs\Orders\CheckoutData;
use App\Enums\OrderStatus as S;
use App\Enums\StockMovementType as T;
use App\Models\Branch;
use App\Models\City;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ShippingCompany;
use App\Models\Tenant;
use App\Models\TenantAdmin;
use App\Models\TenantProduct;
use App\Services\Orders\OrderPlacementService;
use App\Services\Orders\OrderStatusService;
use App\Services\Stock\StockLedger;
use Database\Seeders\FoundationSeeder;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Cache::flush();
    $this->seed(FoundationSeeder::class);
    $this->tenant = Tenant::where('slug', 'egy')->first();
    $this->t = 'http://egy.3alraf.test/admin';
    $this->s = 'http://egy.3alraf.test/ar';
    $this->api = 'http://egy.3alraf.test/api/v1';
    app()->instance('currentTenant', $this->tenant);
    $this->city = City::first();
    $this->company = ShippingCompany::first();                      // rate: price 40, cost 30
    $this->cod = PaymentMethod::where('type', 'cod')->first();
    $this->wallet = PaymentMethod::where('type', 'wallet')->first();
    $this->product = Product::create(['type' => 'simple', 'name' => ['ar' => 'منتج'], 'slug' => 'cf1', 'sku' => 'CF1', 'barcode' => '2000000000077']);
    TenantProduct::create(['product_id' => $this->product->id, 'price' => 100, 'cost' => 60, 'is_active' => true]);
    app(StockLedger::class)->record(Branch::first()->id, $this->product->id, 0, T::PURCHASE_IN, 50, '60.00');
    $this->setFee = fn (array $s) => $this->tenant->update(['settings' => array_merge($this->tenant->settings ?? [], ['cod_extra_fee_enabled' => true, 'cod_extra_fee_type' => 'fixed', 'cod_extra_fee_fixed' => '15'], $s)]);
    $this->place = fn (int $method, array $o = []) => app(OrderPlacementService::class)->place(new CheckoutData(...array_merge([
        'name' => 'Ali', 'phone' => '0100', 'cityId' => $this->city->id, 'address' => 'St', 'shippingCompanyId' => $this->company->id, 'paymentMethodId' => $method,
    ], $o)), [['product_id' => $this->product->id, 'variant_id' => 0, 'qty' => 2]])['order'];
    $this->owner = fn () => $this->actingAs(TenantAdmin::withoutGlobalScopes()->where('email', 'owner@egy.test')->first(), 'tenant');
});

$form = fn (array $o = []) => array_merge([
    'cod_extra_fee_enabled' => 1, 'cod_extra_fee_type' => 'fixed_plus_percent', 'cod_extra_fee_fixed' => '10', 'cod_extra_fee_percent' => '2',
    'cod_extra_fee_min' => '5', 'cod_extra_fee_max' => '50', 'cod_extra_fee_free_over' => '1000', 'cod_extra_fee_bearer' => 'customer',
    'cod_extra_fee_label' => ['ar' => 'رسوم COD', 'en' => 'Handling'],
], $o);

it('validates and saves COD fee settings, keeping other settings keys', function () use ($form) {
    $this->tenant->update(['settings' => ['tax_rate' => '14']]);
    ($this->owner)();
    $this->get("{$this->t}/cod-fees")->assertOk()->assertSee('cod_extra_fee_type', false);
    $this->postJson("{$this->t}/cod-fees/validate", $form(['cod_extra_fee_type' => 'bogus']))->assertStatus(422)->assertJsonValidationErrors('cod_extra_fee_type');
    $this->postJson("{$this->t}/cod-fees/validate", $form(['cod_extra_fee_percent' => '101']))->assertStatus(422)->assertJsonValidationErrors('cod_extra_fee_percent');
    $this->postJson("{$this->t}/cod-fees/validate", $form(['cod_extra_fee_fixed' => '-1']))->assertStatus(422)->assertJsonValidationErrors('cod_extra_fee_fixed');
    $this->postJson("{$this->t}/cod-fees/validate", $form(['cod_extra_fee_fixed' => '']))->assertStatus(422)->assertJsonValidationErrors('cod_extra_fee_fixed');
    $this->postJson("{$this->t}/cod-fees/validate", $form(['cod_extra_fee_min' => '60', 'cod_extra_fee_max' => '50']))->assertStatus(422)->assertJsonValidationErrors('cod_extra_fee_max');
    $this->postJson("{$this->t}/cod-fees/validate", $form(['cod_extra_fee_bearer' => 'carrier']))->assertStatus(422)->assertJsonValidationErrors('cod_extra_fee_bearer');
    $this->postJson("{$this->t}/cod-fees/validate", $form(['cod_extra_fee_city_fixed' => [$this->city->id => '-3']]))->assertStatus(422);
    $this->postJson("{$this->t}/cod-fees/validate", $form())->assertOk();

    $this->putJson("{$this->t}/cod-fees", $form(['cod_extra_fee_city_fixed' => [$this->city->id => '25', 99999 => '']]))->assertStatus(422); // unknown city key (empty value still a key)
    $this->putJson("{$this->t}/cod-fees", $form(['cod_extra_fee_city_fixed' => [$this->city->id => '25']]))->assertOk();
    $s = $this->tenant->fresh()->settings;
    expect($s['tax_rate'])->toBe('14')->and($s['cod_extra_fee_enabled'])->toBeTrue()->and($s['cod_extra_fee_type'])->toBe('fixed_plus_percent')
        ->and($s['cod_extra_fee_max'])->toBe('50')->and($s['cod_extra_fee_label']['en'])->toBe('Handling')->and($s['cod_extra_fee_city_fixed'][$this->city->id])->toBe('25');
});

it('guards COD fee settings by permission and tenant', function () use ($form) {
    $role = Role::create(['name' => 't'.$this->tenant->id.':viewer', 'guard_name' => 'tenant']);
    $role->givePermissionTo('settings.view');
    $u = TenantAdmin::withoutGlobalScopes()->create(['tenant_id' => $this->tenant->id, 'name' => 'V', 'email' => 'v@egy.test', 'password' => 'password', 'is_active' => true]);
    $u->assignRole($role);
    $this->actingAs($u, 'tenant');
    $this->get("{$this->t}/cod-fees")->assertOk();
    $this->putJson("{$this->t}/cod-fees", $form())->assertForbidden();
    $this->postJson("{$this->t}/cod-fees/validate", $form())->assertForbidden();
    expect($this->tenant->fresh()->settings['cod_extra_fee_enabled'] ?? false)->toBeFalse();

    // a city of ANOTHER tenant is not accepted, and saving never touches the other tenant's settings
    $sa = Tenant::create(['slug' => 'sa', 'name' => 'KSA', 'country' => 'SA', 'currency_code' => 'SAR', 'currency_symbol' => 'SAR', 'currency_rate' => 1, 'default_locale' => 'ar', 'locales' => ['ar', 'en'], 'timezone' => 'Asia/Riyadh', 'is_active' => true]);
    $other = City::withoutGlobalScopes()->create(['tenant_id' => $sa->id, 'name' => ['ar' => 'الرياض'], 'is_active' => true, 'sort_order' => 0]);
    ($this->owner)();
    $this->putJson("{$this->t}/cod-fees", $form(['cod_extra_fee_city_fixed' => [$other->id => '9']]))->assertStatus(422);
    $this->putJson("{$this->t}/cod-fees", $form())->assertOk();
    expect($sa->fresh()->settings['cod_extra_fee_enabled'] ?? null)->toBeNull();
    // tenants without a session of their own cannot reach it
    auth('tenant')->logout();
    $this->get("{$this->t}/cod-fees")->assertRedirect();
});

it('quote changes when switching COD vs online/wallet and re-quotes with the customer-safe fee only', function () {
    ($this->setFee)(['cod_extra_fee_label' => ['ar' => 'رسوم COD', 'en' => 'Handling']]);
    $this->withCredentials()->postJson("{$this->s}/cart/items", ['product_id' => $this->product->id, 'variant_id' => 0, 'quantity' => 2])->assertOk();
    $b = ['city_id' => $this->city->id, 'shipping_company_id' => $this->company->id];
    $cod = $this->withCredentials()->postJson("{$this->s}/cart/quote", $b + ['payment_method_id' => $this->cod->id])->assertOk()
        ->assertJsonPath('data.totals.codFee', '15.00')->assertJsonPath('data.totals.codFeeLabel', 'رسوم COD')->assertJsonPath('data.totals.total', '255.00');
    expect($cod->json('data.totals'))->not->toHaveKeys(['netProfit', 'cogs', 'shippingCost', 'affiliateCommission']);
    $this->withCredentials()->postJson("{$this->s}/cart/quote", $b + ['payment_method_id' => $this->wallet->id])->assertOk()
        ->assertJsonPath('data.totals.codFee', '0.00')->assertJsonPath('data.totals.total', '240.00');
    $this->withCredentials()->postJson("{$this->s}/cart/quote", $b)->assertOk()->assertJsonPath('data.totals.codFee', '0.00');
});

it('snapshots the fee on the order: total, cod_amount (collected by the carrier), label, profit and refund rules', function () {
    ($this->setFee)(['cod_extra_fee_label' => ['ar' => 'رسوم COD', 'en' => 'Handling'], 'cod_collect_shipping_upfront' => false]);
    app()->instance('currentTenant', $this->tenant->fresh());
    $o = ($this->place)($this->cod->id);
    expect((string) $o->cod_fee)->toBe('15.00')->and((string) $o->total)->toBe('255.00')->and((string) $o->cod_amount)->toBe('255.00')->and($o->cod_fee_label['en'])->toBe('Handling');
    // later settings changes never rewrite the snapshot
    ($this->setFee)(['cod_extra_fee_fixed' => '99']);
    app()->instance('currentTenant', $this->tenant->fresh());
    expect((string) $o->fresh()->cod_fee)->toBe('15.00');
    app(OrderStatusService::class)->change($o, S::CONFIRMED);
    $o->refresh();
    expect((string) $o->net_profit)->toBe('105.00');            // (200-120) + 40 - 30 + 15

    $w = ($this->place)($this->cod->id, []);                    // settings now 99 -> a fresh order uses them
    expect((string) $w->cod_fee)->toBe('99.00');
    ($this->setFee)(['cod_extra_fee_bearer' => 'merchant']);
    app()->instance('currentTenant', $this->tenant->fresh());
    $m = ($this->place)($this->cod->id);
    expect((string) $m->cod_fee)->toBe('0.00')->and($m->cod_fee_label)->toBeNull()->and((string) $m->total)->toBe('240.00');
    app(OrderStatusService::class)->change($m, S::CONFIRMED);
    expect((string) $m->refresh()->net_profit)->toBe('90.00');
    // online orders never carry it
    $wal = ($this->place)($this->wallet->id);
    expect((string) $wal->cod_fee)->toBe('0.00')->and((string) $wal->cod_amount)->toBe('0.00');
});

it('keeps the fee out of prepaid shipping when shipping is prepaid', function () {
    ($this->setFee)(['cod_collect_shipping_upfront' => true]);
    app()->instance('currentTenant', $this->tenant->fresh());
    $o = ($this->place)($this->cod->id, ['prepayMethodId' => $this->wallet->id]);
    expect((string) $o->prepaid_shipping)->toBe('40.00')->and((string) $o->cod_amount)->toBe('215.00')->and((string) $o->total)->toBe('255.00');
});

it('API quote/checkout/order resources expose the fee (parity with web) but no profit fields', function () {
    ($this->setFee)(['cod_extra_fee_label' => ['ar' => 'رسوم COD'], 'cod_collect_shipping_upfront' => false]);
    $h = ['X-Cart-Token' => $this->postJson("{$this->api}/cart/items", ['product_id' => $this->product->id, 'variant_id' => 0, 'quantity' => 2])->headers->get('X-Cart-Token')];
    $b = ['name' => 'Ali', 'phone' => '0100', 'city_id' => $this->city->id, 'address' => 'St 1', 'shipping_company_id' => $this->company->id];
    $this->postJson("{$this->api}/cart/quote", $b + ['payment_method_id' => $this->cod->id], $h)->assertOk()
        ->assertJsonPath('data.totals.cod_fee', '15.00')->assertJsonPath('data.totals.cod_fee_label', 'رسوم COD')->assertJsonPath('data.totals.total', '255.00')->assertJsonPath('data.totals.cod_amount', '255.00');
    $this->postJson("{$this->api}/cart/quote", $b + ['payment_method_id' => $this->wallet->id], $h)->assertOk()->assertJsonPath('data.totals.cod_fee', '0.00');
    $r = $this->postJson("{$this->api}/checkout", $b + ['payment_method_id' => $this->cod->id], $h)->assertCreated()
        ->assertJsonPath('data.cod_fee', '15.00')->assertJsonPath('data.cod_amount', '255.00')->assertJsonPath('data.total', '255.00');
    expect(json_encode($r->json()))->not->toContain('net_profit')->not->toContain('cogs');
    $this->get("{$this->s}/order/".$r->json('data.token'))->assertOk()->assertSee('رسوم COD');
});

it('shows the fee in the admin order, invoice and includes it in the cod-settlement report and net profit report', function () {
    ($this->setFee)(['cod_collect_shipping_upfront' => false, 'cod_extra_fee_label' => ['ar' => 'رسوم COD', 'en' => 'Handling']]);
    app()->instance('currentTenant', $this->tenant->fresh());
    $o = ($this->place)($this->cod->id);
    foreach ([S::CONFIRMED, S::PROCESSING, S::SHIPPED, S::DELIVERED] as $to) {
        app(OrderStatusService::class)->change($o, $to);
    }
    ($this->owner)();
    $this->get("{$this->t}/orders/{$o->id}")->assertOk()->assertSee('رسوم COD');
    $this->get("{$this->t}/orders/{$o->id}/invoice.pdf")->assertOk();
    $f = new \App\DTOs\Reports\ReportFilter(now()->subDay()->toDateString(), now()->toDateString(), false, [$this->tenant->id]);
    $row = app(\App\Repositories\Contracts\ReportRepositoryInterface::class)->codSettlement($f)[0];
    expect((float) $row['collected'])->toBe(255.0)->and((float) $row['owed'])->toBe(225.0);        // 255 collected - 30 carrier fee
    $np = app(\App\Repositories\Contracts\ReportRepositoryInterface::class)->netProfit($f);
    expect((float) $np['cod_fee'])->toBe(15.0);
    $this->get("{$this->t}/reports/net-profit")->assertOk();
});
