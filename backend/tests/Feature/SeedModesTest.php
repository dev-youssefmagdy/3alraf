<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Admin;
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
    expect(Admin::where('email', 'admin@3alraf.test')->exists())->toBeTrue()
        ->and(DB::table('stock_movements')->count())->toBeGreaterThan(0)
        ->and(Product::query()->whereNotNull('price')->count())->toBe(35)
        ->and(Customer::query()->where('email', 'customer@egy.test')->exists())->toBeTrue();

    // something real the purge must not touch: a customer with an order, a store admin whose password was changed
    $real = Customer::query()->create(['name' => 'Real', 'email' => 'real@example.com', 'phone' => '01012345678', 'password' => 'secret-pass-1', 'is_active' => true]);
    Admin::query()->where('email', 'owner@egy.test')->first()->update(['password' => 'a-real-password-1']);

    $this->artisan('seed:live')->expectsConfirmation('Delete it permanently?', 'no')->assertFailed();   // declined: nothing changes
    expect(Admin::where('email', 'admin@3alraf.test')->exists())->toBeTrue();

    $this->artisan('seed:live --force')->assertSuccessful();

    expect(Admin::where('email', 'admin@3alraf.test')->exists())->toBeFalse()
        ->and(Admin::query()->where('email', 'owner@egy.test')->exists())->toBeTrue()   // changed password = real
        ->and(Customer::query()->where('email', 'customer@egy.test')->exists())->toBeFalse()
        ->and(Customer::query()->whereKey($real->id)->exists())->toBeTrue()
        ->and(DB::table('discount_codes')->where('code', 'WELCOME10')->exists())->toBeFalse()
        ->and(DB::table('suppliers')->where('name', 'Demo Supplier')->exists())->toBeFalse()
        ->and(DB::table('affiliates')->where('email', 'like', 'aff@%')->exists())->toBeFalse()
        ->and(DB::table('static_pages')->count())->toBe(0)
        ->and(PaymentMethod::query()->pluck('type')->map->value->all())->toBe(['cod'])
        ->and(DB::table('stock_movements')->count())->toBe(0)
        ->and(DB::table('stock_levels')->count())->toBe(0)
        ->and(setting('currency_code'))->toBe('EGP')
        ->and(Country::count())->toBeGreaterThan(240)
        ->and(City::query()->count())->toBeGreaterThan(200);

    // the whole catalog (hidden phase-2 products too), listed and active, with no stock
    $listings = Product::query()->whereNotNull('price')->get();
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
    expect(Product::query()->whereNotNull('price')->count())->toBe(35)->and(DB::table('stock_movements')->count())->toBe(0);
});

it('seed:dev refuses to run in production', function () {
    app()->detectEnvironment(fn () => 'production');
    $this->artisan('seed:dev')->assertFailed();
    expect(Admin::count())->toBe(0);
});

