<?php

use App\Enums\AffiliateStatus;
use App\Models\Affiliate;
use App\Models\Banner;
use App\Models\CentralAdmin;
use App\Models\City;
use App\Models\Customer;
use App\Models\CustomPage;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ShippingCompany;
use App\Models\Tenant;
use App\Models\TenantAdmin;
use App\Models\TenantProduct;
use App\Notifications\AuthCodeNotification;
use App\Services\Auth\SessionRevoker;
use App\Services\Orders\OrderPaymentService;
use App\Support\SafeUrl;
use Database\Seeders\FoundationSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    Cache::flush();
    $this->seed(FoundationSeeder::class);
    $this->tenant = Tenant::where('slug', 'egy')->first();
    app()->instance('currentTenant', $this->tenant);
    $this->central = 'http://3alraf.test/admin';
    $this->t = 'http://egy.3alraf.test/admin';
    $this->s = 'http://egy.3alraf.test/ar';
    $this->api = 'http://egy.3alraf.test/api/v1';
    $this->owner = TenantAdmin::withoutGlobalScopes()->where('email', 'owner@egy.test')->first();
    // a tenant role + staff account holding it
    $this->mkRole = function (string $name, array $perms): Role {
        $r = Role::create(['name' => 't'.$this->tenant->id.':'.$name, 'guard_name' => 'tenant']);
        $r->givePermissionTo($perms);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $r;
    };
    $this->mkStaff = function (string $email, ?Role $role): TenantAdmin {
        $a = TenantAdmin::create(['name' => 'Staff', 'email' => $email, 'password' => 'password', 'is_active' => true]);
        $role && $a->assignRole($role);

        return $a;
    };
});

// ---------------------------------------------------------------- (1) role assignment
it('stops a non-owner from assigning or touching the system role and from granting permissions they lack', function () {
    $manager = ($this->mkRole)('manager', ['admins.view', 'admins.create', 'admins.edit', 'admins.delete']);
    $big = ($this->mkRole)('big', ['admins.view', 'orders.view']);
    $small = ($this->mkRole)('small', ['admins.view']);
    $ownerRole = Role::findByName('tenant-owner', 'tenant');
    $staff = ($this->mkStaff)('mgr@egy.test', $manager);
    $this->actingAs($staff, 'tenant');
    $form = fn (array $roles, string $email = 'new@egy.test') => ['name' => 'N', 'email' => $email, 'password' => 'secret123', 'is_active' => 1, 'roles' => $roles];

    $this->postJson("{$this->t}/admins", $form([$ownerRole->id]))->assertStatus(422)->assertJsonValidationErrors('roles');
    $this->postJson("{$this->t}/admins", $form([$big->id]))->assertStatus(422)->assertJsonValidationErrors('roles');   // orders.view not held
    $this->postJson("{$this->t}/admins", $form([$small->id]))->assertOk();
    expect(TenantAdmin::where('email', 'new@egy.test')->first()->hasRole($small))->toBeTrue();

    // the owner account (system role) cannot be opened, edited or deleted by a non-holder
    $this->get("{$this->t}/admins/{$this->owner->id}/edit")->assertForbidden();
    $this->putJson("{$this->t}/admins/{$this->owner->id}", $form([], 'owner@egy.test'))->assertForbidden();
    $this->deleteJson("{$this->t}/admins/{$this->owner->id}")->assertForbidden();
    expect($this->owner->fresh()->is_active)->toBeTrue();

    // the form only offers roles the actor may grant
    $this->get("{$this->t}/admins/create")->assertOk()->assertSee('small')->assertDontSee('value="'.$ownerRole->id.'"', false)->assertDontSee('big');

    // the owner can do all of it
    $this->actingAs($this->owner, 'tenant');
    $this->postJson("{$this->t}/admins", $form([$ownerRole->id, $big->id], 'co@egy.test'))->assertOk();
    expect(TenantAdmin::where('email', 'co@egy.test')->first()->hasRole('tenant-owner'))->toBeTrue();
});

it('lets a non-owner keep a role the account already holds while editing it', function () {
    $manager = ($this->mkRole)('manager', ['admins.view', 'admins.edit']);
    $big = ($this->mkRole)('big', ['orders.view']);
    $other = ($this->mkStaff)('o@egy.test', $big);
    $this->actingAs(($this->mkStaff)('mgr@egy.test', $manager), 'tenant');
    $this->putJson("{$this->t}/admins/{$other->id}", ['name' => 'Renamed', 'email' => 'o@egy.test', 'is_active' => 1, 'roles' => [$big->id]])->assertOk();
    expect($other->fresh()->name)->toBe('Renamed')->and($other->fresh()->hasRole($big))->toBeTrue();
});

