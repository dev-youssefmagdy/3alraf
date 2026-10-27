<?php

use App\DTOs\Orders\CheckoutData;
use App\DTOs\Orders\ReturnRequestData;
use App\Enums\OrderStatus as S;
use App\Enums\PaymentStatus;
use App\Enums\StockMovementType as T;
use App\Listeners\NotifyAdminsOfNewOrder;
use App\Listeners\NotifyAdminsOfPaymentProof;
use App\Listeners\NotifyAdminsOfStatusChange;
use App\Models\Branch;
use App\Models\City;
use App\Models\Customer;
use App\Models\DiscountCode;
use App\Models\Order;
use App\Models\PaymentEvent;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ShippingCompany;
use App\Models\Tenant;
use App\Models\TenantAdmin;
use App\Models\TenantProduct;
use App\Services\Orders\OrderPaymentService;
use App\Services\Orders\OrderPlacementService;
use App\Services\Orders\OrderStatusService;
use App\Services\Orders\ReturnService;
use App\Services\Payments\Drivers\FawryPayGateway;
use App\Services\Payments\Drivers\PaymobGateway;
use App\Services\Payments\PaymentGatewayFactory;
use App\Services\Stock\StockLedger;
use Database\Seeders\FoundationSeeder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed(FoundationSeeder::class);
    Storage::fake('private');
    Storage::fake('media');
    $this->tenant = Tenant::where('slug', 'egy')->first();
    codUpfront($this->tenant, false);   // legacy tests: carrier collects the full total
    $this->t = 'http://egy.3alraf.test/admin';
    $this->s = 'http://egy.3alraf.test/ar';
    app()->instance('currentTenant', $this->tenant);
    $branch = Branch::first();
    $this->product = Product::create(['type' => 'simple', 'name' => ['ar' => 'منتج'], 'slug' => 'pp1', 'sku' => 'PP1', 'barcode' => '2000000000015']);
    TenantProduct::create(['product_id' => $this->product->id, 'price' => 100, 'cost' => 60, 'is_active' => true]);
    app(StockLedger::class)->record($branch->id, $this->product->id, 0, T::PURCHASE_IN, 50, '60.00');
    $this->city = City::first();
    $this->company = ShippingCompany::first();             // rate for first city: price 40, cost 30
    $this->cod = PaymentMethod::where('type', 'cod')->first();
    $this->wallet = PaymentMethod::where('type', 'wallet')->first();
    $this->paymob = PaymentMethod::where('provider', 'paymob')->first();
    $this->paymob->update(['is_active' => true, 'credentials' => ['api_key' => 'k', 'integration_id' => '11', 'iframe_id' => '22', 'hmac_secret' => 'hsecret']]);
    $this->fawry = PaymentMethod::create(['type' => 'online', 'provider' => 'fawrypay', 'name' => ['ar' => 'فوري'], 'fee_percent' => 0, 'fee_fixed' => 0, 'fee_bearer' => 'customer',
        'credentials' => ['merchant_code' => 'MC1', 'secure_key' => 'fsecret'], 'is_sandbox' => true, 'is_active' => true]);
    $this->place = fn (int $qty, array $o = []) => app(OrderPlacementService::class)->place(new CheckoutData(...array_merge([
        'proof' => testProof(), 'name' => 'Ali', 'phone' => '0100', 'email' => 'ali@x.test', 'cityId' => $this->city->id, 'address' => 'St 1',
        'shippingCompanyId' => $this->company->id, 'paymentMethodId' => $this->cod->id,
    ], $o)), [['product_id' => $this->product->id, 'variant_id' => 0, 'qty' => $qty]]);
    $this->setUpfront = fn (bool $on) => $this->tenant->update(['settings' => array_merge($this->tenant->settings ?? [], ['cod_collect_shipping_upfront' => $on])]);
    $this->owner = fn () => $this->actingAs(TenantAdmin::withoutGlobalScopes()->where('email', 'owner@egy.test')->first(), 'tenant');
});

// Web checkout is signed-in-only (commit 21780b3, REQUIREMENTS.md): run these flows as a logged-in customer.
beforeEach(function () {
    $this->actingAs(Customer::where('email', 'customer@egy.test')->first(), 'web');
});

/** Fresh Http fake per call (stubs registered earlier would otherwise win). */
function fakeHttp(array|Closure $map): void
{
    Http::swap(new Factory);
    Http::fake($map);
}

