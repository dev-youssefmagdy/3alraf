<?php

// Mobile API gap closure (docs/mobile-app/PLAN.md section 2): blog, tag/brand/category pages, stock requests, launch, ambassador, invoice/proof,
// store info, tenant directory, account deletion, devices, app-config.

use App\Models\Affiliate;
use App\Models\BlogPost;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Category;
use App\Models\City;
use App\Models\Customer;
use App\Models\Device;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ShippingCompany;
use App\Models\SmallBatchTest;
use App\Models\StockRequest;
use App\Models\Tag;
use App\Models\Tenant;
use App\Models\TenantProduct;
use App\Enums\StockMovementType as T;
use App\Services\Growth\GrowthAccess;
use App\Services\Growth\GrowthFeatures;
use App\Services\Stock\StockLedger;
use Database\Seeders\FoundationSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Cache::flush();
    $this->seed(FoundationSeeder::class);
    Storage::fake('private');
    Storage::fake('media');
    $this->tenant = Tenant::where('slug', 'egy')->first();
    codUpfront($this->tenant, false);
    app()->instance('currentTenant', $this->tenant);
    $this->api = 'http://egy.3alraf.test/api/v1';
    $this->hdr = 'http://3alraf.test/api/v1';
    $this->sa = Tenant::create(['slug' => 'sa', 'name' => 'KSA', 'country' => 'SA', 'currency_code' => 'SAR', 'currency_symbol' => 'SAR', 'currency_rate' => 1, 'default_locale' => 'ar', 'locales' => ['ar', 'en'], 'timezone' => 'Asia/Riyadh', 'is_active' => true]);
    $this->customer = Customer::where('email', 'customer@egy.test')->first();
    $this->auth = fn () => ['Authorization' => 'Bearer '.$this->customer->createToken('t', ['customer'])->plainTextToken];
    $this->cat = Category::create(['name' => ['ar' => 'أجهزة', 'en' => 'Devices'], 'description' => ['ar' => 'وصف', 'en' => 'Desc'], 'slug' => 'devices', 'path' => 'x', 'depth' => 0, 'is_active' => true]);
    $this->cat->update(['path' => (string) $this->cat->id]);
    $this->child = Category::create(['parent_id' => $this->cat->id, 'name' => ['ar' => 'هواتف', 'en' => 'Phones'], 'slug' => 'phones', 'path' => 'x', 'depth' => 1, 'is_active' => true]);
    $this->child->update(['path' => $this->cat->id.'/'.$this->child->id]);
    $this->sony = Brand::create(['name' => ['ar' => 'سوني', 'en' => 'Sony'], 'slug' => 'sony', 'is_active' => true]);
    $this->lg = Brand::create(['name' => ['ar' => 'ال جي', 'en' => 'LG'], 'slug' => 'lg', 'is_active' => true]);
    $this->tag = Tag::create(['name' => ['ar' => 'جديد', 'en' => 'New'], 'slug' => 'new', 'is_active' => true]);
    $branch = Branch::first();
    $this->mk = function (string $slug, Brand $brand) use ($branch) {
        $p = Product::create(['type' => 'simple', 'name' => ['ar' => "منتج $slug", 'en' => "P $slug"], 'slug' => $slug, 'sku' => strtoupper($slug), 'barcode' => app(\App\Services\BarcodeService::class)->generateUnique(), 'brand_id' => $brand->id]);
        $p->categories()->attach($this->cat->id);
        TenantProduct::create(['product_id' => $p->id, 'price' => 100, 'cost' => 60, 'is_active' => true]);
        app(StockLedger::class)->record($branch->id, $p->id, 0, T::PURCHASE_IN, 50, '60.00');

        return $p;
    };
    $this->p1 = ($this->mk)('sonyx', $this->sony);
    $this->p2 = ($this->mk)('lgx', $this->lg);
    $this->p1->tags()->attach($this->tag->id);
    $this->growthOn = function () {
        app(GrowthAccess::class)->set($this->tenant, true);
        app(GrowthFeatures::class)->enableAll($this->tenant);
        app()->instance('currentTenant', $this->tenant->refresh());
    };
});

