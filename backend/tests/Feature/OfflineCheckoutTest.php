<?php

use App\DTOs\Orders\CheckoutData;
use App\Enums\PaymentStatus;
use App\Enums\ProofStatus;
use App\Enums\StockMovementType as T;
use App\Models\Branch;
use App\Models\City;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ShippingCompany;
use App\Models\Tenant;
use App\Models\TenantProduct;
use App\Repositories\Contracts\OrderRepositoryInterface;
use App\Services\Orders\OfflineCheckoutThrottle;
use App\Services\Orders\OrderPaymentService;
use App\Services\Orders\OrderPlacementService;
use App\Services\Stock\StockLedger;
use Database\Seeders\FoundationSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Cache::flush();
    $this->seed(FoundationSeeder::class);
    Storage::fake('private');
    Storage::fake('media');
    $this->tenant = Tenant::where('slug', 'egy')->first();
    codUpfront($this->tenant, false);
    app()->instance('currentTenant', $this->tenant);
    $this->s = 'http://egy.3alraf.test/ar';
    $this->api = 'http://egy.3alraf.test/api/v1';
    $this->hdr = 'http://3alraf.test/api/v1';
    $this->product = Product::create(['type' => 'simple', 'name' => ['ar' => 'منتج'], 'slug' => 'oc1', 'sku' => 'OC1', 'barcode' => '2000000000077']);
    TenantProduct::create(['product_id' => $this->product->id, 'price' => 100, 'cost' => 60, 'is_active' => true]);
    app(StockLedger::class)->record(Branch::first()->id, $this->product->id, 0, T::PURCHASE_IN, 500, '60.00');
    $this->city = City::first();
    $this->company = ShippingCompany::first();
    $this->cod = PaymentMethod::where('type', 'cod')->first();
    $this->wallet = PaymentMethod::where('type', 'wallet')->first();
    config(['checkout.offline.ip_attempts' => 1000, 'checkout.offline.ip_daily' => 1000, 'checkout.offline.contact_attempts' => 1000, 'checkout.offline.open_cap' => 1000]);
    $this->n = 0;
    // distinct bytes per call: identical files would (rightly) be rejected as a reused proof
    $this->proof = function () { $this->n++; return UploadedFile::fake()->image("p{$this->n}.png", 10 + $this->n, 12); };
    $this->payload = fn (array $o = []) => array_merge([
        'name' => 'Ali', 'phone' => '0100'.random_int(1000000, 9999999), 'email' => 'u'.random_int(1, 9999999).'@x.test', 'city_id' => $this->city->id, 'address' => 'St 1',
        'shipping_company_id' => $this->company->id, 'payment_method_id' => $this->wallet->id,
    ], $o);
    $this->webCart = fn (int $q = 1) => $this->postJson("{$this->s}/cart/items", ['product_id' => $this->product->id, 'variant_id' => 0, 'quantity' => $q])->assertOk();
    $this->webPost = fn (string $path, array $data) => $this->withHeader('Accept', 'application/json')->post("{$this->s}/$path", $data);
    $this->place = fn (array $o = []) => app(OrderPlacementService::class)->place(new CheckoutData(...array_merge([
        'name' => 'Ali', 'phone' => '0100', 'email' => 'ali@x.test', 'cityId' => $this->city->id, 'address' => 'St 1',
        'shippingCompanyId' => $this->company->id, 'paymentMethodId' => $this->wallet->id,
    ], $o)), [['product_id' => $this->product->id, 'variant_id' => 0, 'qty' => 1]]);
});

it('web: wallet checkout without a proof file is a 422 on both pre-validate and store', function () {
    ($this->webCart)();
    ($this->webPost)('checkout/validate', ($this->payload)())->assertStatus(422)->assertJsonValidationErrors(['proof']);
    ($this->webPost)('checkout', ($this->payload)())->assertStatus(422)->assertJsonValidationErrors(['proof']);
    expect(Order::count())->toBe(0);
});

