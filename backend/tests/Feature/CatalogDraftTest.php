<?php

// Catalog drafts (`is_draft`): saved with only a name, always hidden from the store, published by a full save.

use App\Models\Admin;
use App\Models\Attribute;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Language;
use App\Models\Product;
use App\Services\ProductService;
use Database\Seeders\FoundationSeeder;

beforeEach(function () {
    $this->seed(FoundationSeeder::class);
    $this->actingAs(Admin::query()->where('email', 'owner@egy.test')->first(), 'admin');
    $this->name = fn (string $n) => Language::activeList()->mapWithKeys(fn ($l) => [$l->code => "$n {$l->code}"])->all();
    $this->json = ['Accept' => 'application/json'];
});

it('saves a product draft with only a name, inactive, with a placeholder sku', function () {
    $this->post('/admin/products', ['type' => 'simple', 'name' => ($this->name)('Mug'), 'is_active' => 1, 'save_as_draft' => 1], $this->json)->assertOk();

    $p = Product::query()->latest('id')->first();
    expect($p->is_draft)->toBeTrue()
        ->and($p->is_active)->toBeFalse()
        ->and(ProductService::isDraftSku($p->sku))->toBeTrue()
        ->and($p->categories)->toBeEmpty();
});

it('still requires the name for a draft', function () {
    $this->post('/admin/products', ['type' => 'simple', 'save_as_draft' => 1], $this->json)
        ->assertStatus(422)->assertJsonValidationErrors('name');
});

it('requires the full data to publish a draft, then clears the draft flag', function () {
    $this->post('/admin/products', ['type' => 'simple', 'name' => ($this->name)('Mug'), 'save_as_draft' => 1], $this->json)->assertOk();
    $p = Product::query()->latest('id')->first();

    $this->post("/admin/products/{$p->id}", ['_method' => 'PUT', 'type' => 'simple', 'name' => ($this->name)('Mug')], $this->json)
        ->assertStatus(422)->assertJsonValidationErrors(['sku', 'category_ids']);

    $cat = Category::create(['name' => ($this->name)('Cups'), 'slug' => 'cups', 'path' => '', 'depth' => 0, 'is_active' => true]);
    $this->post("/admin/products/{$p->id}", ['_method' => 'PUT', 'type' => 'simple', 'name' => ($this->name)('Mug'), 'sku' => 'MUG-1',
        'category_ids' => [$cat->id], 'price' => '100', 'is_active' => 1], $this->json)->assertOk();

    expect($p->fresh())->is_draft->toBeFalse()->is_active->toBeTrue()->sku->toBe('MUG-1');
});

it('ignores "save as draft" on a published record', function () {
    $this->post('/admin/brands', ['name' => ($this->name)('Acme'), 'is_active' => 1], $this->json)->assertOk();
    $b = Brand::query()->latest('id')->first();

    $this->post("/admin/brands/{$b->id}", ['_method' => 'PUT', 'name' => ($this->name)('Acme'), 'is_active' => 1, 'save_as_draft' => 1], $this->json)->assertOk();

    expect($b->fresh())->is_draft->toBeFalse()->is_active->toBeTrue();
});

it('never lets a draft become active through another path', function () {
    $this->post('/admin/brands', ['name' => ($this->name)('Draft brand'), 'save_as_draft' => 1], $this->json)->assertOk();
    $b = Brand::query()->latest('id')->first();

    $b->update(['is_active' => true]);

    expect($b->fresh())->is_draft->toBeTrue()->is_active->toBeFalse();
});

it('saves an attribute draft without code or values and keeps it out of the variant picker', function () {
    $this->post('/admin/attributes', ['name' => ($this->name)('Size'), 'type' => 'select', 'save_as_draft' => 1], $this->json)->assertOk();
    $a = Attribute::query()->latest('id')->first();

    expect($a->is_draft)->toBeTrue()->and($a->values)->toBeEmpty();
    $this->get('/admin/products/create')->assertOk()->assertViewHas('attributes', fn ($attrs) => ! $attrs->contains('id', $a->id));
});
