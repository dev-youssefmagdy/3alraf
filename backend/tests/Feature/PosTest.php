<?php

use App\Enums\StockMovementType;
use App\Models\Branch;
use App\Models\City;
use App\Models\Customer;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ShippingCompany;
use App\Models\Tenant;
use App\Models\TenantAdmin;
use App\Models\TenantProduct;
use App\Services\Stock\StockLedger;
use Database\Seeders\FoundationSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(FoundationSeeder::class);
    Storage::fake('media');
    Storage::fake('private');
    $this->tenant = Tenant::where('slug', 'egy')->first();
    codUpfront($this->tenant, false);
    app()->instance('currentTenant', $this->tenant);
    $this->t = 'http://egy.3alraf.test/admin';
    $this->product = Product::create(['type' => 'simple', 'name' => ['ar' => 'منتج', 'en' => 'Widget'], 'slug' => 'p1', 'sku' => 'WID-1', 'barcode' => '2000000000008']);
    TenantProduct::create(['product_id' => $this->product->id, 'price' => 100, 'cost' => 60, 'is_active' => true]);
    app(StockLedger::class)->record(Branch::first()->id, $this->product->id, 0, StockMovementType::PURCHASE_IN, 10, '60.00');
    $this->owner = TenantAdmin::withoutGlobalScopes()->where('email', 'owner@egy.test')->first();
    $this->actingAs($this->owner, 'tenant');
    $this->payload = fn (array $o = []) => array_merge([
        'items' => [['product_id' => $this->product->id, 'variant_id' => 0, 'qty' => 2]],
        'name' => 'Ali', 'phone' => '1001234567', 'city_id' => City::first()->id, 'address' => 'Street 1',
        'shipping_company_id' => ShippingCompany::first()->id, 'payment_method_id' => PaymentMethod::where('type', 'cod')->first()->id,
    ], $o);
});

it('searches products by name / sku and customers by name / phone', function () {
    $this->getJson("{$this->t}/pos/products?q=WID")->assertOk()->assertJsonPath('items.0.sku', 'WID-1')->assertJsonPath('items.0.stock', 10)->assertJsonPath('items.0.price', '100.00');
    $this->getJson("{$this->t}/pos/products?q=nothing-like-this")->assertOk()->assertJsonCount(0, 'items');

    Customer::create(['name' => 'Mona Said', 'email' => 'mona@example.com', 'phone' => '1055500000', 'password' => 'secret-123', 'is_active' => true]);
    $this->getJson("{$this->t}/pos/customers?q=mona")->assertOk()->assertJsonPath('items.0.name', 'Mona Said');
    $this->getJson("{$this->t}/pos/customers?q=105550")->assertOk()->assertJsonCount(1, 'items');
});

it('creates a customer without e-mail and refuses a duplicate phone', function () {
    $this->postJson("{$this->t}/pos/customers", ['name' => 'Walk In', 'phone' => '1007770000'])->assertOk()->assertJsonPath('data.customer.name', 'Walk In')->assertJsonPath('data.customer.email', null);
    $this->postJson("{$this->t}/pos/customers", ['name' => 'Again', 'phone' => '1007770000'])->assertStatus(422)->assertJsonValidationErrors('phone');
    $this->postJson("{$this->t}/pos/customers", ['name' => ''])->assertStatus(422)->assertJsonValidationErrors(['name', 'phone']);
});

it('quotes and places an order for a customer through the storefront pipeline', function () {
    $c = Customer::create(['name' => 'Mona', 'email' => 'mona@example.com', 'phone' => '1055500000', 'password' => 'secret-123', 'is_active' => true]);
    $this->postJson("{$this->t}/pos/quote", ($this->payload)())->assertOk()->assertJsonPath('data.totals.subtotal', '200.00')->assertJsonMissingPath('data.totals.cogs');

    $res = $this->postJson("{$this->t}/pos/orders", ($this->payload)(['customer_id' => $c->id]))->assertOk();
    $order = Order::latest('id')->first();
    expect($order->customer_id)->toBe($c->id)->and((string) $order->subtotal)->toBe('200.00')->and($order->items)->toHaveCount(1);
    $res->assertJsonPath('redirect', route('tenant.orders.show', $order->id));
    $this->getJson("{$this->t}/orders/datatable")->assertOk()->assertJsonFragment(['customer_name' => 'Ali'])->assertSee($order->number);
});

it('requires a customer and the pos.use permission', function () {
    $this->postJson("{$this->t}/pos/orders", ($this->payload)())->assertStatus(422)->assertJsonValidationErrors('customer_id');

    $clerk = TenantAdmin::withoutGlobalScopes()->create(['tenant_id' => $this->tenant->id, 'name' => 'Clerk', 'email' => 'clerk@egy.test', 'password' => 'secret-123', 'is_active' => true]);
    $clerk->givePermissionTo('orders.view');
    $this->actingAs($clerk, 'tenant');
    $this->get("{$this->t}/pos")->assertForbidden();
    $this->postJson("{$this->t}/pos/orders", ($this->payload)())->assertForbidden();
});

it('renders the page for the owner', function () {
    $this->get("{$this->t}/pos")->assertOk()->assertSee('pos-customer');
});