function ok($r)
{
    return $r->assertJsonStructure(['success', 'message', 'data', 'meta'])->assertJsonPath('success', true);
}

// ------------------------------------------------------------------ blog

it('serves published blog posts only, per tenant, paginated, with content on the detail', function () {
    BlogPost::create(['slug' => 'hello', 'title' => ['ar' => 'مرحبا', 'en' => 'Hello'], 'excerpt' => ['en' => 'ex'], 'content' => ['ar' => '<p>نص</p>', 'en' => '<p>Body</p>'], 'is_active' => true, 'published_at' => now()->subDay()]);
    BlogPost::create(['slug' => 'draft', 'title' => ['en' => 'Draft'], 'content' => ['en' => 'x'], 'is_active' => false]);
    BlogPost::create(['slug' => 'future', 'title' => ['en' => 'Future'], 'content' => ['en' => 'x'], 'is_active' => true, 'published_at' => now()->addDay()]);
    BlogPost::create(['tenant_id' => $this->sa->id, 'slug' => 'ksa-only', 'title' => ['en' => 'KSA'], 'content' => ['en' => 'x'], 'is_active' => true, 'published_at' => now()->subDay()]);

    $r = ok($this->getJson("{$this->api}/blog", ['Accept-Language' => 'en']))->assertJsonCount(1, 'data')->assertJsonPath('data.0.slug', 'hello')->assertJsonPath('data.0.title', 'Hello')->assertJsonPath('meta.pagination.total', 1);
    expect($r->json('data.0'))->not->toHaveKey('content');
    ok($this->getJson("{$this->api}/blog/hello", ['Accept-Language' => 'en']))->assertJsonPath('data.content', '<p>Body</p>');
    $this->getJson("{$this->api}/blog/draft")->assertNotFound()->assertJsonPath('message', 'Not found.');   // no model class names in the message
    $this->getJson("{$this->api}/blog/future")->assertNotFound();
    $this->getJson("{$this->api}/blog/ksa-only")->assertNotFound();   // other tenant's post
    ok($this->getJson("{$this->hdr}/blog", ['X-Tenant' => 'egy']))->assertJsonCount(1, 'data');
});

// ------------------------------------------------------------------ catalog meta + filters

it('adds brand, tag and category pages and the brand/tag catalog filters', function () {
    ok($this->getJson("{$this->api}/brands/sony", ['Accept-Language' => 'en']))->assertJsonPath('data.name', 'Sony')->assertJsonPath('data.products_count', 1);
    $this->getJson("{$this->api}/brands/ghost")->assertNotFound();
    ok($this->getJson("{$this->api}/tags/new", ['Accept-Language' => 'en']))->assertJsonPath('data.slug', 'new');
    $this->getJson("{$this->api}/tags/ghost")->assertNotFound();
    $c = ok($this->getJson("{$this->api}/categories/phones", ['Accept-Language' => 'en']))->assertJsonPath('data.name', 'Phones')->assertJsonPath('data.parent_id', $this->cat->id)
        ->assertJsonPath('data.breadcrumbs.0.slug', 'devices')->assertJsonPath('data.breadcrumbs.1.slug', 'phones')->assertJsonPath('data.children', []);
    ok($this->getJson("{$this->api}/categories/devices", ['Accept-Language' => 'en']))->assertJsonPath('data.description', 'Desc')->assertJsonPath('data.children.0.slug', 'phones');
    $this->getJson("{$this->api}/categories/ghost")->assertNotFound();
    Category::where('slug', 'phones')->update(['is_active' => false]);
    $this->getJson("{$this->api}/categories/phones")->assertNotFound();

    $this->getJson("{$this->api}/catalog?brand=sony")->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.slug', 'sonyx');
    $this->getJson("{$this->api}/catalog?brand=lg")->assertJsonCount(1, 'data')->assertJsonPath('data.0.slug', 'lgx');
    $this->getJson("{$this->api}/catalog?tag=new")->assertJsonCount(1, 'data')->assertJsonPath('data.0.slug', 'sonyx');
    $this->getJson("{$this->api}/catalog?tag=new&brand=lg")->assertJsonCount(0, 'data');
    $this->getJson("{$this->api}/catalog?brand=ghost")->assertNotFound();
    $this->getJson("{$this->api}/catalog?tag=ghost")->assertNotFound();
    $this->getJson("{$this->api}/catalog/facets?brand=sony")->assertOk()->assertJsonCount(1, 'data.brands');
});

