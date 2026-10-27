<?php

use App\Models\Affiliate;
use App\Models\City;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Tenant;
use Database\Seeders\FoundationSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->seed(FoundationSeeder::class);
    $this->tenant = Tenant::where('slug', 'egy')->first();
    app()->instance('currentTenant', $this->tenant);
    $this->s = 'http://egy.3alraf.test/ar';
    $this->api = 'http://egy.3alraf.test/api/v1';
    $this->customer = Customer::where('email', 'customer@egy.test')->first();
});

function addrPayload(array $o = []): array
{
    return $o + ['name' => 'Ali', 'phone' => '01008489183', 'city_id' => City::first()->id, 'address' => 'St 1'];
}

it('stores national number + tenant code for an address and ignores a client-sent code', function () {
    $this->actingAs($this->customer, 'web')->postJson("{$this->s}/account/addresses", addrPayload(['phone' => '+201008489183', 'phone_country_code' => '+999']))->assertSuccessful();
    $a = $this->customer->addresses()->latest('id')->first();
    expect($a->phone)->toBe('01008489183')->and($a->phone_country_code)->toBe('+20')->and($a->phone_full)->toBe('+201008489183')->and($a->phone_display)->toBe('+20 100 848 9183');
});

it('rejects a different country code and short numbers', function () {
    $this->actingAs($this->customer, 'web')->postJson("{$this->s}/account/addresses", addrPayload(['phone' => '+966501234567']))->assertStatus(422)->assertJsonValidationErrors('phone');
    $this->actingAs($this->customer, 'web')->postJson("{$this->s}/account/addresses", addrPayload(['phone' => '0100']))->assertStatus(422)->assertJsonValidationErrors('phone');
});

it('uses the tenant country code per tenant (SA => +966)', function () {
    $this->tenant->update(['country' => 'SA']);
    $this->actingAs($this->customer, 'web')->postJson("{$this->s}/account/addresses", addrPayload(['phone' => '0501234567']))->assertSuccessful();
    $a = $this->customer->addresses()->latest('id')->first();
    expect($a->phone_country_code)->toBe('+966')->and($a->phone_full)->toBe('+966501234567');
    $this->actingAs($this->customer, 'web')->postJson("{$this->s}/account/addresses", addrPayload(['phone' => '01008489183']))->assertStatus(422);
});

it('registers a customer and updates a profile with code', function () {
    $this->postJson("{$this->s}/register", ['name' => 'Sara', 'email' => 'sara@egy.test', 'phone' => '1008489183', 'password' => 'secret123', 'password_confirmation' => 'secret123'])->assertOk();
    $c = Customer::where('email', 'sara@egy.test')->first();
    expect($c->phone)->toBe('01008489183')->and($c->phone_country_code)->toBe('+20');
    $this->actingAs($c, 'web')->putJson("{$this->s}/account/profile", ['name' => 'Sara', 'email' => 'sara@egy.test', 'phone' => ''])->assertOk();
    expect($c->fresh()->phone_country_code)->toBeNull();
});

it('affiliate register stores code', function () {
    $this->postJson("{$this->s}/affiliate/register", ['name' => 'Aff', 'email' => 'newaff@example.com', 'phone' => '01112345678', 'password' => 'secret123', 'password_confirmation' => 'secret123']);
    $a = Affiliate::where('email', 'newaff@example.com')->first();
    expect($a?->phone_country_code)->toBe('+20')->and($a->phone_full)->toBe('+201112345678');
});

it('mobile API address returns phone, code and full', function () {
    $tok = $this->customer->createToken('t')->plainTextToken;
    $r = $this->withToken($tok)->postJson("{$this->api}/addresses", addrPayload(['phone' => '00201008489183']))->assertStatus(201);
    $r->assertJsonPath('data.phone', '01008489183')->assertJsonPath('data.phone_country_code', '+20')->assertJsonPath('data.phone_full', '+201008489183');
});


it('backfill parses legacy rows and leaves unparsable ones for phone:audit', function () {
    $good = $this->customer->addresses()->create(['label' => 'g', 'name' => 'A', 'phone' => '+201008489183', 'city_id' => City::first()->id, 'address' => 'x']);
    $bad = $this->customer->addresses()->create(['label' => 'b', 'name' => 'A', 'phone' => 'abc', 'city_id' => City::first()->id, 'address' => 'x']);
    DB::table('customer_addresses')->whereIn('id', [$good->id, $bad->id])->update(['phone_country_code' => null]);
    (include database_path('migrations/2026_11_22_100001_phone_country_code.php'))->backfill();
    expect($good->fresh()->phone)->toBe('01008489183')->and($good->fresh()->phone_country_code)->toBe('+20')
        ->and($bad->fresh()->phone_country_code)->toBeNull();
    $this->artisan('phone:audit')->assertFailed();
});
