<?php

use App\DTOs\Orders\CheckoutData;
use App\Enums\OrderStatus;
use App\Enums\ProofStatus;
use App\Enums\StockMovementType as T;
use App\Models\Branch;
use App\Models\Bundle;
use App\Models\BundleItem;
use App\Models\City;
use App\Models\Customer;
use App\Models\DiscountCode;
use App\Models\Order;
use App\Models\PackagingOption;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ShippingCompany;
use App\Models\Tenant;
use App\Models\TenantAdmin;
use App\Models\TenantProduct;
use App\Services\Orders\OfflineCheckoutThrottle;
use App\Services\Orders\OrderPlacementService;
use App\Services\Orders\OrderStatusService;
use App\Services\Orders\PaymentProofFiles;
use App\Services\Stock\StockLedger;
use Database\Seeders\FoundationSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

/* Regression tests for the review of offline payment proof / bundles / special packaging. */

beforeEach(function () {
    Cache::flush();
    $this->seed(FoundationSeeder::class);
    Storage::fake('private');
    Storage::fake('media');
    $this->tenant = Tenant::where('slug', 'egy')->first();
    codUpfront($this->tenant, false);
    app()->instance('currentTenant', $this->tenant);
    $this->s = 'http://egy.3alraf.test/ar';
    $this->t = 'http://egy.3alraf.test/admin';
    $this->product = Product::create(['type' => 'simple', 'name' => ['ar' => 'منتج'], 'slug' => 'rv1', 'sku' => 'RV1', 'barcode' => '2000000000099']);
    TenantProduct::create(['product_id' => $this->product->id, 'price' => 100, 'cost' => 60, 'is_active' => true]);
    app(StockLedger::class)->record(Branch::first()->id, $this->product->id, 0, T::PURCHASE_IN, 500, '60.00');
    $this->city = City::first();
    $this->company = ShippingCompany::first();
    $this->cod = PaymentMethod::where('type', 'cod')->first();
    $this->wallet = PaymentMethod::where('type', 'wallet')->first();
    config(['checkout.offline.ip_attempts' => 1000, 'checkout.offline.ip_daily' => 1000, 'checkout.offline.contact_attempts' => 1000, 'checkout.offline.open_cap' => 1000]);
    $this->place = fn (array $o = []) => app(OrderPlacementService::class)->place(new CheckoutData(...array_merge([
        'name' => 'Ali', 'phone' => '01000000100', 'email' => 'ali@x.test', 'cityId' => $this->city->id, 'address' => 'St 1',
        'shippingCompanyId' => $this->company->id, 'paymentMethodId' => $this->wallet->id, 'proof' => testProof(),
    ], $o)), [['product_id' => $this->product->id, 'variant_id' => 0, 'qty' => 1, 'bundle_id' => 0]])['order'];
    $this->payload = fn (array $o = []) => withSavedAddress(array_merge([
        'name' => 'Ali', 'phone' => '010'.random_int(10000000, 99999999), 'email' => 'u'.random_int(1, 9999999).'@x.test', 'city_id' => $this->city->id, 'address' => 'St 1',
        'shipping_company_id' => $this->company->id, 'payment_method_id' => $this->wallet->id,
    ], $o));
    $this->post = fn (string $path, array $data) => $this->withHeader('Accept', 'application/json')->post("{$this->s}/$path", $data);
});

// Web checkout is signed-in-only (commit 21780b3, REQUIREMENTS.md): run these flows as a logged-in customer.
beforeEach(function () {
    $this->actingAs(Customer::where('email', 'customer@egy.test')->first(), 'web');
});