// ------------------------------------------------------------------ stock requests

it('stores a back-in-stock request, de-duplicates it and validates', function () {
    $body = ['product_id' => $this->p1->id, 'name' => 'Ali', 'phone' => '01000000111'];
    ok($this->postJson("{$this->api}/stock-requests", $body))->assertCreated();
    $this->postJson("{$this->api}/stock-requests", $body)->assertCreated();
    expect(StockRequest::count())->toBe(1);
    Cache::flush();   // stock-requests limiter: 3 / minute
    $this->postJson("{$this->api}/stock-requests", [])->assertStatus(422)->assertJsonStructure(['errors' => ['product_id', 'name', 'phone']]);
    $this->postJson("{$this->api}/stock-requests", ['product_id' => 999999] + $body)->assertStatus(422);
    // signed-in customer is linked
    Cache::flush();
    $this->postJson("{$this->api}/stock-requests", ['phone' => '01000000112'] + $body, ($this->auth)())->assertCreated();
    expect(StockRequest::where('phone', '01000000112')->value('customer_id'))->toBe($this->customer->id);
});

// ------------------------------------------------------------------ small-batch launch

it('serves the small-batch launch page behind its feature flag, per tenant, and signs up', function () {
    $mkTest = fn (array $o = []) => SmallBatchTest::create($o + ['code' => 'abcd1234', 'name' => 'Test', 'mode' => 'interest', 'description' => 'd', 'status' => 'running', 'preorder' => false]);
    $mkTest();
    $this->getJson("{$this->api}/launch/abcd1234")->assertStatus(403)->assertJsonPath('error', 'feature_disabled');
    ($this->growthOn)();

    ok($this->getJson("{$this->api}/launch/abcd1234"))->assertJsonPath('data.name', 'Test')->assertJsonPath('data.is_open', true)->assertJsonPath('data.preorder_terms', null);
    $this->getJson("{$this->api}/launch/zzzzzzzz")->assertNotFound();
    $mkTest(['code' => 'draft123', 'status' => 'draft']);
    $this->getJson("{$this->api}/launch/draft123")->assertNotFound();
    $mkTest(['code' => 'ksa12345', 'tenant_id' => $this->sa->id]);
    $this->getJson("{$this->api}/launch/ksa12345")->assertNotFound();   // tenant isolation

    $this->postJson("{$this->api}/launch/abcd1234", [])->assertStatus(422)->assertJsonStructure(['errors' => ['contact', 'consent']]);
    ok($this->postJson("{$this->api}/launch/abcd1234", ['contact' => '01000000555', 'consent' => true]))->assertCreated()->assertJsonPath('data.created', true);
    ok($this->postJson("{$this->api}/launch/abcd1234", ['contact' => '01000000555', 'consent' => true]))->assertOk()->assertJsonPath('data.created', false);   // idempotent
    SmallBatchTest::where('code', 'abcd1234')->update(['status' => 'closed']);
    $this->postJson("{$this->api}/launch/abcd1234", ['contact' => '01000000666', 'consent' => true])->assertStatus(422);
});

// ------------------------------------------------------------------ ambassador

