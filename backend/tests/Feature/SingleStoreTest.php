<?php

// Single-store smoke tests (Phase 9): one host, one admin guard, the former central pages inside the panel with their own permissions,
// Settings → General, legacy {slug}.host redirects, and a schema sweep (no tenant_id / owner_key left).

use App\Enums\StockMovementType as T;
use App\Models\Admin;
use App\Models\Branch;
use App\Models\City;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Setting;
use App\Models\ShippingCompany;
use App\Services\Stock\StockLedger;
use Database\Seeders\FoundationSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->seed(FoundationSeeder::class);
    $this->super = Admin::query()->where('email', 'admin@3alraf.test')->first();
    $this->owner = Admin::query()->where('email', 'owner@egy.test')->first();
});

/** Pages that existed only in the central panel and were ADDED to the single panel (INVENTORY §A), with their view permission. */
function addedPages(): array
{
    return [
        '/admin/products' => 'products.view', '/admin/categories' => 'categories.view', '/admin/brands' => 'brands.view', '/admin/tags' => 'tags.view',
        '/admin/attributes' => 'attributes.view', '/admin/languages' => 'languages.view',
        '/admin/ai/providers' => 'ai_providers.view', '/admin/ai/models' => 'ai_models.view', '/admin/ai/usage' => 'ai_usage.view',
        '/admin/erp/anomalies' => 'erp.audit.view', '/admin/settings/general' => 'general_settings.view',
    ];
}

it('has no tenant_id / owner_key column and no tenant / central admin table left', function () {
    $db = config('database.connections.mysql.database');
    $cols = DB::select("SELECT TABLE_NAME t, COLUMN_NAME c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND COLUMN_NAME IN ('tenant_id', 'owner_key', 'tenant_product_id')", [$db]);
    $tables = DB::select("SELECT TABLE_NAME t FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND (TABLE_NAME LIKE 'tenant%' OR TABLE_NAME LIKE 'central%' OR TABLE_NAME IN ('stores', 'store_products'))", [$db]);
    $keys = DB::select("SELECT DISTINCT INDEX_NAME n FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? AND INDEX_NAME LIKE '%tenant%'", [$db]);
    expect($cols)->toBe([])->and($tables)->toBe([])->and($keys)->toBe([]);
    expect(array_keys(config('auth.guards')))->toContain('admin')->not->toContain('tenant')->not->toContain('central')
        ->and(collect(app('router')->getRoutes()->getRoutes())->map->getName()->filter(fn ($n) => preg_match('/^(tenant|central)\./', (string) $n))->values()->all())->toBe([]);
});

it('logs in once at /admin on guard admin; otp_exempt skips the OTP', function () {
    $this->get('/admin')->assertRedirect('/admin/login');
    $this->postJson('/admin/login', adminCreds('admin@3alraf.test'))->assertOk()->assertJsonPath('redirect', url('/admin'));
    expect(auth('admin')->id())->toBe($this->super->id)->and($this->super->otp_exempt)->toBeTrue();
    $this->get('/admin')->assertOk();
});

it('the super admin opens every page added from the central panel; the owner has the merged permissions too', function () {
    foreach ([$this->super, $this->owner] as $admin) {
        $this->actingAs($admin, 'admin');
        foreach (array_keys(addedPages()) as $url) {
            $this->get($url)->assertOk();
        }
    }
    $html = $this->actingAs($this->super, 'admin')->get('/admin')->assertOk()->getContent();
    foreach (array_keys(addedPages()) as $url) {
        expect($html)->toContain('href="'.url($url).'"');   // sidebar links
    }
});