it('web: rejects bad mime, fake pdf, oversize and non-decoding images', function () {
    ($this->webCart)();
    foreach ([UploadedFile::fake()->create('x.exe', 10), UploadedFile::fake()->createWithContent('a.pdf', 'not really a pdf'), UploadedFile::fake()->create('big.jpg', 6000, 'image/jpeg'),
        UploadedFile::fake()->createWithContent('b.png', 'definitely not a png')] as $bad) {
        ($this->webPost)('checkout', ($this->payload)() + ['proof' => $bad])->assertStatus(422)->assertJsonValidationErrors(['proof']);
    }
    // extension says pdf, bytes are an HTML page => the real sniff refuses it even if the FormRequest mime guess were fooled
    expect(fn () => ($this->place)(['proof' => UploadedFile::fake()->createWithContent('a.pdf', '<html><script>alert(1)</script></html>')]))->toThrow(ValidationException::class);
    expect(Order::count())->toBe(0)->and(Storage::disk('private')->allFiles())->toBe([]);
});

it('web: a valid proof creates a pending / review order with a hashed random private file; pre-validate stores nothing', function () {
    ($this->webCart)();
    $file = ($this->proof)();
    ($this->webPost)('checkout/validate', ($this->payload)() + ['proof' => $file])->assertOk();
    expect(Storage::disk('private')->allFiles())->toBe([]);

    $file = ($this->proof)();
    $res = ($this->webPost)('checkout', ($this->payload)() + ['proof' => $file])->assertOk();
    $o = Order::where('number', $res->json('data.number'))->first();
    expect($o->proof_status)->toBe(ProofStatus::PENDING)->and($o->payment_status)->toBe(PaymentStatus::REVIEW)
        ->and($o->payment_proof_hash)->toBe(hash_file('sha256', $file->getRealPath()))->and($o->payment_proof)->toStartWith("tenants/{$this->tenant->id}/payment-proofs/")->toEndWith('.png');
    Storage::disk('private')->assertExists($o->payment_proof);
    expect(basename($o->payment_proof))->not->toContain('p'.$this->n.'.');   // client name never reused
});

it('COD alone is unaffected (no proof needed, a stray file is ignored) while a wallet prepay leg on COD needs one', function () {
    ($this->webCart)();
    $res = ($this->webPost)('checkout', ($this->payload)(['payment_method_id' => $this->cod->id]))->assertOk();
    $o = Order::where('number', $res->json('data.number'))->first();
    expect($o->payment_proof)->toBeNull()->and($o->payment_status)->toBe(PaymentStatus::UNPAID);

    ($this->webCart)();
    $res = ($this->webPost)('checkout', ($this->payload)(['payment_method_id' => $this->cod->id]) + ['proof' => ($this->proof)()])->assertOk();
    expect(Order::where('number', $res->json('data.number'))->value('payment_proof'))->toBeNull()->and(Storage::disk('private')->allFiles())->toBe([]);

    codUpfront($this->tenant, true);
    ($this->webCart)();
    $prepay = ($this->payload)(['payment_method_id' => $this->cod->id, 'prepay_method_id' => $this->wallet->id]);
    ($this->webPost)('checkout/validate', $prepay)->assertStatus(422)->assertJsonValidationErrors(['proof']);
    ($this->webPost)('checkout', $prepay)->assertStatus(422)->assertJsonValidationErrors(['proof']);
    $res = ($this->webPost)('checkout', $prepay + ['proof' => ($this->proof)()])->assertOk();
    $o = Order::where('number', $res->json('data.number'))->first();
    expect($o->prepay_method_id)->toBe($this->wallet->id)->and($o->proof_status)->toBe(ProofStatus::PENDING)->and($o->payment_status)->toBe(PaymentStatus::REVIEW);
});

it('the service layer itself refuses an offline placement without proof (not only the FormRequest)', function () {
    expect(fn () => ($this->place)())->toThrow(ValidationException::class);
    codUpfront($this->tenant, true);
    expect(fn () => ($this->place)(['paymentMethodId' => $this->cod->id, 'prepayMethodId' => $this->wallet->id]))->toThrow(ValidationException::class);
    expect(Order::count())->toBe(0);
    expect(($this->place)(['proof' => ($this->proof)()])['order']->proof_status)->toBe(ProofStatus::PENDING);
});