it('lets an approved affiliate join the ambassador program, create links and read the report (feature gated)', function () {
    $aff = Affiliate::where('email', 'aff@egy.test')->first();
    $h = ['Authorization' => 'Bearer '.$aff->createToken('t', ['affiliate'])->plainTextToken];
    $this->getJson("{$this->api}/affiliate/ambassador", $h)->assertStatus(403);   // flag off
    ($this->growthOn)();

    $this->getJson("{$this->api}/affiliate/ambassador")->assertUnauthorized();
    $this->getJson("{$this->api}/affiliate/ambassador", ($this->auth)())->assertUnauthorized();   // customer token is no affiliate token
    ok($this->getJson("{$this->api}/affiliate/ambassador", $h))->assertJsonPath('data.enrolled', false)->assertJsonPath('data.report', null)->assertJsonPath('data.channels.0', 'instagram');
    $this->postJson("{$this->api}/affiliate/ambassador/links", ['product_id' => $this->p1->id, 'channel' => 'instagram'], $h)->assertNotFound();   // not enrolled
    $this->postJson("{$this->api}/affiliate/ambassador/join", [], $h)->assertStatus(422)->assertJsonStructure(['errors' => ['disclosure', 'terms_accepted']]);
    ok($this->postJson("{$this->api}/affiliate/ambassador/join", ['disclosure' => 'Paid partner', 'terms_accepted' => true], $h))->assertCreated()->assertJsonPath('data.status', 'active');

    $l = ok($this->postJson("{$this->api}/affiliate/ambassador/links", ['product_id' => $this->p1->id, 'channel' => 'instagram'], $h))->assertCreated()->assertJsonPath('data.channel', 'instagram');
    expect($l->json('data.url'))->toContain('egy.3alraf.test/l/');
    $this->postJson("{$this->api}/affiliate/ambassador/links", ['product_id' => 999999, 'channel' => 'instagram'], $h)->assertStatus(422);
    $s = ok($this->getJson("{$this->api}/affiliate/ambassador", $h))->assertJsonPath('data.enrolled', true)->assertJsonCount(1, 'data.links');
    expect($s->json('data.report'))->toHaveKeys(['valid_orders', 'pending_orders', 'window_days'])->not->toHaveKey('margin_after_rewards')->not->toHaveKey('commission_cost');
});

// ------------------------------------------------------------------ invoice + proof file

it('streams the order invoice PDF and the uploaded proof by public token', function () {
    $h = ['X-Cart-Token' => $this->postJson("{$this->api}/cart/items", ['product_id' => $this->p1->id, 'variant_id' => 0, 'quantity' => 1])->headers->get('X-Cart-Token')];
    $body = ['name' => 'Ali', 'phone' => '01000000100', 'city_id' => City::first()->id, 'address' => 'St 1', 'shipping_company_id' => ShippingCompany::first()->id, 'payment_method_id' => PaymentMethod::where('type', 'cod')->first()->id];
    $token = $this->postJson("{$this->api}/checkout", $body, $h)->assertCreated()->json('data.token');

    $r = $this->get("{$this->api}/orders/$token/invoice")->assertOk();
    expect($r->headers->get('Content-Type'))->toContain('application/pdf')->and($r->headers->get('Content-Disposition'))->toContain('attachment');
    $this->get("{$this->api}/orders/nope/invoice")->assertNotFound();
    $this->get("http://sa.3alraf.test/api/v1/orders/$token/invoice")->assertNotFound();   // other tenant cannot read it

    $this->getJson("{$this->api}/orders/$token/proof")->assertNotFound();   // nothing uploaded yet
    $order = Order::where('public_token', $token)->first();
    Storage::disk('private')->put('payment-proofs/x.png', UploadedFile::fake()->image('p.png')->getContent());
    $order->update(['payment_proof' => 'payment-proofs/x.png']);
    $p = $this->get("{$this->api}/orders/$token/proof")->assertOk();
    expect($p->headers->get('Cache-Control'))->toContain('no-store');
});

// ------------------------------------------------------------------ store info + tenant directory + app config

