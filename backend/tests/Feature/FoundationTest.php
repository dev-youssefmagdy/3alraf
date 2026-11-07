<?php

use App\Models\Language;
use App\Models\Admin;
use Database\Seeders\FoundationSeeder;

beforeEach(function () {
    $this->seed(FoundationSeeder::class);
});

it('redirects guests to the admin login and logs in via json', function () {
    $this->get("/admin")->assertRedirect("/admin/login");
    $this->postJson("/admin/login/validate", [])->assertStatus(422)->assertJsonValidationErrors(['phone', 'password']);
    $this->postJson("/admin/login", ['country' => 'EG', 'phone' => '01000000001', 'password' => 'password'])
        ->assertOk()->assertJson(['success' => true]);
    $this->get("/admin")->assertOk();
});

it('redirects the site root to the default locale and rejects unknown locales', function () {
    $this->get("/")->assertRedirect("/ar");
    $this->get("/ar")->assertOk()->assertSee('dir="rtl"', false);
    $this->get("/en")->assertOk()->assertSee('dir="ltr"', false);
    $this->get("/fr")->assertNotFound();
});

it('one admin guard: the owner signs in to the same panel, a storefront customer session never opens /admin, logout ends it', function () {
    Admin::query()->where('email', 'owner@egy.test')->update(['otp_exempt' => true]); // this test is about guards, not the OTP step
    $this->actingAs(\App\Models\Customer::where('email', 'customer@egy.test')->first(), 'web');
    $this->get('/admin')->assertRedirect('/admin/login');
    $this->postJson('/admin/login', ['country' => 'EG', 'phone' => '0'.substr(Admin::query()->where('email', 'owner@egy.test')->value('phone'), 2), 'password' => 'password'])->assertOk();
    $this->get('/admin')->assertOk();
    expect(auth('admin')->user()->email)->toBe('owner@egy.test');
    $this->post('/admin/logout');
    $this->get('/admin')->assertRedirect('/admin/login');
});

it('runs the language CRUD with pre-validation', function () {
    $this->actingAs(Admin::first(), 'admin');
    $base = "/admin/languages";
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

it('enforces permissions for non super admins', function () {
    $a = Admin::create(['name' => 'p', 'email' => 'p@x.test', 'password' => 'password']);
    $this->actingAs($a, 'admin')->get("/admin/languages")->assertForbidden();
});
