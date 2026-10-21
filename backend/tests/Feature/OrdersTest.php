<?php

use App\DTOs\Orders\CheckoutData;
use App\DTOs\Orders\ReturnRequestData;
use App\Enums\OrderStatus as S;
use App\Enums\PaymentStatus;
use App\Enums\StockMovementType as T;
use App\Models\Branch;
use App\Models\City;
use App\Models\Customer;
use App\Models\DiscountCode;
use App\Models\FlashSale;
use App\Models\FlashSaleItem;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ShippingCompany;
use App\Models\StockLevel;
use App\Models\Tenant;
use App\Models\TenantAdmin;
use App\Models\TenantProduct;
use App\Services\Orders\DiscountValidator;
use App\Services\Orders\OrderPaymentService;
use App\Services\Orders\OrderPlacementService;
use App\Services\Orders\OrderStatusService;
use App\Services\Orders\PriceResolver;
use App\Services\Orders\ReturnService;
use App\Services\Stock\StockLedger;
use Database\Seeders\FoundationSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(FoundationSeeder::class);
    Storage::fake('media');
    Storage::fake('private');
    $this->tenant = Tenant::where('slug', 'egy')->first();
    $this->t = 'http://egy.3alraf.test/admin';
    $this->s = 'http://egy.3alraf.test/ar';
    app()->instance('currentTenant', $this->tenant);
    $this->branch = Branch::first();
    $this->product = Product::create(['type' => 'simple', 'name' => ['ar' => 'منتج'], 'slug' => 'p1', 'sku' => 'P1', 'barcode' => '2000000000008']);
    $this->tp = TenantProduct::create(['product_id' => $this->product->id, 'price' => 100, 'cost' => 60, 'is_active' => true]);
    $this->ledger = app(StockLedger::class);
    $this->ledger->record($this->branch->id, $this->product->id, 0, T::PURCHASE_IN, 10, '60.00');
    $this->city = City::first();
    $this->company = ShippingCompany::first();
    $this->cod = PaymentMethod::where('type', 'cod')->first();
    $this->wallet = PaymentMethod::where('type', 'wallet')->first();
    $this->lines = fn (int $qty) => [['product_id' => $this->product->id, 'variant_id' => 0, 'qty' => $qty]];
    $this->checkout = fn (array $o = []) => new CheckoutData(...array_merge([
        'name' => 'Ali', 'phone' => '0100', 'cityId' => $this->city->id, 'address' => 'Street 1',
        'shippingCompanyId' => $this->company->id, 'paymentMethodId' => $this->cod->id,
    ], $o));
    $this->place = fn (int $qty = 2, array $o = []) => app(OrderPlacementService::class)->place(($this->checkout)($o), ($this->lines)($qty))['order'];
});

function lvl(): StockLevel
{
    return StockLevel::where('product_id', test()->product->id)->first();
}

function actAsOwner(): void
{
    test()->actingAs(TenantAdmin::withoutGlobalScopes()->where('email', 'owner@egy.test')->first(), 'tenant');
}

it('resolves flash price, clamps by remaining quantity and falls back to the regular price', function () {
    $r = app(PriceResolver::class);
    $item = $r->item($this->product->id);
    expect($item['price'])->toBe('100.00');
    $this->tp->update(['sale_price' => 90]);
    expect($r->item($this->product->id)['price'])->toBe('90.00');

    $sale = FlashSale::create(['name' => ['ar' => 'عرض'], 'starts_at' => now()->subHour(), 'ends_at' => now()->addHour(), 'is_active' => true]);
    $fi = FlashSaleItem::create(['flash_sale_id' => $sale->id, 'product_id' => $this->product->id, 'product_variant_id' => 0, 'discount_type' => 'percent', 'value' => 50, 'qty_limit' => 3]);
    $seg = $r->segments($r->item($this->product->id), 5);
    expect($seg)->toHaveCount(2)->and($seg[0]['unit_price'])->toBe('45.00')->and($seg[0]['qty'])->toBe(3)->and($seg[1]['unit_price'])->toBe('90.00')->and($seg[1]['qty'])->toBe(2);

    $fi->update(['discount_type' => 'price', 'value' => 70, 'sold' => 3]);   // sold out -> regular
    expect($r->segments($r->item($this->product->id), 1)[0]['unit_price'])->toBe('90.00');
    $fi->update(['sold' => 0]);
    $sale->update(['ends_at' => now()->subMinute()]);                          // ended
    expect($r->segments($r->item($this->product->id), 1)[0]['unit_price'])->toBe('90.00');
    $sale->update(['ends_at' => now()->addHour(), 'is_active' => false]);
    expect($r->flashItem($this->product->id, 0))->toBeNull();
});