// ---------------------------------------------------------------- (2) inactive accounts
it('logs deactivated admins, customers and suspended affiliates out on their next request', function () {
    $staff = ($this->mkStaff)('s@egy.test', ($this->mkRole)('viewer', ['admins.view']));
    $this->actingAs($staff, 'tenant')->get("{$this->t}/admins")->assertOk();
    $staff->forceFill(['is_active' => false])->saveQuietly();   // same instance the test guard holds
    $this->get("{$this->t}/admins")->assertRedirect();
    expect(auth('tenant')->check())->toBeFalse();

    $c = Customer::where('email', 'customer@egy.test')->first();
    $this->actingAs($c, 'web')->get("{$this->s}/account")->assertOk();
    $c->forceFill(['is_active' => false])->saveQuietly();
    $this->get("{$this->s}/account")->assertRedirect();

    $a = Affiliate::create(['name' => 'Aff', 'email' => 'aff-sec@egy.test', 'password' => 'password', 'status' => AffiliateStatus::APPROVED]);
    $this->actingAs($a, 'affiliate')->get("{$this->s}/affiliate")->assertOk();
    $a->forceFill(['status' => AffiliateStatus::SUSPENDED])->saveQuietly();
    $this->get("{$this->s}/affiliate")->assertRedirect();

    $ca = CentralAdmin::create(['name' => 'C2', 'email' => 'c2@3alraf.test', 'password' => 'password', 'is_active' => true]);
    $this->actingAs($ca, 'central')->get($this->central)->assertOk();
    $ca->forceFill(['is_active' => false])->saveQuietly();
    $this->get($this->central)->assertRedirect();
});

it('rotates the remember token and deletes DB sessions on deactivation, suspension and password change', function () {
    config(['session.driver' => 'database']);
    $sess = function (string $guard, int $id, string $sid) {
        $key = auth()->guard($guard)->getName();
        DB::table('sessions')->insert(['id' => $sid, 'user_id' => null, 'ip_address' => '1.1.1.1', 'user_agent' => 'x', 'payload' => base64_encode(json_encode([$key => $id, '_token' => 'x'])), 'last_activity' => now()->getTimestamp()]);
    };
    $c = Customer::where('email', 'customer@egy.test')->first();
    $c->forceFill(['remember_token' => 'old-token'])->saveQuietly();
    $sess('web', $c->id, 'cust-a');
    $sess('web', $c->id + 999, 'someone-else');
    $sess('tenant', $c->id, 'admin-same-id');   // other guard, same numeric id: untouched

    $c->update(['is_active' => false]);
    expect($c->fresh()->remember_token)->not->toBe('old-token')
        ->and(DB::table('sessions')->pluck('id')->sort()->values()->all())->toBe(['admin-same-id', 'someone-else']);

    $a = Affiliate::create(['name' => 'Aff', 'email' => 'aff-sec@egy.test', 'password' => 'password', 'status' => AffiliateStatus::APPROVED]);
    $sess('affiliate', $a->id, 'aff-1');
    $a->update(['status' => AffiliateStatus::SUSPENDED]);
    expect(DB::table('sessions')->where('id', 'aff-1')->exists())->toBeFalse();

    $sess('tenant', $this->owner->id, 'owner-1');
    $this->owner->update(['name' => 'Renamed']);   // unrelated change: nothing revoked
    expect(DB::table('sessions')->where('id', 'owner-1')->exists())->toBeTrue();
    $this->owner->update(['password' => 'brandnew123']);
    expect(DB::table('sessions')->where('id', 'owner-1')->exists())->toBeFalse();
});

it('deactivating a staff account through the admin UI revokes it', function () {
    $staff = ($this->mkStaff)('s@egy.test', null);
    $staff->forceFill(['remember_token' => 'tok'])->saveQuietly();
    $this->actingAs($this->owner, 'tenant')->putJson("{$this->t}/admins/{$staff->id}", ['name' => 'S', 'email' => 's@egy.test', 'is_active' => 0, 'roles' => []])->assertOk();
    expect($staff->fresh()->is_active)->toBeFalse()->and($staff->fresh()->remember_token)->not->toBe('tok');
});