it('rejects a reused proof (checkout + re-upload) unless the earlier order was rejected or cancelled', function () {
    $bytes = ($this->proof)();
    $a = ($this->place)(['proof' => $bytes])['order'];
    $same = fn () => UploadedFile::fake()->createWithContent('again.png', file_get_contents($bytes->getRealPath()));

    ($this->webCart)();
    ($this->webPost)('checkout', ($this->payload)() + ['proof' => $same()])->assertStatus(422)->assertJsonValidationErrors(['proof']);
    ($this->webPost)('checkout/validate', ($this->payload)() + ['proof' => $same()])->assertStatus(422)->assertJsonValidationErrors(['proof']);

    $b = ($this->place)(['proof' => ($this->proof)(), 'phone' => '0111'])['order'];
    $this->postJson("{$this->s}/order/{$b->public_token}/proof", ['proof' => $same()])->assertStatus(422)->assertJsonValidationErrors(['proof']);
    expect($b->fresh()->payment_proof_hash)->not->toBe($a->payment_proof_hash);

    app(OrderPaymentService::class)->rejectProof($a->fresh());          // rejected => its hash is free again
    ($this->webPost)('checkout/validate', ($this->payload)() + ['proof' => $same()])->assertOk();
    $r = $this->withHeader('Accept', 'application/json')->post("{$this->s}/order/{$b->public_token}/proof", ['proof' => $same()])->assertOk();
    expect($b->fresh()->payment_proof_hash)->toBe($a->payment_proof_hash);
});

it('a failed placement deletes the stored proof file', function () {
    $repo = Mockery::mock(OrderRepositoryInterface::class);
    $repo->shouldReceive('create')->andThrow(new RuntimeException('db down'));
    $this->app->instance(OrderRepositoryInterface::class, $repo);
    expect(fn () => ($this->place)(['proof' => ($this->proof)()]))->toThrow(RuntimeException::class);
    expect(Storage::disk('private')->allFiles())->toBe([]);
});

it('api: same rules (422 without file, order created with file, COD unaffected, pre-validate stores nothing)', function () {
    $cartHeaders = fn () => ['X-Cart-Token' => $this->postJson("{$this->api}/cart/items", ['product_id' => $this->product->id, 'variant_id' => 0, 'quantity' => 1])->headers->get('X-Cart-Token'), 'Accept' => 'application/json'];
    $h = $cartHeaders();
    $post = function ($path, $data) use (&$h) { return $this->post("{$this->api}/$path", $data, $h); };
    $post('checkout/validate', ($this->payload)())->assertStatus(422)->assertJsonStructure(['success', 'message', 'errors' => ['proof']]);
    $post('checkout', ($this->payload)())->assertStatus(422)->assertJsonStructure(['errors' => ['proof']]);
    $post('checkout/validate', ($this->payload)() + ['proof' => ($this->proof)()])->assertOk();
    expect(Storage::disk('private')->allFiles())->toBe([]);
    $r = $post('checkout', ($this->payload)() + ['proof' => ($this->proof)()])->assertCreated()->assertJsonPath('data.payment_status', 'pending_review');
    expect(json_encode($r->json()))->not->toContain('payment-proofs');
    $o = Order::where('public_token', $r->json('data.token'))->first();
    expect($o->proof_status)->toBe(ProofStatus::PENDING)->and($o->payment_proof_hash)->toHaveLength(64);

    $h = $cartHeaders();
    $post('checkout', ($this->payload)(['payment_method_id' => $this->cod->id]))->assertCreated();
});

// ------------------------------------------------------------------ limiter tiers

it('limiter ip tier: the 3rd attempt in the window is a 429 with Retry-After, counted even when validation fails', function () {
    config(['checkout.offline.ip_attempts' => 2]);
    ($this->webCart)();
    foreach (range(1, 2) as $i) {
        ($this->webPost)('checkout', ($this->payload)())->assertStatus(422);            // no file: still counted
    }
    $r = ($this->webPost)('checkout', ($this->payload)())->assertStatus(429);
    expect((int) $r->headers->get('Retry-After'))->toBeGreaterThan(0)->and($r->json('message'))->not->toBeEmpty();
    ($this->webPost)('checkout', ($this->payload)(['payment_method_id' => $this->cod->id]))->assertStatus(200);   // COD is not offline => never limited
});