it('validates discount codes (dates, limits, minimum, scope)', function () {
    $v = app(DiscountValidator::class);
    $lines = [['key' => 0, 'product_id' => $this->product->id, 'unit_price' => '100.00', 'qty' => 2]];
    $mk = fn (array $o = []) => DiscountCode::create($o + ['code' => 'T'.mt_rand(1000, 9999), 'type' => 'percent', 'value' => 10, 'scope' => 'all', 'is_active' => true, 'min_order' => 0]);
    $fails = fn (string $code) => expect(fn () => $v->validate($code, $lines, 1))->toThrow(ValidationException::class);

    expect($v->validate($mk(['code' => 'ok'])->code, $lines)['rule']['value'])->toBe('10.00');
    $fails('missing');
    $fails($mk(['is_active' => false])->code);
    $fails($mk(['starts_at' => now()->addDay()])->code);
    $fails($mk(['ends_at' => now()->subDay()])->code);
    $fails($mk(['usage_limit' => 1, 'used_count' => 1])->code);
    $fails($mk(['min_order' => 500])->code);
    $limited = $mk(['per_customer_limit' => 1]);
    \App\Models\DiscountCodeUsage::create(['discount_code_id' => $limited->id, 'order_id' => ($this->place)(1)->id, 'customer_id' => 1, 'amount' => 5]);
    $fails($limited->code);
    expect($v->validate($limited->code, $lines, 2)['model']->id)->toBe($limited->id);   // another customer is fine

    $other = Product::create(['type' => 'simple', 'name' => ['ar' => 'x'], 'slug' => 'p2', 'sku' => 'P2']);
    $scoped = $mk(['scope' => 'products', 'product_ids' => [$other->id]]);
    $fails($scoped->code);                                                                  // nothing eligible
    $two = array_merge($lines, [['key' => 1, 'product_id' => $other->id, 'unit_price' => '10.00', 'qty' => 1]]);
    expect($v->validate($scoped->code, $two)['eligible'])->toBe([0 => false, 1 => true]);
    expect($v->validate(strtolower($scoped->code), $two)['model']->id)->toBe($scoped->id);  // case-insensitive
});

it('places an order, reserves stock, ships (sale_out), and releases on cancel', function () {
    $order = ($this->place)(2);
    expect($order->number)->toBe(sprintf('ORD-%06d', $order->id))->and($order->status)->toBe(S::PENDING)->and((string) $order->subtotal)->toBe('200.00')
        ->and((string) $order->shipping)->toBe('40.00')->and((string) $order->total)->toBe('240.00')->and((string) $order->cogs)->toBe('120.00')
        ->and($order->items)->toHaveCount(1)->and($order->histories)->toHaveCount(1)
        ->and(lvl()->on_hand)->toBe(10)->and(lvl()->reserved)->toBe(2)->and(lvl()->available)->toBe(8);

    // oversell refused atomically (nothing created, nothing reserved)
    $before = Order::count();
    expect(fn () => ($this->place)(9))->toThrow(ValidationException::class);
    expect(Order::count())->toBe($before)->and(lvl()->reserved)->toBe(2);

    $st = app(OrderStatusService::class);
    expect(fn () => $st->change($order, S::SHIPPED))->toThrow(ValidationException::class);   // pending -> shipped not allowed
    foreach ([S::CONFIRMED, S::PROCESSING, S::SHIPPED] as $to) {
        $st->change($order, $to);
    }
    expect(lvl()->on_hand)->toBe(8)->and(lvl()->reserved)->toBe(0)->and($order->fresh()->stock_deducted)->toBeTrue()
        ->and(\App\Models\StockMovement::where('type', 'sale_out')->value('quantity'))->toBe(-2);
    $st->change($order, S::DELIVERED);
    $order->refresh();
    expect($order->payment_status)->toBe(PaymentStatus::PAID)->and((string) $order->paid_amount)->toBe('240.00');   // COD collected on delivery

    $o2 = ($this->place)(3);
    expect(lvl()->reserved)->toBe(3);
    $st->change($o2, S::CANCELLED, 'no');
    expect(lvl()->reserved)->toBe(0)->and(lvl()->on_hand)->toBe(8)->and($o2->fresh()->status)->toBe(S::CANCELLED);
    expect(fn () => $st->change($o2, S::CONFIRMED))->toThrow(ValidationException::class);
});