// ---------------------------------------------------------------- (3) banner links
it('accepts only http(s) or site-relative banner links and filters unsafe ones at render', function () {
    foreach (['https://x.test/a', 'http://x.test', '/ar/category/devices'] as $ok) {
        expect(SafeUrl::isSafe($ok))->toBeTrue();
    }
    foreach (['javascript:alert(1)', 'JaVaScRiPt:alert(1)', 'data:text/html,x', '//evil.test', '/\\evil.test', 'vbscript:x', 'ar/page', ' https://x.test', "java\nscript:x"] as $bad) {
        expect(SafeUrl::isSafe($bad))->toBeFalse();
    }
    $this->actingAs($this->owner, 'tenant');
    $this->postJson("{$this->t}/banners/validate", ['placement' => 'home_hero', 'link_url' => 'javascript:alert(1)'])->assertStatus(422)->assertJsonValidationErrors('link_url');
    $this->postJson("{$this->t}/banners/validate", ['placement' => 'home_hero', 'link_url' => '/ar/search'])->assertJsonMissingValidationErrors('link_url');

    $b = Banner::create(['placement' => 'home_hero', 'title' => ['ar' => 'b'], 'images' => ['ar' => 'banners/x.jpg'], 'link_url' => 'javascript:alert(1)', 'is_active' => true]);
    expect($b->safeLink())->toBeNull();
    $this->get("{$this->s}/")->assertOk()->assertDontSee('javascript:alert(1)', false);
    $this->getJson("{$this->api}/home")->assertOk()->assertDontSee('javascript:', false);
});

// ---------------------------------------------------------------- (4) + (6) permission gates
it('requires payment_methods.edit to toggle a method and reports.view for the central dashboard data + tenant switcher', function () {
    $pm = PaymentMethod::first();
    $this->actingAs(($this->mkStaff)('v@egy.test', ($this->mkRole)('viewer', ['payment_methods.view'])), 'tenant');
    $this->postJson("{$this->t}/payment-methods/{$pm->id}/toggle")->assertForbidden();

    $ca = CentralAdmin::create(['name' => 'C2', 'email' => 'c2@3alraf.test', 'password' => 'password', 'is_active' => true]);
    $this->actingAs($ca, 'central');
    $this->get($this->central)->assertOk()->assertDontSee(__('reports.sales_trend'))->assertDontSee('switch-tenant');
    $this->post("{$this->central}/switch-tenant", ['tenant_id' => $this->tenant->id])->assertForbidden();
    expect(session('central_tenant_id'))->toBeNull();

    $this->actingAs(CentralAdmin::where('email', 'admin@3alraf.test')->first(), 'central');
    $this->get($this->central)->assertOk()->assertSee(__('reports.sales_trend'));
    $this->post("{$this->central}/switch-tenant", ['tenant_id' => $this->tenant->id])->assertRedirect();
});

// ---------------------------------------------------------------- (5) limiters
it('keys sensitive limiters by tenant + IP + route and ignores X-Tenant on subdomain hosts', function () {
    foreach (range(1, 10) as $i) {
        $this->postJson("{$this->api}/auth/login", ['email' => "u$i@x.test", 'password' => 'bad'], ['X-Tenant' => "rot$i"])->assertStatus(422);
    }
    $this->postJson("{$this->api}/auth/login", ['email' => 'z@x.test', 'password' => 'bad'], ['X-Tenant' => 'fresh'])->assertStatus(429);
    // the header-tenant twin route shares the bucket
    $this->postJson('http://3alraf.test/api/v1/auth/login', ['email' => 'z@x.test', 'password' => 'bad'], ['X-Tenant' => 'egy'])->assertStatus(429);
    // another route has its own bucket
    $this->postJson("{$this->api}/affiliate/login", ['email' => 'z@x.test', 'password' => 'bad'])->assertStatus(422);
    // web login converted too
    foreach (range(1, 10) as $i) {
        $this->postJson("{$this->s}/login", ['email' => "w$i@x.test", 'password' => 'bad']);
    }
    $this->postJson("{$this->s}/login", ['email' => 'w@x.test', 'password' => 'bad'])->assertStatus(429);
});

it('does not give garbage bearer tokens their own api bucket', function () {
    foreach (range(1, 120) as $i) {
        $this->getJson("{$this->api}/store", ['Authorization' => "Bearer junk$i"]);
    }
    $this->getJson("{$this->api}/store", ['Authorization' => 'Bearer junk-new'])->assertStatus(429);
});