it('counts offline pre-validate calls that carry a file in their own looser tier', function () {
    config(['checkout.validate.attempts' => 3]);
    $this->postJson("{$this->s}/cart/items", ['product_id' => $this->product->id, 'variant_id' => 0, 'quantity' => 1])->assertOk();
    foreach (range(1, 3) as $i) {
        ($this->post)('checkout/validate', ($this->payload)() + ['proof' => testProof()])->assertOk();
    }
    $r = ($this->post)('checkout/validate', ($this->payload)() + ['proof' => testProof()])->assertStatus(429);
    expect((int) $r->headers->get('Retry-After'))->toBeGreaterThan(0);
    // COD pre-validate is never limited by it, and the place-order tiers are untouched
    ($this->post)('checkout/validate', ($this->payload)(['payment_method_id' => $this->cod->id]))->assertOk();
    ($this->post)('checkout', ($this->payload)() + ['proof' => testProof()])->assertStatus(200);
});

it('treats Arabic-Indic digits in a phone like their ASCII form (limiter key, stored value, open-order cap)', function () {
    expect(OfflineCheckoutThrottle::phoneKey('٠١٠٠١١١٢٢٢٢'))->toBe(OfflineCheckoutThrottle::phoneKey('01001112222'))
        ->and(OfflineCheckoutThrottle::phoneKey('۰۱۰۰۱۱۱۲۲۲۲'))->toBe('1001112222');
    $this->postJson("{$this->s}/cart/items", ['product_id' => $this->product->id, 'variant_id' => 0, 'quantity' => 1])->assertOk();
    ($this->post)('checkout', ($this->payload)(['phone' => '٠١٠٠١١١٢٢٢٢']) + ['proof' => testProof()])->assertOk();
    // logged-in checkout takes the phone from the saved address (stored as saved); the limiter key still folds digits
    expect(OfflineCheckoutThrottle::phoneKey(Order::first()->customer_phone))->toBe('1001112222');
    config(['checkout.offline.open_cap' => 1]);
    expect(app(OfflineCheckoutThrottle::class)->openOrders('+20 100-111-2222', null, null))->toBe(1)
        ->and(app(OfflineCheckoutThrottle::class)->openOrders('(0100) 1112222', null, null))->toBe(1);
});

it('has a DB-level guard: two live orders can never share a proof hash, rejected / cancelled ones free it', function () {
    $a = ($this->place)();
    $copy = function () use ($a) {
        $b = $a->replicate(['number', 'proof_guard']);
        $b->public_token = (string) Str::uuid();
        $b->number = 'ORD-X'.random_int(100000, 999999);
        $b->save();

        return $b;
    };
    expect(fn () => $copy())->toThrow(QueryException::class);
    $a->update(['proof_status' => ProofStatus::REJECTED]);
    $b = $copy();
    $b->update(['proof_status' => ProofStatus::PENDING]); // b is now the live holder
    expect($b->exists)->toBeTrue();
    expect(fn () => $a->update(['proof_status' => ProofStatus::PENDING]))->toThrow(QueryException::class); // b is live now
    $b->update(['status' => OrderStatus::CANCELLED]);
    $a->update(['proof_status' => ProofStatus::PENDING]);
    expect($a->fresh()->proof_status)->toBe(ProofStatus::PENDING);
});

it('turns a lost proof-hash race into a clean 422 instead of a 500 and removes the stored file', function () {
    $bytes = testProof();
    ($this->place)(['proof' => $bytes]);
    // simulate the race: the application-level check passes (TOCTOU), the DB guard decides
    $this->app->bind(PaymentProofFiles::class, fn () => new class extends PaymentProofFiles
    {
        public function assertNotReused(string $hash, ?int $exceptOrderId = null): void {}
    });
    $same = UploadedFile::fake()->createWithContent('again.png', file_get_contents($bytes->getRealPath()));
    $before = count(Storage::disk('private')->allFiles());
    expect(fn () => ($this->place)(['proof' => $same, 'phone' => '01100000111']))->toThrow(ValidationException::class);
    expect(Order::count())->toBe(1)->and(count(Storage::disk('private')->allFiles()))->toBe($before);
});

