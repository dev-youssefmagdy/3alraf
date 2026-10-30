<?php

use App\Models\CentralAdmin;
use App\Models\Language;
use App\Models\Tenant;
use App\Models\TenantAdmin;
use Database\Seeders\FoundationSeeder;

beforeEach(function () {
    $this->seed(FoundationSeeder::class);
    $this->central = 'http://3alraf.test';
    $this->egy = 'http://egy.3alraf.test';
});

it('redirects guests to central login and logs in via json', function () {
    $this->get("{$this->central}/admin")->assertRedirect("{$this->central}/admin/login");
    $this->postJson("{$this->central}/admin/login/validate", [])->assertStatus(422)->assertJsonValidationErrors(['phone', 'password']);
    $this->postJson("{$this->central}/admin/login", ['country' => 'EG', 'phone' => '01000000001', 'password' => 'password'])
        ->assertOk()->assertJson(['success' => true]);
    $this->get("{$this->central}/admin")->assertOk();
});

it('resolves tenants by subdomain and 404s unknown ones', function () {
    $this->get("{$this->egy}/admin/login")->assertOk();
    $this->get('http://nope.3alraf.test/admin/login')->assertNotFound();
    Tenant::where('slug', 'egy')->update(['is_active' => false]);
    $this->get("{$this->egy}/admin/login")->assertNotFound();
});

it('redirects tenant root to default locale and rejects unknown locales', function () {
    $this->get("{$this->egy}/")->assertRedirect("{$this->egy}/ar");
    $this->get("{$this->egy}/ar")->assertOk()->assertSee('dir="rtl"', false);
    $this->get("{$this->egy}/en")->assertOk()->assertSee('dir="ltr"', false);
    $this->get("{$this->egy}/fr")->assertNotFound();
});

it('keeps guards isolated: tenant admin cannot enter central and vice versa', function () {
    $this->postJson("{$this->egy}/admin/login", ['country' => 'EG', 'phone' => '0'.substr(TenantAdmin::withoutGlobalScopes()->where('email', 'owner@egy.test')->value('phone'), 2), 'password' => 'password'])->assertOk();
    $this->get("{$this->egy}/admin")->assertOk();
    $this->get("{$this->central}/admin")->assertRedirect("{$this->central}/admin/login");
    $this->post("{$this->egy}/admin/logout");
    $this->postJson("{$this->egy}/admin/login", ['country' => 'EG', 'phone' => '01000000001', 'password' => 'password'])->assertStatus(422);
});

it('scopes tenant models to the current tenant', function () {
    $other = Tenant::create(['slug' => 'sa', 'name' => 'SA', 'locales' => ['ar'], 'default_locale' => 'ar']);
    TenantAdmin::withoutGlobalScopes()->create(['tenant_id' => $other->id, 'name' => 'x', 'email' => 'x@sa.test', 'password' => 'password']);
    expect(TenantAdmin::count())->toBe(2);
    app()->instance('currentTenant', Tenant::where('slug', 'egy')->first());
    expect(TenantAdmin::count())->toBe(1);
    $a = TenantAdmin::create(['name' => 'y', 'email' => 'y@egy.test', 'password' => 'password']);
    expect($a->tenant->slug)->toBe('egy');
});

it('runs the language CRUD with pre-validation', function () {
    $this->actingAs(CentralAdmin::first(), 'central');
    $base = "{$this->central}/admin/languages";
    $this->postJson("$base/validate", ['code' => 'ar'])->assertStatus(422)->assertJsonValidationErrors(['code', 'name', 'native_name', 'direction']);
    $payload = ['code' => 'fr', 'name' => 'French', 'native_name' => 'Français', 'direction' => 'ltr', 'is_active' => 1];
    $this->postJson("$base/validate", $payload)->assertOk();
    $this->postJson($base, $payload)->assertOk()->assertJson(['success' => true]);
    $fr = Language::where('code', 'fr')->first();
    $this->putJson("$base/{$fr->id}", [...$payload, 'name' => 'Français'])->assertOk();
    $this->get("$base/datatable?draw=1&start=0&length=10")->assertOk()->assertJsonPath('recordsTotal', 3);
    $this->get($base)->assertOk();
    $this->deleteJson("$base/{$fr->id}")->assertOk();
    $ar = Language::where('code', 'ar')->first();
    $this->deleteJson("$base/{$ar->id}")->assertStatus(422);
});

it('runs the tenant CRUD and creates an owner admin', function () {
    $this->actingAs(CentralAdmin::first(), 'central');
    $base = "{$this->central}/admin/tenants";
    $p = ['slug' => 'ksa', 'name' => 'KSA', 'country' => 'sa', 'currency_code' => 'sar', 'currency_symbol' => 'SAR', 'currency_rate' => 1.1,
        'default_locale' => 'ar', 'locales' => ['ar', 'en'], 'timezone' => 'Asia/Riyadh', 'is_active' => 1,
        'admin_country' => 'EG', 'admin_phone' => '01012345678', 'admin_password' => 'secret123'];
    $this->postJson("$base/validate", [...$p, 'slug' => 'www'])->assertStatus(422)->assertJsonValidationErrors('slug');
    $this->postJson($base, $p)->assertOk();
    $t = Tenant::where('slug', 'ksa')->first();
    expect($t->currency_code)->toBe('SAR')->and(TenantAdmin::withoutGlobalScopes()->where('tenant_id', $t->id)->first()->hasRole('tenant-owner'))->toBeTrue();
    $this->putJson("$base/{$t->id}", [...$p, 'name' => 'KSA 2', 'admin_phone' => null, 'admin_password' => null])->assertOk();
    expect($t->fresh()->name)->toBe('KSA 2');
    $this->get("$base/datatable?draw=1&start=0&length=10")->assertOk();
    $this->get("$base/create")->assertOk();
    $this->get("$base/{$t->id}/edit")->assertOk();
    $this->deleteJson("$base/{$t->id}")->assertOk();
});

it('enforces permissions for non super admins', function () {
    $a = CentralAdmin::create(['name' => 'p', 'email' => 'p@x.test', 'password' => 'password']);
    $this->actingAs($a, 'central')->get("{$this->central}/admin/languages")->assertForbidden();
});