it('increments flash counters on placement, decrements on cancel and counts discount usage', function () {
    $sale = FlashSale::create(['name' => ['ar' => 'عرض'], 'starts_at' => now()->subHour(), 'ends_at' => now()->addHour(), 'is_active' => true]);
    $fi = FlashSaleItem::create(['flash_sale_id' => $sale->id, 'product_id' => $this->product->id, 'product_variant_id' => 0, 'discount_type' => 'price', 'value' => 80, 'qty_limit' => 2]);
    $code = DiscountCode::create(['code' => 'SAVE', 'type' => 'fixed', 'value' => 10, 'scope' => 'all', 'is_active' => true, 'min_order' => 0, 'usage_limit' => 5]);

    $order = ($this->place)(3, ['discountCode' => 'save']);        // 2 @80 + 1 @100 = 260
    expect((string) $order->subtotal)->toBe('260.00')->and((string) $order->discount)->toBe('10.00')->and($order->items)->toHaveCount(2)
        ->and($fi->fresh()->sold)->toBe(2)->and($code->fresh()->used_count)->toBe(1)
        ->and($order->items->sum(fn ($i) => (float) $i->line_discount))->toBe(10.0);

    app(OrderStatusService::class)->change($order, S::CANCELLED);
    expect($fi->fresh()->sold)->toBe(0)->and($code->fresh()->used_count)->toBe(0);
});

it('handles wallet proof upload, approval and rejection', function () {
    $order = ($this->place)(1, ['paymentMethodId' => $this->wallet->id]);
    $svc = app(OrderPaymentService::class);
    expect(fn () => $svc->approveProof($order))->toThrow(ValidationException::class);
    $svc->uploadProof($order, UploadedFile::fake()->image('proof.jpg'));
    $order->refresh();
    expect($order->payment_status)->toBe(PaymentStatus::REVIEW)->and($order->payment_proof)->not->toBeNull();
    Storage::disk('private')->assertExists($order->payment_proof);
    Storage::disk('media')->assertMissing($order->payment_proof);

    $svc->rejectProof($order, 'blurry');
    expect($order->fresh()->payment_status)->toBe(PaymentStatus::UNPAID);
    $svc->uploadProof($order->fresh(), UploadedFile::fake()->image('p2.jpg'));
    $svc->approveProof($order->fresh());
    $order->refresh();
    expect($order->payment_status)->toBe(PaymentStatus::PAID)->and((string) $order->paid_amount)->toBe((string) $order->total)->and($order->status)->toBe(S::PENDING);   // approving a proof does NOT confirm
    app(OrderStatusService::class)->change($order, S::CONFIRMED);                                              // the admin confirms
    expect($order->fresh()->status)->toBe(S::CONFIRMED);
});

it('returns and refunds a delivered order with restock', function () {
    $order = ($this->place)(2, ['discountCode' => null]);
    $st = app(OrderStatusService::class);
    foreach ([S::CONFIRMED, S::PROCESSING, S::SHIPPED, S::DELIVERED] as $to) {
        $st->change($order, $to);
    }
    $item = $order->items->first();
    $rs = app(ReturnService::class);
    expect(fn () => $rs->request($order, ReturnRequestData::fromArray(['items' => [$item->id => ['quantity' => 3]]])))->toThrow(ValidationException::class);

    $ret = $rs->request($order->fresh(), ReturnRequestData::fromArray(['items' => [$item->id => ['quantity' => 1]], 'restock' => true]));
    expect((string) $ret->return_value)->toBe('100.00')->and($ret->number)->toBe(sprintf('RET-%06d', $ret->id));
    $rs->approve($ret);
    expect(lvl()->on_hand)->toBe(9)->and($item->fresh()->returned_qty)->toBe(1)->and($ret->fresh()->status->value)->toBe('approved');
    expect(fn () => $rs->refund($ret->fresh(), '150'))->toThrow(ValidationException::class);
    $rs->refund($ret->fresh(), '100');
    $order->refresh();
    expect((string) $order->refunded_amount)->toBe('100.00')->and($order->payment_status)->toBe(PaymentStatus::PARTIALLY_REFUNDED);

    $ret2 = $rs->request($order, ReturnRequestData::fromArray(['items' => [$item->id => ['quantity' => 1]], 'restock' => false, 'refund_shipping' => true]));
    $rs->approve($ret2);
    expect(lvl()->on_hand)->toBe(9)->and($order->fresh()->status)->toBe(S::RETURNED);       // not restocked, order fully returned
    expect($rs->refundable($ret2->fresh()))->toBe('140.00');                                  // 100 + 40 shipping
});

