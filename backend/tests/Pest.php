<?php

use App\Models\City;
use App\Models\Customer;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature');
pest()->extend(TestCase::class)->in('Unit');

/**
 * Explicit COD shipping-prepay mode for a tenant (the real default, no setting, is UPFRONT = true).
 * Legacy tests that model "carrier collects the full total" call codUpfront($tenant, false).
 */
function codUpfront(Tenant $tenant, bool $on): Tenant
{
    $tenant->update(['settings' => array_merge($tenant->settings ?? [], ['cod_collect_shipping_upfront' => $on])]);

    return $tenant->refresh();
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

/** Login payload (country + phone + password) for an existing admin looked up by e-mail, central or tenant. */
function adminCreds(string $email, string $password = 'password'): array
{
    $a = App\Models\TenantAdmin::withoutGlobalScopes()->where('email', $email)->first() ?? App\Models\CentralAdmin::where('email', $email)->first();

    return ['country' => 'EG', 'phone' => '0'.substr($a->phone, 2), 'password' => $password];
}