// ---------------------------------------------------------------- (7) auth codes
it('keeps the wrong-code counter across re-issues and applies a per-email issue cooldown', function () {
    Notification::fake();
    $c = Customer::where('email', 'customer@egy.test')->first();
    $svc = app(\App\Services\Api\AuthCodeService::class);
    $svc->sendReset($c->email);
    foreach (range(1, 3) as $i) {
        try {
            $svc->reset($c->email, '000000', 'newpass123');
        } catch (ValidationException) {
        }
    }
    $this->travel(61)->seconds();
    $svc->sendReset($c->email);
    expect((int) DB::table('customer_auth_codes')->where('email', $c->email)->value('attempts'))->toBe(3);
    Notification::assertSentToTimes($c, AuthCodeNotification::class, 2);

    $svc->sendReset($c->email);   // inside the cooldown: silently skipped
    Notification::assertSentToTimes($c, AuthCodeNotification::class, 2);
    expect(fn () => $svc->sendVerification($c))->not->toThrow(ValidationException::class); // other type: separate cooldown
    expect(fn () => $svc->sendVerification($c))->toThrow(ValidationException::class);
});

// ---------------------------------------------------------------- (8) audit log
it('keeps order secrets and customer PII out of the audit log', function () {
    $o = Order::query()->first() ?? Order::create(['number' => 'T-1', 'public_token' => 'tok-secret', 'customer_name' => 'Ali', 'customer_phone' => '0100', 'customer_email' => 'a@x.test', 'address' => 'St', 'status' => 'pending', 'payment_status' => 'unpaid', 'subtotal' => 1, 'total' => 1, 'currency' => 'EGP']);
    $o->update(['customer_name' => 'Changed', 'address' => 'New street', 'payment_proof' => 'proofs/x.jpg', 'notes' => 'n2']);
    $props = \Spatie\Activitylog\Models\Activity::where('subject_type', $o->getMorphClass())->where('subject_id', $o->id)->latest('id')->first()->properties->toArray();
    expect(json_encode($props))->not->toContain('Changed')->not->toContain('New street')->not->toContain('proofs/x.jpg')->not->toContain($o->public_token)->toContain('n2');
});

// ---------------------------------------------------------------- (9) profile
it('requires the current password to change e-mail or password, unverifies a new e-mail and revokes other logins', function () {
    $c = Customer::where('email', 'customer@egy.test')->first();
    $c->forceFill(['email_verified_at' => now()])->save();
    $other = $c->createToken('other', ['customer'])->plainTextToken;
    $mine = $c->createToken('mine', ['customer'])->plainTextToken;
    $h = ['Authorization' => "Bearer $mine"];

    $this->putJson("{$this->api}/auth/me", ['name' => 'X', 'email' => 'new@x.test'], $h)->assertStatus(422)->assertJsonValidationErrors('current_password');
    $this->putJson("{$this->api}/auth/me", ['name' => 'X', 'email' => 'new@x.test', 'current_password' => 'wrong'], $h)->assertStatus(422)->assertJsonValidationErrors('current_password');
    $this->putJson("{$this->api}/auth/me", ['name' => 'X', 'email' => 'customer@egy.test'], $h)->assertOk(); // no credential change: no password needed
    expect($c->fresh()->email_verified_at)->not->toBeNull();

    $this->putJson("{$this->api}/auth/me", ['name' => 'X', 'email' => 'new@x.test', 'current_password' => 'password'], $h)->assertOk()->assertJsonPath('data.email_verified', false);
    expect($c->fresh()->email)->toBe('new@x.test')->and($c->fresh()->email_verified_at)->toBeNull();
    app('auth')->forgetGuards();
    $this->getJson("{$this->api}/auth/me", ['Authorization' => "Bearer $other"])->assertUnauthorized();
    app('auth')->forgetGuards();
    $this->getJson("{$this->api}/auth/me", $h)->assertOk();

    // web form: password change needs the current one
    app('auth')->forgetGuards();
    $c = $c->fresh();
    $this->actingAs($c, 'web')->putJson("{$this->s}/account/profile", ['name' => 'X', 'email' => 'new@x.test', 'password' => 'brandnew123', 'password_confirmation' => 'brandnew123'])
        ->assertStatus(422)->assertJsonValidationErrors('current_password');
    $this->putJson("{$this->s}/account/profile", ['name' => 'X', 'email' => 'new@x.test', 'password' => 'brandnew123', 'password_confirmation' => 'brandnew123', 'current_password' => 'password'])->assertOk();
    expect(\Illuminate\Support\Facades\Hash::check('brandnew123', $c->fresh()->password))->toBeTrue();
});