it('a role without the permissions gets 403 on each added page, and exactly its own page once granted', function () {
    $staff = Admin::query()->create(['name' => 'Staff', 'phone' => '201000000321', 'password' => 'password', 'is_active' => true]);
    $role = \Spatie\Permission\Models\Role::create(['name' => 'Nothing', 'guard_name' => 'admin']);
    $staff->assignRole($role);
    $this->actingAs($staff, 'admin');
    foreach (array_keys(addedPages()) as $url) {
        $this->get($url)->assertForbidden();
    }
    foreach (addedPages() as $url => $perm) {
        $role->syncPermissions([$perm]);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs($staff->fresh(), 'admin')->get($url)->assertOk();
        $other = collect(addedPages())->keys()->first(fn ($u) => $u !== $url && addedPages()[$u] !== $perm);
        $this->get($other)->assertForbidden();
    }
    expect(\Spatie\Permission\Models\Permission::query()->where('guard_name', '!=', 'admin')->count())->toBe(0);
});

it('Settings → General saves the store profile into settings (validated, audited) and the storefront follows it', function () {
    $this->actingAs($this->super, 'admin');
    $p = ['name' => 'My Shop', 'country' => 'EG', 'currency_code' => 'EGP', 'currency_symbol' => 'ج.م', 'default_locale' => 'en', 'locales' => ['ar', 'en'], 'timezone' => 'Africa/Cairo', 'ai_enabled' => 0, 'growth_enabled' => 0];
    $this->postJson('/admin/settings/general/validate', [...$p, 'default_locale' => 'fr'])->assertStatus(422)->assertJsonValidationErrors('default_locale');
    $this->postJson('/admin/settings/general/validate', [...$p, 'timezone' => 'Mars/Base'])->assertStatus(422)->assertJsonValidationErrors('timezone');
    $this->putJson('/admin/settings/general', $p)->assertOk();
    Setting::flush();
    expect(setting('name'))->toBe('My Shop')->and(setting('default_locale'))->toBe('en')->and(setting('currency_symbol'))->toBe('ج.م');
    $this->get('/')->assertRedirect('/en');
    $this->get('/en')->assertOk()->assertSee('My Shop');
    $this->getJson('/api/v1/app-config')->assertJsonPath('data.store.name', 'My Shop')->assertJsonPath('data.store.default_locale', 'en');
});

it('the Catalog page edits price/sale/cost/status directly on products and links to the product page', function () {
    $p = Product::create(['type' => 'simple', 'name' => ['ar' => 'منتج', 'en' => 'Item'], 'slug' => 'cat-item', 'sku' => 'CI1']);
    listProduct($p, ['price' => 100, 'cost' => 40]);
    $this->actingAs($this->owner, 'admin');
    $this->getJson('/admin/catalog/datatable?draw=1&start=0&length=10')->assertOk()->assertJsonPath('recordsTotal', 1);
    $this->get("/admin/catalog/{$p->id}/edit")->assertOk()->assertSee(url("/admin/products/{$p->id}/edit"), false);
    $this->putJson("/admin/catalog/{$p->id}", ['price' => '150', 'sale_price' => '120', 'cost' => '50', 'is_active' => 1])->assertOk();
    $p->refresh();
    expect((string) $p->price)->toBe('150.00')->and((string) $p->sale_price)->toBe('120.00')->and((string) $p->effectivePrice())->toBe('120.00')->and($p->is_active)->toBeTrue();
    $this->putJson("/admin/catalog/{$p->id}", ['price' => '150', 'cost' => '50', 'is_active' => 0])->assertOk();
    expect($p->fresh()->is_active)->toBeFalse();
    $this->get('/admin/products')->assertOk()->assertSee(url('/admin/catalog'), false);
});