it('shows a warning on the admin order page when its proof hash is shared, and labels WALLET as offline / manual transfer', function () {
    $a = ($this->place)();
    $a->update(['proof_status' => ProofStatus::REJECTED]);
    $b = ($this->place)(['proof' => UploadedFile::fake()->createWithContent('x.png', file_get_contents(Storage::disk('private')->path($a->payment_proof))), 'phone' => '01100000111']);
    $this->actingAs(TenantAdmin::withoutGlobalScopes()->where('email', 'owner@egy.test')->first(), 'tenant');
    $this->get("{$this->t}/orders/{$b->id}")->assertOk()->assertSee(__('orders.proof_shared_warning'))->assertSee($a->number);
    $this->get("{$this->t}/orders/{$a->id}")->assertOk()->assertSee(__('orders.proof_shared_warning'))->assertSee($b->number);
    $c = ($this->place)(['phone' => '01200000122']);
    $this->get("{$this->t}/orders/{$c->id}")->assertOk()->assertDontSee(__('orders.proof_shared_warning'));
    foreach (['ar', 'en'] as $l) {
        expect(trans('ops.enums.payment_method_type.wallet', [], $l))->toBe($l === 'en' ? 'Offline / manual transfer' : 'دفع يدوي / تحويل (أوفلاين)');
    }
});

it('treats packaging VAT as inclusive even when the goods themselves carry no VAT (fully discounted order)', function () {
    $o = new Order(['subtotal' => '100.00', 'discount' => '100.00', 'shipping_net' => '0', 'payment_fee_charged' => '0', 'cod_fee' => '0', 'tax' => '0.00',
        'packaging_fee' => '114.00', 'packaging_tax' => '14.00', 'packaging_cost' => '0', 'total' => '114.00']);
    expect($o->taxIncluded())->toBeTrue()->and($o->packagingRevenue())->toBe('100.00');
    $x = new Order(['subtotal' => '100.00', 'discount' => '100.00', 'shipping_net' => '0', 'payment_fee_charged' => '0', 'cod_fee' => '0', 'tax' => '0.00',
        'packaging_fee' => '100.00', 'packaging_tax' => '14.00', 'packaging_cost' => '0', 'total' => '114.00']);
    expect($x->taxIncluded())->toBeFalse()->and($x->packagingRevenue())->toBe('100.00');
});

it('documents the checkout as multipart when it takes the proof file, and keeps permissions idempotent for existing owners', function () {
    $doc = json_decode(file_get_contents(base_path('docs/api/openapi.json')), true);
    expect($doc['paths']['/api/v1/checkout']['post']['requestBody']['content'])->toHaveKey('multipart/form-data');
    $migration = require base_path('database/migrations/2026_11_01_100002_seed_bundle_packaging_permissions.php');
    $migration->up();
    $migration->up();
    $role = Role::where('name', 'tenant-owner')->where('guard_name', 'tenant')->first();
    expect($role->hasPermissionTo('bundles.create'))->toBeTrue()->and($role->hasPermissionTo('packaging.delete'))->toBeTrue();
});

it('enforces the proof on web and API against crafted requests (missing, empty, array, wrong field, wallet prepay on COD)', function () {
    $this->postJson("{$this->s}/cart/items", ['product_id' => $this->product->id, 'variant_id' => 0, 'quantity' => 1])->assertOk();
    foreach (['checkout', 'checkout/validate'] as $path) {
        ($this->post)($path, ($this->payload)())->assertStatus(422)->assertJsonValidationErrors('proof');
        ($this->post)($path, ($this->payload)() + ['proof' => ''])->assertStatus(422)->assertJsonValidationErrors('proof');
        ($this->post)($path, ($this->payload)() + ['proof' => 'C:\\fake\\proof.png'])->assertStatus(422)->assertJsonValidationErrors('proof');
        ($this->post)($path, ($this->payload)() + ['proof' => [testProof(), testProof()]])->assertStatus(422)->assertJsonValidationErrors('proof');
        ($this->post)($path, ($this->payload)() + ['proof_file' => testProof(), 'file' => testProof(), 'payment_proof' => testProof()])->assertStatus(422)->assertJsonValidationErrors('proof');
        ($this->post)($path, ($this->payload)() + ['proof' => UploadedFile::fake()->create('r.php', 5, 'image/png')])->assertStatus(422)->assertJsonValidationErrors('proof');
    }
    codUpfront($this->tenant, true);
    ($this->post)('checkout', ($this->payload)(['payment_method_id' => $this->cod->id, 'prepay_method_id' => $this->wallet->id]))->assertStatus(422)->assertJsonValidationErrors('proof');
    expect(Order::count())->toBe(0)->and(Storage::disk('private')->allFiles())->toBe([]);

    $api = 'http://egy.3alraf.test/api/v1';
    $h = ['X-Cart-Token' => $this->postJson("$api/cart/items", ['product_id' => $this->product->id, 'variant_id' => 0, 'quantity' => 1])->headers->get('X-Cart-Token'), 'Accept' => 'application/json'];
    foreach (['checkout', 'checkout/validate'] as $path) {
        $this->post("$api/$path", ($this->payload)(), $h)->assertStatus(422)->assertJsonValidationErrors('proof');
        $this->post("$api/$path", ($this->payload)(['payment_method_id' => $this->cod->id, 'prepay_method_id' => $this->wallet->id]) + ['file' => testProof()], $h)->assertStatus(422);
    }
    expect(Order::count())->toBe(0);
});