// ---------------------------------------------------------------- (10) payment attempts
it('widens payment_attempts and refuses new payment links beyond the cap', function () {
    expect(Schema::getColumnType('orders', 'payment_attempts'))->toBe('smallint');
    $o = Order::query()->first() ?? Order::create(['number' => 'T-2', 'public_token' => 'tok-2', 'customer_name' => 'A', 'customer_phone' => '1', 'address' => 'St', 'status' => 'pending', 'payment_status' => 'unpaid', 'subtotal' => 1, 'total' => 1, 'currency' => 'EGP']);
    $o->update(['payment_attempts' => OrderPaymentService::MAX_PAYMENT_ATTEMPTS]);
    $online = PaymentMethod::where('provider', 'paymob')->first();
    $online->update(['is_active' => true, 'credentials' => ['api_key' => 'k', 'integration_id' => '11', 'iframe_id' => '22', 'hmac_secret' => 'hs']]);
    $o->update(['payment_method_id' => $online->id, 'payment_status' => 'unpaid', 'status' => 'pending']);
    $this->postJson("{$this->s}/order/{$o->public_token}/pay")->assertStatus(422)->assertJsonValidationErrors('payment');
    expect((int) $o->fresh()->payment_attempts)->toBe(OrderPaymentService::MAX_PAYMENT_ATTEMPTS);
});

// ---------------------------------------------------------------- (12) checkout city
it('rejects an inactive city at checkout', function () {
    $city = City::first();
    $city->update(['is_active' => false]);
    $this->postJson("{$this->s}/checkout/validate", ['name' => 'A', 'phone' => '1', 'city_id' => $city->id, 'address' => 'St', 'shipping_company_id' => ShippingCompany::first()->id, 'payment_method_id' => PaymentMethod::first()->id])
        ->assertStatus(422)->assertJsonValidationErrors('city_id');
});

// ---------------------------------------------------------------- (13) explicit fillable
it('declares explicit fillable columns on the document item models', function () {
    foreach ([\App\Models\PurchaseOrderItem::class, \App\Models\StockAdjustmentItem::class, \App\Models\StockTransferItem::class, \App\Models\OrderReturnItem::class] as $cls) {
        $m = new $cls;
        expect($m->getGuarded())->toBe(['*'])->and($m->getFillable())->not->toBeEmpty()->not->toContain('id');
    }
});

// ---------------------------------------------------------------- (14) irreversible migration
it('refuses to roll back the affiliate e-mail normalisation', function () {
    $m = require database_path('migrations/2026_10_27_100001_affiliate_email_unique.php');
    expect(fn () => $m->down())->toThrow(\App\Exceptions\IrreversibleMigrationException::class);
});

// ---------------------------------------------------------------- (15) custom pages API
it('lists active custom pages and shows one with its product grid', function () {
    $p = Product::create(['type' => 'simple', 'name' => ['ar' => 'منتج', 'en' => 'Prod'], 'slug' => 'cp-prod', 'sku' => 'CPP', 'barcode' => '2000000000091', 'is_active' => true]);
    TenantProduct::create(['product_id' => $p->id, 'price' => 100, 'cost' => 60, 'is_active' => true]);
    $page = CustomPage::create(['slug' => 'summer', 'title' => ['ar' => 'الصيف', 'en' => 'Summer'], 'filters' => [], 'is_active' => true]);
    $page->products()->attach($p->id, ['sort_order' => 1]);
    CustomPage::create(['slug' => 'hidden', 'title' => ['ar' => 'مخفي'], 'filters' => [], 'is_active' => false]);

    $list = collect($this->getJson("{$this->api}/custom-pages", ['Accept-Language' => 'en'])->assertOk()->json('data'))->keyBy('slug');
    expect($list->has('summer'))->toBeTrue()->and($list->has('hidden'))->toBeFalse()->and($list['summer']['title'])->toBe('Summer');
    $this->getJson("{$this->api}/custom-pages/summer?sort=bogus&price_min=abc", ['Accept-Language' => 'en'])->assertOk()
        ->assertJsonPath('data.page.slug', 'summer')->assertJsonPath('data.products.0.slug', 'cp-prod')->assertJsonPath('data.products.0.price', '100.00')
        ->assertJsonPath('meta.pagination.total', 1)->assertJsonMissingPath('data.page.filters');
    $this->getJson("{$this->api}/custom-pages/hidden")->assertNotFound();
    $this->getJson('http://3alraf.test/api/v1/custom-pages', ['X-Tenant' => 'egy'])->assertOk()->assertJsonCount($list->count(), 'data');
});