function paymobTx(Order $o, array $over = []): array
{
    return array_replace_recursive(['id' => 5001, 'amount_cents' => (int) round((float) $o->payableNow() * 100), 'currency' => 'EGP', 'success' => true, 'pending' => false,
        'error_occured' => false, 'has_parent_transaction' => false, 'integration_id' => 11, 'is_3d_secure' => true, 'is_auth' => false, 'is_capture' => false, 'is_refunded' => false,
        'is_standalone_payment' => true, 'is_voided' => false, 'owner' => 7, 'created_at' => '2026-10-22T10:00:00', 'order' => ['id' => 99, 'merchant_order_id' => $o->number],
        'source_data' => ['pan' => '2346', 'sub_type' => 'MasterCard', 'type' => 'card']], $over);
}

function paymobHook(Order $o, array $over = [], ?array $requery = null, string $secret = 'hsecret')
{
    $obj = paymobTx($o, $over);
    fakeHttp([
        '*/api/auth/tokens' => Http::response(['token' => 'AUTH']),
        '*/api/acceptance/transactions/*' => Http::response($requery ?? $obj),
    ]);
    $hmac = PaymobGateway::hmac($obj, $secret);

    return test()->call('POST', 'http://egy.3alraf.test/webhooks/payments/'.test()->paymob->id.'?hmac='.$hmac, [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(['type' => 'TRANSACTION', 'obj' => $obj]));
}

function fawryBody(Order $o, array $over = []): array
{
    $p = array_merge(['fawryRefNumber' => '9900123', 'merchantRefNumber' => $o->number, 'paymentAmount' => (float) $o->payableNow(), 'orderAmount' => (float) $o->payableNow(),
        'orderStatus' => 'PAID', 'paymentMethod' => 'CARD', 'paymentRefrenceNumber' => '', 'fawryFees' => 0], $over);
    $p['messageSignature'] = FawryPayGateway::signatureFor($p, 'fsecret');

    return $p;
}

function fawryHook(Order $o, array $over = [], bool $badSig = false, ?array $statusReply = null)
{
    $p = fawryBody($o, $over);
    if ($badSig) {
        $p['messageSignature'] = str_repeat('a', 64);
    }

    // Fawry status/v2 re-query answers with the same facts as the (legit) webhook unless a test passes $statusReply
    fakeHttp(['*/payments/status/v2*' => Http::response($statusReply ?? ['orderStatus' => $p['orderStatus'], 'paymentAmount' => $p['paymentAmount'], 'fawryRefNumber' => $p['fawryRefNumber']])]);

    return test()->postJson('http://egy.3alraf.test/webhooks/payments/'.test()->fawry->id, $p);
}

// ------------------------------------------------------------------ Paymob

it('creates a Paymob payment through auth -> order -> payment key and returns the iframe URL', function () {
    fakeHttp([
        '*/api/auth/tokens' => Http::response(['token' => 'AUTH']),
        '*/api/ecommerce/orders' => Http::response(['id' => 321]),
        '*/api/acceptance/payment_keys' => Http::response(['token' => 'PKEY']),
    ]);
    $res = ($this->place)(1, ['paymentMethodId' => $this->paymob->id]);
    expect($res['payment']->redirectUrl)->toBe('https://accept.paymob.com/api/acceptance/iframes/22?payment_token=PKEY')
        ->and($res['order']->gateway_reference)->toBe('321')->and($res['order']->status)->toBe(S::PENDING);
    Http::assertSent(fn ($r) => str_ends_with($r->url(), '/api/ecommerce/orders') && $r['merchant_order_id'] === $res['order']->number && $r['amount_cents'] === (int) round((float) $res['order']->total * 100));
    Http::assertSent(fn ($r) => str_ends_with($r->url(), '/payment_keys') && $r['integration_id'] === 11 && $r['order_id'] === 321);
});

it('keeps the order pending (retryable) when the provider is down at checkout', function () {
    fakeHttp(['*' => Http::response('boom', 500)]);
    $res = ($this->place)(1, ['paymentMethodId' => $this->paymob->id]);
    expect($res['payment'])->toBeNull()->and($res['order']->status)->toBe(S::PENDING);
    fakeHttp([
        '*/api/auth/tokens' => Http::response(['token' => 'AUTH']), '*/api/ecommerce/orders' => Http::response(['id' => 322]), '*/api/acceptance/payment_keys' => Http::response(['token' => 'PK2']),
    ]);
    $this->postJson("{$this->s}/order/{$res['order']->public_token}/pay")->assertOk()->assertJsonPath('data.pay_url', 'https://accept.paymob.com/api/acceptance/iframes/22?payment_token=PK2');
    Http::assertSent(fn ($r) => str_ends_with($r->url(), '/api/ecommerce/orders') && $r['merchant_order_id'] === $res['order']->number.'-R2');   // retry uses a fresh merchant ref
});

it('verifies Paymob HMAC-SHA512, re-queries the transaction, settles once and ignores replays', function () {
    $order = ($this->place)(1, ['paymentMethodId' => $this->paymob->id])['order'];
    paymobHook($order)->assertOk();
    $order->refresh();
    expect($order->payment_status)->toBe(PaymentStatus::PAID)->and((string) $order->paid_amount)->toBe((string) $order->total)->and($order->gateway_reference)->toBe('5001')
        ->and($order->status)->toBe(S::PENDING);                       // paid online still waits for the admin (auto_confirm_paid_online off)
    Http::assertSent(fn ($r) => str_contains($r->url(), '/api/acceptance/transactions/5001'));
    paymobHook($order)->assertOk()->assertJson(['duplicate' => true]);   // replay
    expect(PaymentEvent::where('provider', 'paymob')->count())->toBe(1);
    expect($order->histories()->where('note', __('orders.gateway_paid_note'))->count())->toBe(1);
});

it('rejects Paymob webhooks with a bad signature, wrong amount/currency/status or unknown order', function () {
    $order = ($this->place)(1, ['paymentMethodId' => $this->paymob->id])['order'];
    paymobHook($order, [], null, 'wrong-secret')->assertStatus(400);                                              // bad HMAC
    paymobHook($order, ['amount_cents' => 100])->assertStatus(422);                                              // underpaid (HMAC valid, amount != our order)
    paymobHook($order, [], paymobTx($order, ['currency' => 'USD']))->assertStatus(422);                           // provider says other currency
    paymobHook($order, [], paymobTx($order, ['success' => false]))->assertStatus(422);                            // callback says paid, provider says no
    paymobHook($order, [], paymobTx($order, ['order' => ['merchant_order_id' => 'ORD-OTHER']]))->assertStatus(422); // provider's order reference differs
    expect($order->fresh()->payment_status)->toBe(PaymentStatus::UNPAID);
    paymobHook($order, ['order' => ['merchant_order_id' => 'ORD-404404']])->assertOk()->assertJson(['ignored' => true]);
    expect($order->fresh()->payment_status)->toBe(PaymentStatus::UNPAID)->and(PaymentEvent::count())->toBe(0);
    paymobHook($order, ['success' => false])->assertOk();                                                          // genuine failed payment
    expect($order->fresh()->payment_status)->toBe(PaymentStatus::FAILED);
});

it('auto-confirms a paid online order only when settings.auto_confirm_paid_online is on', function () {
    $this->tenant->update(['settings' => ['auto_confirm_paid_online' => true]]);
    $order = ($this->place)(1, ['paymentMethodId' => $this->paymob->id])['order'];
    paymobHook($order)->assertOk();
    expect($order->fresh()->status)->toBe(S::CONFIRMED)->and((float) $order->fresh()->net_profit)->toBeGreaterThan(0);
});

// ------------------------------------------------------------------ FawryPay

it('creates a FawryPay payment link with the SHA-256 signature', function () {
    fakeHttp(['*/fawrypay-api/api/payments/init' => Http::response('https://atfawry.fawrystaging.com/atfawry/pay/abc', 200)]);
    $res = ($this->place)(1, ['paymentMethodId' => $this->fawry->id]);
    expect($res['payment']->redirectUrl)->toBe('https://atfawry.fawrystaging.com/atfawry/pay/abc');
    $o = $res['order'];
    Http::assertSent(function ($r) use ($o) {
        $price = number_format((float) $o->total, 2, '.', '');
        $expected = hash('sha256', 'MC1'.$o->number.'ali@x.test'.$r['returnUrl'].'11'.$price.'fsecret');

        return $r['merchantRefNum'] === $o->number && $r['signature'] === $expected && str_contains($r['orderWebHookUrl'], '/webhooks/payments/'.$this->fawry->id);
    });
});

it('verifies FawryPay signatures, cross-checks amount/reference and settles exactly once', function () {
    $order = ($this->place)(1, ['paymentMethodId' => $this->fawry->id])['order'];
    fawryHook($order, [], true)->assertStatus(400);                                                    // bad signature
    fawryHook($order, ['orderAmount' => 1.0, 'paymentAmount' => 1.0])->assertStatus(422);              // wrong amount (validly signed)
    fawryHook($order, ['merchantRefNumber' => 'ORD-999999'])->assertOk()->assertJson(['ignored' => true]); // unknown order
    expect($order->fresh()->payment_status)->toBe(PaymentStatus::UNPAID);
    fawryHook($order)->assertOk();
    $order->refresh();
    expect($order->payment_status)->toBe(PaymentStatus::PAID)->and($order->gateway_reference)->toBe('9900123');
    fawryHook($order)->assertOk()->assertJson(['duplicate' => true]);                                  // replay
    fawryHook($order, ['orderStatus' => 'FAILED'])->assertOk();                                        // late failure never un-pays
    expect($order->fresh()->payment_status)->toBe(PaymentStatus::PAID);
});

it('re-queries FawryPay and rejects a webhook the provider does not confirm', function () {
    $order = ($this->place)(1, ['paymentMethodId' => $this->fawry->id])['order'];
    fawryHook($order, [], false, ['orderStatus' => 'UNPAID', 'paymentAmount' => 0, 'fawryRefNumber' => '9900123'])->assertStatus(422);   // forged PAID
    fawryHook($order, [], false, ['orderStatus' => 'PAID', 'paymentAmount' => 1.0, 'fawryRefNumber' => '9900123'])->assertStatus(422);    // amount differs at provider
    expect($order->fresh()->payment_status)->toBe(PaymentStatus::UNPAID);
    fawryHook($order)->assertOk();
    expect($order->fresh()->payment_status)->toBe(PaymentStatus::PAID);
    $b = ($this->place)(1, ['paymentMethodId' => $this->fawry->id])['order'];
    fakeHttp(['*/payments/status/v2*' => Http::response([], 500)]);
    $this->postJson('http://egy.3alraf.test/webhooks/payments/'.$this->fawry->id, fawryBody($b))->assertStatus(422);   // provider unreachable => rejected, Fawry retries
    expect($b->fresh()->payment_status)->toBe(PaymentStatus::UNPAID);
});

it('does not accept another tenant\'s webhook / method id and stubs unimplemented gateways', function () {
    $sa = Tenant::create(['slug' => 'sa', 'name' => 'Saudi', 'country' => 'SA', 'currency_code' => 'SAR', 'currency_symbol' => 'SAR', 'currency_rate' => 2, 'default_locale' => 'ar', 'locales' => ['ar'], 'timezone' => 'Asia/Riyadh', 'is_active' => true]);
    $foreign = PaymentMethod::withoutGlobalScopes()->create(['tenant_id' => $sa->id, 'type' => 'online', 'provider' => 'fawrypay', 'name' => ['ar' => 'x'], 'fee_percent' => 0, 'fee_fixed' => 0, 'fee_bearer' => 'customer', 'credentials' => ['merchant_code' => 'M', 'secure_key' => 's'], 'is_active' => true]);
    $this->postJson("http://egy.3alraf.test/webhooks/payments/{$foreign->id}", ['x' => 1])->assertNotFound();
    // checkout with a foreign payment method / prepay method id is a validation error, never an order
    $this->postJson("{$this->s}/cart/items", ['product_id' => $this->product->id, 'variant_id' => 0, 'quantity' => 1])->assertOk();
    $payload = withSavedAddress(['name' => 'Sara', 'phone' => '0111', 'city_id' => $this->city->id, 'address' => 'St 2', 'shipping_company_id' => $this->company->id]);
    $this->postJson("{$this->s}/checkout/validate", $payload + ['payment_method_id' => $foreign->id])->assertStatus(422)->assertJsonValidationErrors(['payment_method_id']);
    $this->postJson("{$this->s}/checkout/validate", $payload + ['payment_method_id' => $this->cod->id, 'prepay_method_id' => $foreign->id])->assertStatus(422)->assertJsonValidationErrors(['prepay_method_id']);
    // stripe is a clean "not implemented" stub and is never offered
    $stripe = PaymentMethod::create(['type' => 'online', 'provider' => 'stripe', 'name' => ['ar' => 's'], 'fee_percent' => 0, 'fee_fixed' => 0, 'fee_bearer' => 'customer', 'credentials' => ['publishable_key' => 'a', 'secret_key' => 'b', 'webhook_secret' => 'c'], 'is_active' => true]);
    expect(PaymentMethod::available()->pluck('id'))->not->toContain($stripe->id);
    $this->postJson("{$this->s}/checkout/validate", $payload + ['payment_method_id' => $stripe->id])->assertStatus(422);
    $this->postJson("http://egy.3alraf.test/webhooks/payments/{$stripe->id}", [])->assertStatus(501);
    expect(app(PaymentGatewayFactory::class)->make($stripe)->refund('x', '1.00')->status)->toBe('failed');
});

// ------------------------------------------------------------------ refunds

function deliveredOnline($test, $method, string $provider)
{
    $order = ($test->place)(2, ['paymentMethodId' => $method->id])['order'];
    $provider === 'paymob' ? paymobHook($order)->assertOk() : fawryHook($order)->assertOk();
    $order->refresh();
    $st = app(OrderStatusService::class);
    foreach ([S::CONFIRMED, S::PROCESSING, S::SHIPPED, S::DELIVERED] as $to) {
        $st->change($order, $to);
    }

    return $order->refresh();
}

it('refunds through Paymob and marks refunded only on provider success', function () {
    $order = deliveredOnline($this, $this->paymob, 'paymob');
    $rs = app(ReturnService::class);
    $item = $order->items->first();
    $ret = $rs->request($order, ReturnRequestData::fromArray(['items' => [$item->id => ['quantity' => 1]], 'restock' => true]));
    $rs->approve($ret);

    fakeHttp(['*/api/auth/tokens' => Http::response(['token' => 'AUTH']), '*/void_refund/refund' => Http::response(['success' => false, 'error_occured' => true], 200)]);
    expect(fn () => $rs->refund($ret->fresh()))->toThrow(ValidationException::class);
    $order->refresh();
    expect((string) $order->refunded_amount)->toBe('0.00')->and($order->payment_status)->toBe(PaymentStatus::PAID)->and($ret->fresh()->status->value)->toBe('approved');

    fakeHttp(['*/api/auth/tokens' => Http::response(['token' => 'AUTH']), '*/void_refund/refund' => Http::response(['success' => true, 'id' => 77])]);
    $rs->refund($ret->fresh(), '100.00');
    $order->refresh();
    expect((string) $order->refunded_amount)->toBe('100.00')->and($order->payment_status)->toBe(PaymentStatus::PARTIALLY_REFUNDED)->and($ret->fresh()->status->value)->toBe('refunded');
    Http::assertSent(fn ($r) => str_ends_with($r->url(), '/void_refund/refund') && $r['transaction_id'] === '5001' && $r['amount_cents'] === 10000);
});

it('refunds through FawryPay with the documented signature and keeps the order unrefunded on failure', function () {
    $order = deliveredOnline($this, $this->fawry, 'fawry');
    $rs = app(ReturnService::class);
    $ret = $rs->request($order, ReturnRequestData::fromArray(['items' => [$order->items->first()->id => ['quantity' => 1]], 'restock' => true]));
    $rs->approve($ret);
    fakeHttp(['*/payments/refund' => Http::response(['statusCode' => 9901, 'statusDescription' => 'nope'])]);
    expect(fn () => $rs->refund($ret->fresh()))->toThrow(ValidationException::class);
    expect((string) $order->fresh()->refunded_amount)->toBe('0.00');
    fakeHttp(['*/payments/refund' => Http::response(['statusCode' => 200, 'statusDescription' => 'ok'])]);
    $rs->refund($ret->fresh(), '100.00');
    expect((string) $order->fresh()->refunded_amount)->toBe('100.00');
    Http::assertSent(fn ($r) => str_ends_with($r->url(), '/payments/refund') && $r['referenceNumber'] === '9900123'
        && $r['signature'] === hash('sha256', 'MC1'.'9900123'.'100.00'.'Refund'.'fsecret'));
});

it('cancelling a paid online order refunds at the provider, and flags it when the provider fails', function () {
    $a = ($this->place)(1, ['paymentMethodId' => $this->paymob->id])['order'];
    paymobHook($a)->assertOk();
    fakeHttp(['*/api/auth/tokens' => Http::response(['token' => 'AUTH']), '*/void_refund/refund' => Http::response(['success' => false])]);
    app(OrderStatusService::class)->change($a->refresh(), S::CANCELLED);
    $a->refresh();
    expect($a->status)->toBe(S::CANCELLED)->and((string) $a->refunded_amount)->toBe('0.00')->and($a->payment_status)->toBe(PaymentStatus::PAID)
        ->and($a->histories()->where('note', __('orders.cancel_refund_failed_note'))->exists())->toBeTrue();
    $b = ($this->place)(1, ['paymentMethodId' => $this->fawry->id])['order'];
    fawryHook($b, ['fawryRefNumber' => '777'])->assertOk();
    fakeHttp(['*/payments/refund' => Http::response(['statusCode' => 200])]);
    app(OrderStatusService::class)->change($b->refresh(), S::CANCELLED);
    expect($b->fresh()->payment_status)->toBe(PaymentStatus::REFUNDED);
});

// ------------------------------------------------------------------ methods

it('toggles methods independently, keeps COD undeletable but deactivatable, and offers only active methods at checkout', function () {
    ($this->owner)();
    $this->postJson("{$this->t}/payment-methods/{$this->wallet->id}/toggle")->assertOk();
    expect($this->wallet->fresh()->is_active)->toBeFalse()->and($this->cod->fresh()->is_active)->toBeTrue();   // independent
    $this->postJson("{$this->s}/cart/items", ['product_id' => $this->product->id, 'variant_id' => 0, 'quantity' => 1])->assertOk();
    $this->get("{$this->s}/checkout")->assertOk()->assertViewHas('methods', fn ($m) => ! $m->contains('id', $this->wallet->id) && $m->contains('id', $this->cod->id));

    $this->deleteJson("{$this->t}/payment-methods/{$this->cod->id}")->assertStatus(422);
    expect(PaymentMethod::find($this->cod->id))->not->toBeNull();
    $this->postJson("{$this->t}/payment-methods/{$this->cod->id}/toggle")->assertOk();
    expect($this->cod->fresh()->is_active)->toBeFalse();
    $order = fn () => ($this->place)(1, ['paymentMethodId' => $this->cod->id]);
    expect($order)->toThrow(ValidationException::class);                                                    // inactive COD cannot be used
    // a second COD is refused; custom wallets (e& Cash / other) are accepted and always need proof
    $base = ['name' => ['ar' => 'x'], 'fee_percent' => 0, 'fee_fixed' => 0, 'fee_bearer' => 'customer'];
    $this->postJson("{$this->t}/payment-methods/validate", ['type' => 'cod', 'provider' => 'cod'] + $base)->assertStatus(422)->assertJsonValidationErrors(['type']);
    $this->postJson("{$this->t}/payment-methods", ['type' => 'wallet', 'provider' => 'etisalat_cash', 'account_number' => '0111', 'is_active' => 1] + $base)->assertOk();
    expect(PaymentMethod::where('provider', 'etisalat_cash')->first()->requires_proof)->toBeTrue();
    // every new tenant gets a COD method
    expect(PaymentMethod::ensureDefaultCod($this->tenant->id)->id)->toBe($this->cod->id);
});

// ------------------------------------------------------------------ COD split + proofs + profit

it('splits COD: prepaid shipping via wallet proof, cod_amount = total - shipping, confirm only after approval', function () {
    ($this->setUpfront)(true);
    expect(fn () => ($this->place)(2))->toThrow(ValidationException::class);                                      // prepay method required
    expect(fn () => ($this->place)(2, ['prepayMethodId' => $this->cod->id]))->toThrow(ValidationException::class); // COD cannot prepay itself
    $order = ($this->place)(2, ['prepayMethodId' => $this->wallet->id])['order'];
    expect((string) $order->shipping_net)->toBe('40.00')->and((string) $order->total)->toBe('240.00')->and((string) $order->prepaid_shipping)->toBe('40.00')
        ->and((string) $order->cod_amount)->toBe('200.00')->and($order->prepay_method_id)->toBe($this->wallet->id);
    expect(fn () => app(OrderStatusService::class)->change($order, S::CONFIRMED))->toThrow(ValidationException::class);   // shipping not paid yet
    $svc = app(OrderPaymentService::class);
    $svc->uploadProof($order, UploadedFile::fake()->image('p.png'));
    $svc->approveProof($order->fresh());
    $order->refresh();
    expect($order->payment_status)->toBe(PaymentStatus::PARTIALLY_PAID)->and((string) $order->paid_amount)->toBe('40.00')->and($order->due())->toBe('200.00')->and($order->status)->toBe(S::PENDING);
    app(OrderStatusService::class)->change($order, S::CONFIRMED);
    foreach ([S::PROCESSING, S::SHIPPED, S::DELIVERED] as $to) {
        app(OrderStatusService::class)->change($order, $to);
    }
    expect($order->fresh()->payment_status)->toBe(PaymentStatus::PAID)->and((string) $order->fresh()->paid_amount)->toBe('240.00');   // carrier delivered the COD cash

    ($this->setUpfront)(false);
    $full = ($this->place)(2)['order'];
    expect((string) $full->cod_amount)->toBe('240.00')->and((string) $full->prepaid_shipping)->toBe('0.00')->and($full->prepay_method_id)->toBeNull();
});

it('prepays COD shipping through Paymob for the shipping amount only', function () {
    ($this->setUpfront)(true);
    fakeHttp(['*/api/auth/tokens' => Http::response(['token' => 'A']), '*/api/ecommerce/orders' => Http::response(['id' => 1]), '*/api/acceptance/payment_keys' => Http::response(['token' => 'K'])]);
    $res = ($this->place)(2, ['prepayMethodId' => $this->paymob->id]);
    Http::assertSent(fn ($r) => str_ends_with($r->url(), '/api/ecommerce/orders') && $r['amount_cents'] === 4000);
    $order = $res['order'];
    paymobHook($order)->assertOk();                                          // webhook amount is checked against the 40.00 prepay, not the 240 total
    expect($order->fresh()->payment_status)->toBe(PaymentStatus::PARTIALLY_PAID)->and((string) $order->fresh()->paid_amount)->toBe('40.00');
    app(OrderStatusService::class)->change($order->fresh(), S::CONFIRMED);
    expect($order->fresh()->status)->toBe(S::CONFIRMED);
});

it('keeps proofs on the private disk, served only to tenant admins and the order token', function () {
    $order = ($this->place)(1, ['paymentMethodId' => $this->wallet->id])['order'];
    $bad = fn ($f) => $this->postJson("{$this->s}/order/{$order->public_token}/proof", ['proof' => $f]);
    $bad(UploadedFile::fake()->create('x.txt', 10, 'text/plain'))->assertStatus(422);
    $bad(UploadedFile::fake()->create('big.pdf', 6000, 'application/pdf'))->assertStatus(422);                        // > 5MB
    $bad(UploadedFile::fake()->createWithContent('ok.pdf', "%PDF-1.4\n% first\n%%EOF"))->assertOk();
    $order->refresh();
    Storage::disk('private')->assertExists($order->payment_proof);
    Storage::disk('media')->assertMissing($order->payment_proof);
    // re-uploading replaces: the previous proof file is deleted (no orphan on the private disk)
    $first = $order->payment_proof;
    $bad(UploadedFile::fake()->createWithContent('second.pdf', "%PDF-1.4\n% second\n%%EOF"))->assertOk();
    $order->refresh();
    expect($order->payment_proof)->not->toBe($first);
    Storage::disk('private')->assertMissing($first);
    Storage::disk('private')->assertExists($order->payment_proof);
    expect(Storage::disk('private')->allFiles())->toHaveCount(1);
    $this->get("{$this->s}/order/{$order->public_token}/proof")->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
    $this->get("{$this->s}/order/".Str::uuid().'/proof')->assertNotFound();
    $this->get("{$this->t}/orders/{$order->id}/proof")->assertRedirect();                                              // guests are sent to login
    ($this->owner)();
    $this->get("{$this->t}/orders/{$order->id}/proof")->assertOk();
    $this->get("{$this->t}/orders/{$order->id}")->assertOk()->assertSee("orders/{$order->id}/proof", false)->assertDontSee('/storage/payment-proofs');
    // an admin of ANOTHER tenant cannot read it
    $sa = Tenant::create(['slug' => 'sa', 'name' => 'Saudi', 'country' => 'SA', 'currency_code' => 'SAR', 'currency_symbol' => 'SAR', 'currency_rate' => 2, 'default_locale' => 'ar', 'locales' => ['ar'], 'timezone' => 'Asia/Riyadh', 'is_active' => true]);
    $other = TenantAdmin::withoutGlobalScopes()->create(['tenant_id' => $sa->id, 'name' => 'S', 'email' => 's@sa.test', 'password' => 'password', 'is_active' => true]);
    // the session guard re-resolves admins through the tenant scope: an admin of another tenant is nobody on this host
    expect(auth('tenant')->getProvider()->retrieveById($other->id))->toBeNull();
});

it('computes net_profit only when an admin confirms and reduces it on returns', function () {
    $order = ($this->place)(2)['order'];
    expect((string) $order->net_profit)->toBe('0.00');                                      // pending: never counts
    app(OrderStatusService::class)->change($order, S::CONFIRMED);
    $order->refresh();
    // goods 200 - cogs 120 + shipping_net 40 - shipping_cost 30 (carrier cost) = 90
    expect((string) $order->net_profit)->toBe('90.00');
    foreach ([S::PROCESSING, S::SHIPPED, S::DELIVERED] as $to) {
        app(OrderStatusService::class)->change($order, $to);
    }
    $rs = app(ReturnService::class);
    $ret = $rs->request($order->refresh(), ReturnRequestData::fromArray(['items' => [$order->items->first()->id => ['quantity' => 1]], 'restock' => true, 'return_shipping_cost' => '5']));
    $rs->approve($ret);
    expect((string) $order->fresh()->net_profit)->toBe('45.00');                            // 90 - (100 - 60) - 5 return shipping
    $rep = $this->actingAs(TenantAdmin::withoutGlobalScopes()->where('email', 'owner@egy.test')->first(), 'tenant')->get("{$this->t}/reports/sales")->assertOk()->viewData('data');
    expect(collect($rep['cards'])->firstWhere('label', __('reports.card.net_profit'))['value'])->toBe('45.00');
    $c = ($this->place)(1)['order'];
    app(OrderStatusService::class)->change($c, S::CONFIRMED);
    app(OrderStatusService::class)->change($c->refresh(), S::CANCELLED);
    expect((string) $c->fresh()->net_profit)->toBe('0.00');                                  // cancelled after confirmation: removed
});

it('reports COD carrier settlement per shipping company', function () {
    $o = ($this->place)(2)['order'];                                                         // cod_amount 240, carrier fee 30
    $st = app(OrderStatusService::class);
    foreach ([S::CONFIRMED, S::PROCESSING, S::SHIPPED] as $to) {
        $st->change($o, $to);
    }
    ($this->owner)();
    $data = $this->get("{$this->t}/reports/cod-settlement")->assertOk()->viewData('data');
    expect($data['rows'][0]['in_transit'])->toEqual('240.00')->and($data['rows'][0]['collected'])->toEqual('0.00');
    $st->change($o->refresh(), S::DELIVERED);
    $data = $this->get("{$this->t}/reports/cod-settlement")->viewData('data');
    expect($data['rows'][0]['collected'])->toEqual('240.00')->and($data['rows'][0]['carrier_fee'])->toEqual('30.00')->and($data['rows'][0]['owed'])->toEqual('210.00');
});

it('queues the admin notification listeners', function () {
    foreach ([NotifyAdminsOfNewOrder::class, NotifyAdminsOfPaymentProof::class, NotifyAdminsOfStatusChange::class] as $l) {
        expect(new $l)->toBeInstanceOf(ShouldQueue::class);
    }
});

// ------------------------------------------------------------------ COD upfront: the REAL default (no phpunit override)

it('defaults COD shipping prepay to UPFRONT when the tenant has no setting, honours an explicit false and the env fallback', function () {
    $this->tenant->update(['settings' => []]);                                      // pristine tenant: no cod_collect_shipping_upfront key
    expect($this->tenant->refresh()->codCollectsShippingUpfront())->toBeTrue()
        ->and(config('payments.cod_upfront_default'))->toBeTrue();                  // phpunit.xml no longer overrides it
    expect(fn () => ($this->place)(2))->toThrow(ValidationException::class);        // prepay method now required by default
    $o = ($this->place)(2, ['prepayMethodId' => $this->wallet->id])['order'];
    expect((string) $o->prepaid_shipping)->toBe('40.00')->and((string) $o->cod_amount)->toBe('200.00');

    codUpfront($this->tenant, false);                                               // explicit false: carrier collects the full total
    $full = ($this->place)(2)['order'];
    expect($this->tenant->refresh()->codCollectsShippingUpfront())->toBeFalse()
        ->and((string) $full->prepaid_shipping)->toBe('0.00')->and((string) $full->cod_amount)->toBe((string) $full->total);

    $this->tenant->update(['settings' => []]);                                      // env fallback is only used when the setting is absent
    config(['payments.cod_upfront_default' => false]);
    expect($this->tenant->refresh()->codCollectsShippingUpfront())->toBeFalse();
    expect((string) ($this->place)(2)['order']->cod_amount)->toBe('240.00');
    codUpfront($this->tenant, true);                                                // an explicit setting beats the env fallback
    expect($this->tenant->refresh()->codCollectsShippingUpfront())->toBeTrue();
});

// ------------------------------------------------------------------ refund shipping only once

it('refunds shipping only once when a second return also asks for refund_shipping', function () {
    $order = deliveredOnline($this, $this->paymob, 'paymob');                       // 2 x 100 + shipping 40 paid online
    $rs = app(ReturnService::class);
    $item = $order->items->first();
    $mk = function () use ($rs, $order, $item) {
        $r = $rs->request($order->fresh(), ReturnRequestData::fromArray(['items' => [$item->id => ['quantity' => 1]], 'restock' => true, 'refund_shipping' => true]));
        $rs->approve($r);

        return $r->fresh();
    };
    $r1 = $mk();
    $r2 = $mk();
    expect($rs->refundable($r1))->toBe('140.00');
    fakeHttp(['*/api/auth/tokens' => Http::response(['token' => 'AUTH']), '*/void_refund/refund' => Http::response(['success' => true, 'id' => 77])]);
    $rs->refund($r1);
    expect($rs->refundable($r2->fresh()))->toBe('100.00');                           // shipping already refunded with the first return
    $rs->refund($r2->fresh());
    expect((string) $order->fresh()->refunded_amount)->toBe('240.00');
    Http::assertSent(fn ($r) => str_ends_with($r->url(), '/void_refund/refund') && $r['amount_cents'] === 14000);
    Http::assertSent(fn ($r) => str_ends_with($r->url(), '/void_refund/refund') && $r['amount_cents'] === 10000);
});

it('releases the unique code / e-mail of soft-deleted discount codes and customers', function () {
    $mk = fn () => DiscountCode::create(['code' => 'SAVE10', 'type' => 'percent', 'value' => 10, 'scope' => 'all', 'is_active' => true]);
    $mk()->delete();
    $again = $mk();                                                                  // would violate unique(tenant_id, code) without the tombstone
    expect($again->exists)->toBeTrue()->and(DiscountCode::withTrashed()->where('code', 'SAVE10')->count())->toBe(1);
    $c = Customer::create(['name' => 'A', 'email' => 'dup@x.test', 'password' => 'secret123', 'is_active' => true]);
    $c->delete();
    expect(Customer::create(['name' => 'B', 'email' => 'dup@x.test', 'password' => 'secret123', 'is_active' => true])->exists)->toBeTrue();
});