it('limiter daily ip tier', function () {
    config(['checkout.offline.ip_daily' => 2]);
    foreach (range(1, 2) as $i) {
        ($this->webPost)('checkout', ($this->payload)())->assertStatus(422);
    }
    ($this->webPost)('checkout', ($this->payload)())->assertStatus(429)->assertHeader('Retry-After');
});

it('limiter phone tier survives formatting variants', function () {
    config(['checkout.offline.contact_attempts' => 2]);
    foreach (['0100 111 2222', '+20 100-111-2222'] as $phone) {
        ($this->webPost)('checkout', ($this->payload)(['phone' => $phone]))->assertStatus(422);
    }
    ($this->webPost)('checkout', ($this->payload)(['phone' => '(0100)1112222']))->assertStatus(429);
    ($this->webPost)('checkout', ($this->payload)(['phone' => '0155 000 0000']))->assertStatus(422);   // another person is fine
    expect(OfflineCheckoutThrottle::phoneKey('+20 100-111-2222'))->toBe(OfflineCheckoutThrottle::phoneKey('01001112222'));
});

it('limiter e-mail tier survives case, +tag and gmail-dot variants', function () {
    config(['checkout.offline.contact_attempts' => 2]);
    foreach (['A.b+x@Gmail.com', 'ab@gmail.com'] as $mail) {
        ($this->webPost)('checkout', ($this->payload)(['email' => $mail]))->assertStatus(422);
    }
    ($this->webPost)('checkout', ($this->payload)(['email' => ' a.B+zzz@googlemail.com ']))->assertStatus(429);
});

it('limiter open-order cap: too many offline orders still awaiting review for one phone', function () {
    config(['checkout.offline.open_cap' => 2]);
    foreach ([1, 2] as $i) {
        ($this->place)(['proof' => ($this->proof)(), 'phone' => '+20 100 111 2222', 'email' => "o$i@x.test"]);
    }
    ($this->webCart)();
    $r = ($this->webPost)('checkout', ($this->payload)(['phone' => '01001112222']) + ['proof' => ($this->proof)()])->assertStatus(429);
    expect((int) $r->headers->get('Retry-After'))->toBeGreaterThan(0);
    // once one is reviewed (approved), a new order is possible again
    app(OrderPaymentService::class)->approveProof(Order::first());
    ($this->webPost)('checkout', ($this->payload)(['phone' => '01001112222']) + ['proof' => ($this->proof)()])->assertOk();
});

it('limiter keys cannot be dodged through the X-Tenant header and the api answers with the envelope + Retry-After', function () {
    config(['checkout.offline.ip_attempts' => 2]);
    $post = fn ($tenantHeader) => $this->withHeaders(['X-Tenant' => $tenantHeader, 'Accept' => 'application/json'])->post("{$this->hdr}/checkout", ($this->payload)());
    $post('egy')->assertStatus(422);
    $post('egy')->assertStatus(422);
    $post('no-such-tenant')->assertNotFound();           // unknown tenants never reach (or mint) an offline bucket
    $r = $post('egy');                                   // same resolved tenant, same bucket
    $r->assertStatus(429)->assertJsonStructure(['success', 'message', 'data', 'meta'])->assertJsonPath('success', false);
    expect((int) $r->headers->get('Retry-After'))->toBeGreaterThan(0);
    // the tenant-subdomain twin shares the bucket as well
    $this->withHeader('Accept', 'application/json')->post("{$this->api}/checkout", ($this->payload)())->assertStatus(429);
});

it('the proof re-upload route has its own stricter limiter', function () {
    config(['checkout.reupload.attempts' => 2]);
    $o = ($this->place)(['proof' => ($this->proof)()])['order'];
    $other = ($this->place)(['proof' => ($this->proof)(), 'phone' => '0122'])['order'];
    foreach (range(1, 2) as $i) {
        $this->postJson("{$this->s}/order/{$o->public_token}/proof", [])->assertStatus(422);
    }
    $r = $this->postJson("{$this->s}/order/{$o->public_token}/proof", [])->assertStatus(429);
    expect((int) $r->headers->get('Retry-After'))->toBeGreaterThan(0);
    $this->postJson("{$this->s}/order/{$other->public_token}/proof", [])->assertStatus(422);        // per order token
    $this->postJson("{$this->api}/orders/{$o->public_token}/proof", [])->assertStatus(429)->assertJsonPath('success', false);
});