it('returns branding, contact, social, footer pages and app links in the store payload', function () {
    $this->tenant->update(['settings' => array_merge($this->tenant->settings ?? [], [
        'seo' => ['org_phone' => '+20 100 000 0000', 'org_email' => 'hi@egy.test', 'social' => ['instagram' => 'https://instagram.com/x']],
        'app_links' => ['ios' => 'https://apps.apple.com/x', 'android' => ''],
        'branding' => ['colors' => ['primary' => '#112233', 'accent' => 'javascript:evil']],
    ])]);
    Cache::flush();
    $d = ok($this->getJson("{$this->api}/store"))->assertJsonPath('data.slug', 'egy')->assertJsonPath('data.colors.primary', '#112233')->assertJsonPath('data.colors.accent', '#D7B987')
        ->assertJsonPath('data.contact.whatsapp', '201000000000')->assertJsonPath('data.contact.email', 'hi@egy.test')->assertJsonPath('data.social.instagram', 'https://instagram.com/x')
        ->assertJsonPath('data.app_links.ios', 'https://apps.apple.com/x')->assertJsonPath('data.app_links.android', null)->assertJsonPath('data.features.ambassadors', false)->json('data');
    expect($d['logo_url'])->toContain('logo.png')->and($d['footer'])->toHaveKeys(['pages', 'has_blog', 'payment_methods'])->and($d['footer']['pages'])->not->toBeEmpty()->and($d['colors'])->toHaveKey('danger');
});

it('lists active tenants with flag, languages and logo and serves app-config', function () {
    $e = collect($this->getJson('http://3alraf.test/api/v1/tenants')->assertOk()->assertJsonPath('success', true)->json('data'))->firstWhere('slug', 'egy');
    expect($e['flag'])->not->toBe('')->and($e['languages'])->toBeArray()->not->toBeEmpty()->and($e['logo_url'])->toContain('logo.png');

    config(['mobile.min_version' => '1.2.0', 'mobile.latest_version' => '1.4.0', 'mobile.store_urls.android' => 'https://play.google.com/x']);
    $c = $this->getJson('http://3alraf.test/api/v1/app-config?version=1.1.0')->assertOk()->assertJsonPath('success', true)->assertJsonPath('data.min_version', '1.2.0')
        ->assertJsonPath('data.store_urls.android', 'https://play.google.com/x')->assertJsonPath('data.store_urls.ios', null)->assertJsonPath('data.maintenance', false)->assertJsonPath('data.maintenance_message', null)
        ->assertJsonPath('data.update_required', true)->assertJsonPath('data.update_available', true);
    $this->getJson('http://3alraf.test/api/v1/app-config?version=1.4.0')->assertJsonPath('data.update_required', false)->assertJsonPath('data.update_available', false);
    $this->getJson('http://3alraf.test/api/v1/app-config')->assertJsonPath('data.update_required', null);
    config(['mobile.maintenance' => true]);
    $this->getJson('http://3alraf.test/api/v1/app-config', ['Accept-Language' => 'en'])->assertJsonPath('data.maintenance', true)->assertJsonPath('data.maintenance_message', config('mobile.maintenance_message.en'));
    $this->getJson('http://3alraf.test/api/v1/app-config', ['Accept-Language' => 'ar'])->assertJsonPath('data.maintenance_message', config('mobile.maintenance_message.ar'));
});

// ------------------------------------------------------------------ account deletion + change password

