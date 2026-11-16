<?php

// Product / variant weight is stored in whole grams (migration 2027_01_14_000000 converted kg ×1000).

use App\Models\Admin;
use App\Models\Language;
use App\Models\Product;
use Database\Seeders\FoundationSeeder;

beforeEach(function () {
    $this->seed(FoundationSeeder::class);
    $this->actingAs(Admin::query()->where('email', 'owner@egy.test')->first(), 'admin');
    $this->name = Language::activeList()->mapWithKeys(fn ($l) => [$l->code => 'Watch'])->all();
});

it('stores the product weight in grams', function () {
    $this->post('/admin/products', ['type' => 'simple', 'name' => $this->name, 'weight' => 250, 'save_as_draft' => 1], ['Accept' => 'application/json'])->assertOk();

    expect(Product::query()->latest('id')->first()->weight)->toBe(250);
});

it('rejects fractional grams', function () {
    $this->post('/admin/products', ['type' => 'simple', 'name' => $this->name, 'weight' => 0.25, 'save_as_draft' => 1], ['Accept' => 'application/json'])
        ->assertStatus(422)->assertJsonValidationErrors('weight');
});
