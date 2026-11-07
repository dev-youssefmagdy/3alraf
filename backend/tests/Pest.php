<?php

use App\Models\City;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

require_once __DIR__.'/Helpers/growth.php';

pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature');
pest()->extend(TestCase::class)->in('Unit');

/**
 * Explicit COD shipping-prepay mode (the real default, no setting, is UPFRONT = true).
 * Legacy tests that model "carrier collects the full total" call codUpfront(false).
 */
function codUpfront(bool $on): void
{
    Setting::put('cod_collect_shipping_upfront', $on);
}

/** Merge values into the site-wide settings (Settings → General / former store settings JSON). */
function settings(array $values): void
{
    Setting::putMany($values);
}

/**
 * Put a catalog product on sale (single store: price/sale/cost/listing status live on `products`).
 * $listing: price, sale_price, cost, is_active, min_stock_alert_qty, content, gift_* (default active).
 */
function listProduct(Product|int $product, array $listing = []): Product
{
    $p = $product instanceof Product ? $product : Product::query()->findOrFail($product);
    unset($listing['product_id']);
    $p->forceFill(['is_active' => true, ...$listing])->save();

    return $p->refresh();
}

/** Unique fake proof image per call (identical bytes would be rejected as a reused proof). Offline checkouts require one. */
function testProof(): UploadedFile
{
    static $n = 0;
    $n++;

    return UploadedFile::fake()->image("proof{$n}.png", 50 + $n, 20);
}

/**
 * Web checkout is signed-in-only: a logged-in customer sends a saved address_id, not inline address fields.
 * Saves the payload's name/phone/city/address as that customer's address and returns the payload with address_id.
 */
function withSavedAddress(array $payload, string $email = 'customer@egy.test'): array
{
    $c = Customer::where('email', $email)->first();
    $a = $c->addresses()->create(['label' => 'T', 'name' => $payload['name'] ?? 'Ali', 'phone' => $payload['phone'] ?? '0100', 'city_id' => $payload['city_id'] ?? City::first()->id, 'address' => $payload['address'] ?? 'St 1']);

    return $payload + ['address_id' => $a->id];
}

/** Admins sign in by phone: stable, unique Egyptian test numbers derived from a label (e.g. an e-mail). */
function staffPhone(string $label): array
{
    return ['country' => 'EG', 'phone' => '010'.str_pad((string) (crc32($label) % 100000000), 8, '0', STR_PAD_LEFT)];
}

/** Login payload (country + phone + password) for an existing admin looked up by e-mail. */
function adminCreds(string $email, string $password = 'password'): array
{
    $a = App\Models\Admin::query()->where('email', $email)->firstOrFail();

    return ['country' => 'EG', 'phone' => '0'.substr($a->phone, 2), 'password' => $password];
}

/** Flip growth feature switches (setting `growth.features.{key}`) without the audited admin flow. */
function growthFeatures(bool $on, string ...$keys): void
{
    $growth = (array) (setting('growth') ?? []);
    foreach ($keys as $k) {
        $growth['features'][$k] = $on;
    }
    Setting::put('growth', $growth);
}

/** Every site-wide setting, freshly decoded from the `settings` table. */
function allSettings(): array
{
    Setting::flush();

    return Setting::map();
}
