<?php

use App\Models\CentralAdmin;
use App\Models\City;
use App\Models\Country;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Tenant;
use App\Models\TenantAdmin;
use App\Models\TenantProduct;
use Illuminate\Support\Facades\DB;
use Database\Seeders\GiftCatalogNoStockSeeder;
use Database\Seeders\GiftCatalogSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/** A slice of the catalog (25 available + 10 hidden products) so the test does not encode 1,100+ pictures. */
function sliceCatalog(): void
{
    $slice = fn (array $rows) => [...array_slice(array_values(array_filter($rows, fn ($r) => $r['active'])), 0, 25), ...array_slice(array_values(array_filter($rows, fn ($r) => ! $r['active'])), 0, 10)];
    app()->bind(GiftCatalogSeeder::class, fn () => new class($slice) extends GiftCatalogSeeder
    {
        public function __construct(private \Closure $slice) {}

        protected function rows(): array
        {
            return ($this->slice)(parent::rows());
        }
    });
    app()->bind(GiftCatalogNoStockSeeder::class, fn () => new class($slice) extends GiftCatalogNoStockSeeder
    {
        public function __construct(private \Closure $slice) {}

        protected function rows(): array
        {
            return ($this->slice)(parent::rows());
        }
    });
}

it('seed:dev fills fake data with stock, seed:live removes the fake data and lists the available products with no stock', function () {
    sliceCatalog();
    ob_start();
    imagepng(imagecreatetruecolor(300, 200));
    Http::fake(['*' => Http::response((string) ob_get_clean(), 200, ['Content-Type' => 'image/png'])]);   // every "download" is a small real picture

    $this->artisan('seed:dev')->assertSuccessful();
    $egy = Tenant::where('slug', 'egy')->first();
    expect(CentralAdmin::where('email', 'admin@3alraf.test')->exists())->toBeTrue()
        ->and(DB::table('stock_movements')->count())->toBeGreaterThan(0)
        ->and(TenantProduct::withoutGlobalScopes()->where('tenant_id', $egy->id)->count())->toBe(35)
        ->and(Customer::withoutGlobalScopes()->where('email', 'customer@egy.test')->exists())->toBeTrue();

    // something real the purge must not touch: a customer with an order, a store admin whose password was changed
    $real = Customer::withoutGlobalScopes()->create(['tenant_id' => $egy->id, 'name' => 'Real', 'email' => 'real@example.com', 'phone' => '01012345678', 'password' => 'secret-pass-1', 'is_active' => true]);
    TenantAdmin::withoutGlobalScopes()->where('email', 'owner@sa.test')->first()->update(['password' => 'a-real-password-1']);

    $this->artisan('seed:live')->expectsConfirmation('Delete it permanently?', 'no')->assertFailed();   // declined: nothing changes
    expect(CentralAdmin::where('email', 'admin@3alraf.test')->exists())->toBeTrue();

    $this->artisan('seed:live --force')->assertSuccessful();

    expect(CentralAdmin::where('email', 'admin@3alraf.test')->exists())->toBeFalse()
        ->and(TenantAdmin::withoutGlobalScopes()->where('email', 'owner@egy.test')->exists())->toBeFalse()
        ->and(TenantAdmin::withoutGlobalScopes()->where('email', 'owner@sa.test')->exists())->toBeTrue()   // changed password = real
        ->and(Customer::withoutGlobalScopes()->where('email', 'customer@egy.test')->exists())->toBeFalse()
        ->and(Customer::withoutGlobalScopes()->whereKey($real->id)->exists())->toBeTrue()
        ->and(DB::table('discount_codes')->where('code', 'WELCOME10')->exists())->toBeFalse()
        ->and(DB::table('suppliers')->where('name', 'Demo Supplier')->exists())->toBeFalse()
        ->and(DB::table('affiliates')->where('email', 'like', 'aff@%')->exists())->toBeFalse()
        ->and(DB::table('static_pages')->count())->toBe(0)
        ->and(PaymentMethod::withoutGlobalScopes()->where('tenant_id', $egy->id)->pluck('type')->map->value->all())->toBe(['cod'])
        ->and(DB::table('stock_movements')->count())->toBe(0)
        ->and(DB::table('stock_levels')->count())->toBe(0)
        ->and(Tenant::count())->toBe(2)
        ->and(Country::count())->toBeGreaterThan(240)
        ->and(City::withoutGlobalScopes()->where('tenant_id', $egy->id)->count())->toBeGreaterThan(200);

    // the whole catalog (hidden phase-2 products too), listed and active, with no stock
    $listings = TenantProduct::withoutGlobalScopes()->where('tenant_id', $egy->id)->get();
    expect($listings->count())->toBe(35)->and($listings->every(fn ($l) => $l->is_active))->toBeTrue()
        ->and(DB::table('products')->where('sku', 'like', 'GFT-%')->count())->toBe(35);

    // pictures are stored like real uploads: AVIF main + thumb with the WebP fallbacks beside them
    $paths = DB::table('images')->pluck('path', 'thumb_path');
    expect($paths)->toHaveCount(35);
    foreach ($paths as $thumb => $path) {
        expect($path)->toEndWith('.avif')->and($thumb)->toEndWith('_thumb.avif');
        foreach (\App\Support\MediaPaths::family($path) as $file) {
            Storage::disk(config('media.disk'))->assertExists($file);
        }
    }

    $this->artisan('seed:live --force')->assertSuccessful();             // idempotent
    expect(TenantProduct::withoutGlobalScopes()->where('tenant_id', $egy->id)->count())->toBe(35)->and(DB::table('stock_movements')->count())->toBe(0);
});

it('seed:dev refuses to run in production', function () {
    app()->detectEnvironment(fn () => 'production');
    $this->artisan('seed:dev')->assertFailed();
    expect(CentralAdmin::count())->toBe(0);
});

it('a newly created EGP store starts with its cities and the whole catalog listed without stock', function () {
    config(['tenancy.seed_defaults' => true]);
    sliceCatalog();
    ob_start();
    imagepng(imagecreatetruecolor(300, 200));
    Http::fake(['*' => Http::response((string) ob_get_clean(), 200, ['Content-Type' => 'image/png'])]);

    $this->artisan('seed:live --force')->assertSuccessful();    // the shared catalog exists (no store yet, so no listings)
    $this->artisan('tenants:create egy "3alraf Egypt" --country=EG --currency=EGP --owner-phone=01012345678')->assertSuccessful();
    $egy = Tenant::where('slug', 'egy')->first();
    expect(City::withoutGlobalScopes()->where('tenant_id', $egy->id)->count())->toBeGreaterThan(200)
        ->and(TenantProduct::withoutGlobalScopes()->where('tenant_id', $egy->id)->count())->toBe(35)
        ->and(DB::table('stock_movements')->count())->toBe(0);

    // a store in another currency gets no EGP-priced listings (and cities only for countries that have a database/data/cities file)
    $this->artisan('tenants:create sa "3alraf KSA" --country=SA --currency=SAR --owner-phone=0501234567')->assertSuccessful();
    $sa = Tenant::where('slug', 'sa')->first();
    expect(TenantProduct::withoutGlobalScopes()->where('tenant_id', $sa->id)->count())->toBe(0);
});