it('deletes the account after password confirmation, anonymises it and revokes every token', function () {
    $h = ($this->auth)();
    $other = ($this->auth)();
    $id = $this->customer->id;
    $this->customer->addresses()->create(['label' => 'Home', 'name' => 'A', 'phone' => '01000000100', 'city_id' => City::first()->id, 'address' => 'St 1']);
    $this->postJson("{$this->api}/wishlist/{$this->p1->id}/toggle", [], $h)->assertOk();
    $this->postJson("{$this->api}/devices", ['token' => str_repeat('a', 40), 'platform' => 'android'], $h)->assertCreated();

    $this->deleteJson("{$this->api}/auth/me")->assertUnauthorized();
    $this->deleteJson("{$this->api}/auth/me", [], $h)->assertStatus(422)->assertJsonStructure(['errors' => ['password']]);
    $this->deleteJson("{$this->api}/auth/me", ['password' => 'wrong-password'], $h)->assertStatus(422);
    expect(Customer::find($id))->not->toBeNull();

    ok($this->deleteJson("{$this->api}/auth/me", ['password' => 'password'], $h));
    expect(Customer::find($id))->toBeNull();
    $row = Customer::withTrashed()->find($id);
    expect($row->name)->toBe('Deleted customer')->and($row->phone)->toBeNull()->and($row->email)->not->toContain('customer@egy.test')
        ->and($row->tokens()->count())->toBe(0)->and($row->addresses()->count())->toBe(0)->and(Device::count())->toBe(0);
    expect(\App\Models\WishlistItem::where('customer_id', $id)->count())->toBe(0);
    $this->getJson("{$this->api}/auth/me", $other)->assertUnauthorized();
    $this->postJson("{$this->api}/auth/login", ['email' => 'customer@egy.test', 'password' => 'password'])->assertStatus(422);
    // the e-mail is free again
    $this->postJson("{$this->api}/auth/register", ['name' => 'New', 'email' => 'customer@egy.test', 'password' => 'secret1234', 'password_confirmation' => 'secret1234'])->assertCreated();
});

it('changes the password in-app through PUT auth/me and revokes other sessions', function () {
    $h = ($this->auth)();
    $other = ($this->auth)();
    $p = ['name' => 'Cust', 'email' => 'customer@egy.test', 'password' => 'brand-new-pass', 'password_confirmation' => 'brand-new-pass'];
    $this->putJson("{$this->api}/auth/me", $p, $h)->assertStatus(422)->assertJsonStructure(['errors' => ['current_password']]);
    ok($this->putJson("{$this->api}/auth/me", $p + ['current_password' => 'password'], $h));
    $this->getJson("{$this->api}/auth/me", $h)->assertOk();
    $this->getJson("{$this->api}/auth/me", $other)->assertUnauthorized();
    $this->postJson("{$this->api}/auth/login", ['email' => 'customer@egy.test', 'password' => 'brand-new-pass'])->assertOk();
});

// ------------------------------------------------------------------ devices

it('registers and removes push tokens per tenant', function () {
    $tok = str_repeat('f', 60);
    $this->postJson("{$this->api}/devices", [])->assertStatus(422)->assertJsonStructure(['errors' => ['token', 'platform']]);
    $this->postJson("{$this->api}/devices", ['token' => $tok, 'platform' => 'windows'])->assertStatus(422);
    ok($this->postJson("{$this->api}/devices", ['token' => $tok, 'platform' => 'ios', 'locale' => 'ar', 'app_version' => '1.0.0']))->assertCreated()->assertJsonPath('data.bound_to_customer', false);
    ok($this->postJson("{$this->api}/devices", ['token' => $tok, 'platform' => 'ios'], ($this->auth)()))->assertCreated()->assertJsonPath('data.bound_to_customer', true);   // upsert, not a duplicate
    $d = Device::sole();
    expect($d->customer_id)->toBe($this->customer->id)->and($d->tenant_id)->toBe($this->tenant->id);
    $this->postJson("{$this->api}/devices", ['token' => $tok, 'platform' => 'ios'], ['X-Tenant' => 'sa'])->assertCreated();   // subdomain host wins; same tenant
    expect(Device::count())->toBe(1);
    // the same token registered in another store is a separate row and cannot be removed through this store
    $this->postJson('http://sa.3alraf.test/api/v1/devices', ['token' => $tok, 'platform' => 'ios'])->assertCreated();
    expect(Device::withoutGlobalScopes()->count())->toBe(2);
    $this->deleteJson("{$this->api}/devices", [])->assertStatus(422);
    ok($this->deleteJson("{$this->api}/devices", ['token' => $tok]));
    expect(Device::withoutGlobalScopes()->count())->toBe(1)->and(Device::withoutGlobalScopes()->first()->tenant_id)->toBe($this->sa->id);
    ok($this->deleteJson("{$this->api}/devices", ['token' => $tok]));   // idempotent
    expect(app(\App\Services\Push\PushDispatcher::class))->toBeInstanceOf(\App\Services\Push\LogPushDispatcher::class);
});