it('the Products CRUD saves a product without a cost (products.cost NOT NULL → 0) and keeps the cost on a blank edit (QC Phase 10)', function () {
    $cat = App\Models\Category::create(['name' => ['ar' => 'قسم', 'en' => 'Cat'], 'slug' => 'qc-cat', 'path' => '/', 'depth' => 0]);
    $this->actingAs($this->super, 'admin');
    $base = ['type' => 'simple', 'name' => ['ar' => 'منتج', 'en' => 'Item'], 'sku' => 'QC-1', 'category_ids' => [$cat->id]];
    $this->postJson('/admin/products', $base)->assertOk();
    $p = Product::query()->where('sku', 'QC-1')->firstOrFail();
    expect((float) $p->cost)->toBe(0.0)->and($p->price)->toBeNull()->and($p->is_active)->toBeFalse();
    $this->putJson("/admin/products/{$p->id}", $base + ['price' => '90', 'cost' => '30', 'is_active' => 1])->assertOk();
    $this->putJson("/admin/products/{$p->id}", $base + ['price' => '95', 'is_active' => 1])->assertOk();
    $p->refresh();
    expect((string) $p->price)->toBe('95.00')->and((float) $p->cost)->toBe(30.0)->and($p->is_active)->toBeTrue();
});

it('storefront: home, product, cart and checkout on the one host', function () {
    codUpfront(false);
    $branch = Branch::first();
    $p = Product::create(['type' => 'simple', 'name' => ['ar' => 'منتج واحد', 'en' => 'One item'], 'slug' => 'one', 'sku' => 'ONE']);
    listProduct($p, ['price' => 80, 'cost' => 30]);
    app(StockLedger::class)->record($branch->id, $p->id, 0, T::PURCHASE_IN, 5, '30.00');
    $this->get('/')->assertRedirect('/ar');
    $this->get('/ar')->assertOk()->assertSee('dir="rtl"', false)->assertSee('Egypt Store');
    $this->get('/en/product/one')->assertOk()->assertSee('One item')->assertSee('80.00')->assertSee('<link rel="canonical" href="'.url('/en/product/one').'"', false);
    $this->actingAs(Customer::where('email', 'customer@egy.test')->first(), 'web');
    $this->withCredentials()->postJson('/ar/cart/items', ['product_id' => $p->id, 'variant_id' => 0, 'quantity' => 2])->assertOk();
    $this->withCredentials()->getJson('/ar/cart')->assertOk()->assertJsonPath('data.totals.subtotal', '160.00');
    $this->withCredentials()->get('/ar/checkout')->assertOk();
    $payload = withSavedAddress(['name' => 'Ali', 'phone' => '01000000100', 'city_id' => City::first()->id, 'address' => 'St 1', 'shipping_company_id' => ShippingCompany::first()->id,
        'payment_method_id' => PaymentMethod::where('type', 'cod')->first()->id]);
    $r = $this->withCredentials()->postJson('/ar/checkout', $payload)->assertOk();
    $this->get('/ar/order/'.$r->json('data.token'))->assertOk()->assertSee('منتج واحد');
});

it('LegacyHostRedirect: old {slug}.host pages 301 to the same path + query on APP_URL; api/* and webhooks/* are served directly', function () {
    $host = parse_url(config('app.url'), PHP_URL_HOST);
    $this->get("http://egy.$host/ar/category/x?sort=price_asc&page=2")->assertStatus(301)->assertRedirect(config('app.url').'/ar/category/x?page=2&sort=price_asc');   // same query (Symfony normalises the order)
    $this->get("http://egy.$host/admin")->assertStatus(301)->assertRedirect(config('app.url').'/admin');
    $this->get("http://sa.$host/")->assertStatus(301)->assertRedirect(config('app.url').'/');
    $this->getJson("http://egy.$host/api/v1/health")->assertOk();
    $this->getJson("http://egy.$host/api/v1/store")->assertOk()->assertJsonPath('data.name', 'Egypt Store');
    expect($this->getJson("http://egy.$host/api/v1/store")->getContent())->not->toContain("egy.$host");
    // a webhook POST reaches the payment controller on the legacy host (COD has no gateway: 404, unknown id: 404) — never a redirect
    $cod = PaymentMethod::where('type', 'cod')->first();
    $this->postJson("http://egy.$host/webhooks/payments/{$cod->id}", ['x' => 1])->assertNotFound();
    $this->postJson("http://egy.$host/webhooks/payments/999999", ['x' => 1])->assertNotFound();
    // the main host and unrelated hosts are untouched
    $this->get(config('app.url').'/ar')->assertOk();
});