it('runs the storefront cart -> checkout flow and the admin order pages', function () {
    $this->getJson("{$this->s}/cart")->assertOk();
    $this->postJson("{$this->s}/cart/items", ['product_id' => $this->product->id, 'variant_id' => 0, 'quantity' => 99])->assertStatus(422);
    $this->postJson("{$this->s}/cart/items", ['product_id' => $this->product->id, 'variant_id' => 0, 'quantity' => 2])->assertOk()->assertJsonPath('data.totals.subtotal', '200.00');
    $this->postJson("{$this->s}/checkout/validate", [])->assertStatus(422)->assertJsonValidationErrors(['name', 'shipping_company_id', 'payment_method_id']);
    $payload = ['name' => 'Sara', 'phone' => '0111', 'city_id' => $this->city->id, 'address' => 'St 2', 'shipping_company_id' => $this->company->id, 'payment_method_id' => $this->cod->id, 'discount_code' => 'WELCOME10'];
    $this->postJson("{$this->s}/checkout/validate", $payload)->assertOk();
    $this->postJson("{$this->s}/checkout/validate", ['discount_code' => 'NOPE'] + $payload)->assertStatus(422)->assertJsonValidationErrors(['discount_code']);
    $res = $this->postJson("{$this->s}/checkout", $payload)->assertOk();
    $order = Order::where('number', $res->json('data.number'))->first();
    expect((string) $order->discount)->toBe('20.00')->and((string) $order->total)->toBe('220.00')->and(lvl()->reserved)->toBe(2);
    $this->getJson("{$this->s}/cart")->assertJsonPath('data.items', []);
    $this->get("{$this->s}/order/{$order->public_token}")->assertOk()->assertSee($order->number);

    // admin
    actAsOwner();
    $this->get("{$this->t}/orders")->assertOk();
    $this->getJson("{$this->t}/orders/datatable?draw=1&start=0&length=10&status=pending")->assertOk()->assertJsonPath('recordsFiltered', 1);
    $this->getJson("{$this->t}/orders/datatable?draw=1&start=0&length=10&status=shipped")->assertJsonPath('recordsFiltered', 0);
    $this->get("{$this->t}/orders/{$order->id}")->assertOk()->assertSee($order->number);
    $this->get("{$this->t}/orders/{$order->id}/invoice")->assertOk()->assertSee($order->number);
    $this->postJson("{$this->t}/orders/{$order->id}/status/validate", ['status' => 'bogus'])->assertStatus(422);
    $this->postJson("{$this->t}/orders/{$order->id}/status", ['status' => 'confirmed'])->assertOk();
    $this->postJson("{$this->t}/orders/{$order->id}/status", ['status' => 'delivered'])->assertStatus(422);
    expect($order->fresh()->status)->toBe(S::CONFIRMED);
    $this->get("{$this->t}/returns")->assertOk();
    $this->get("{$this->t}/customers")->assertOk();

    // notifications: new order + status change reached the owner, polling endpoint + read
    $poll = $this->getJson("{$this->t}/notifications")->assertOk();
    expect($poll->json('unread'))->toBe(2)->and($poll->json('items.0.title'))->toContain($order->number);
    $this->postJson("{$this->t}/notifications/read-all")->assertOk();
    $this->getJson("{$this->t}/notifications")->assertJsonPath('unread', 0);

    // discount code + flash sale admin CRUD
    $this->postJson("{$this->t}/discount-codes/validate", ['code' => 'x y'])->assertStatus(422)->assertJsonValidationErrors(['code', 'type']);
    $this->postJson("{$this->t}/discount-codes", ['code' => 'ten', 'type' => 'percent', 'value' => 10, 'scope' => 'all', 'is_active' => 1])->assertOk();
    $this->postJson("{$this->t}/discount-codes/validate", ['code' => 'TEN', 'type' => 'percent', 'value' => 10, 'scope' => 'all'])->assertJsonValidationErrors(['code']);   // unique
    $this->postJson("{$this->t}/flash-sales", ['name' => ['ar' => 'عرض'], 'starts_at' => now()->format('Y-m-d\TH:i'), 'ends_at' => now()->addDay()->format('Y-m-d\TH:i'),
        'items' => [['item' => $this->product->id.'-0', 'discount_type' => 'price', 'value' => 50, 'qty_limit' => 5]]])->assertOk();
    expect(FlashSaleItem::count())->toBe(1);
    $this->get("{$this->t}/discount-codes")->assertOk();
    $this->get("{$this->t}/flash-sales/create")->assertOk();
    $this->get("{$this->t}/discount-codes/create")->assertOk();
});