it('keeps order net_profit, the net-profit report and the customer totals consistent for bundle + code + packaging (inclusive VAT, COD)', function () {
    $this->tenant->update(['settings' => array_merge($this->tenant->settings ?? [], ['tax_rate' => 14, 'prices_include_tax' => true])]);
    app()->instance('currentTenant', $this->tenant->refresh());
    $p2 = Product::create(['type' => 'simple', 'name' => ['ar' => 'ب'], 'slug' => 'rv2', 'sku' => 'RV2', 'barcode' => '2000000000101']);
    TenantProduct::create(['product_id' => $p2->id, 'price' => 33.33, 'cost' => 10, 'is_active' => true]);
    app(StockLedger::class)->record(Branch::first()->id, $p2->id, 0, T::PURCHASE_IN, 50, '10.00');
    $bundle = Bundle::create(['name' => ['ar' => 'باقة'], 'slug' => 'rvb', 'price' => '111.11', 'is_active' => true]);
    foreach ([[$this->product, 1], [$p2, 3]] as [$pr, $q]) {
        BundleItem::create(['bundle_id' => $bundle->id, 'product_id' => $pr->id, 'product_variant_id' => 0, 'quantity' => $q]);
    }
    $opt = PackagingOption::create(['name' => ['ar' => 'هدية'], 'price' => 22.80, 'cost' => 5, 'is_active' => true]);
    DiscountCode::create(['code' => 'RV7', 'type' => 'percent', 'value' => 7, 'scope' => 'all', 'is_active' => true, 'min_order' => 0]);
    $o = app(OrderPlacementService::class)->place(new CheckoutData(name: 'Ali', phone: '01000000100', cityId: $this->city->id, address: 'St', shippingCompanyId: $this->company->id,
        paymentMethodId: $this->cod->id, discountCode: 'RV7', packagingOptionId: $opt->id), [['product_id' => 0, 'variant_id' => 0, 'qty' => 3, 'bundle_id' => $bundle->id]])['order'];
    expect((string) $o->subtotal)->toBe('333.33');
    foreach ([OrderStatus::CONFIRMED, OrderStatus::PROCESSING, OrderStatus::SHIPPED, OrderStatus::DELIVERED] as $to) {
        app(OrderStatusService::class)->change($o, $to);
    }
    $o->refresh();
    $this->actingAs(TenantAdmin::withoutGlobalScopes()->where('email', 'owner@egy.test')->first(), 'tenant');
    $np = $this->get("{$this->t}/reports/net-profit")->assertOk()->viewData('data');
    $op = collect($np['rows'])->firstWhere('line', __('reports.line.order_profit'))['amount'];
    expect($op)->toBe(number_format((float) $o->net_profit, 2, '.', ''));
    $this->get("{$this->t}/orders/{$o->id}/invoice")->assertOk()->assertSee(number_format((float) $o->total, 2));
    expect($o->taxIncluded())->toBeTrue();
});
