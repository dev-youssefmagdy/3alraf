<?php

// Sale price may equal the price (no discount shown); above the price is still refused, for products and variants.

use App\Models\Admin;
use App\Models\Attribute;
use App\Models\Category;
use App\Models\Language;
use App\Models\Product;
use App\Services\Storefront\CardPresenter;
use Database\Seeders\FoundationSeeder;

beforeEach(function () {
    $this->seed(FoundationSeeder::class);
    $this->actingAs(Admin::query()->where('email', 'owner@egy.test')->first(), 'admin');
    $tr = fn (string $s) => Language::activeList()->mapWithKeys(fn ($l) => [$l->code => $s])->all();
    $cat = Category::create(['name' => $tr('Watches'), 'slug' => 'watches', 'path' => '', 'depth' => 0, 'is_active' => true]);
    $this->base = ['type' => 'simple', 'name' => $tr('Watch'), 'sku' => 'W1', 'category_ids' => [$cat->id], 'is_active' => 1, 'price' => '1500'];
    $this->attr = Attribute::create(['name' => $tr('Dial'), 'code' => 'dial', 'type' => 'select']);
    $this->val = $this->attr->values()->create(['value' => $tr('Black'), 'sort_order' => 0]);
});

it('accepts a sale price equal to the price', function () {
    $this->postJson('/admin/products', $this->base + ['sale_price' => '1500'])->assertOk();

    expect(Product::query()->first()->sale_price)->toBe('1500.00');
});

it('still refuses a sale price above the price', function () {
    $this->postJson('/admin/products', $this->base + ['sale_price' => '1500.01'])->assertStatus(422)->assertJsonValidationErrors('sale_price');
});

it('applies the same rule to variant prices', function () {
    $variant = fn (string $sale) => array_merge($this->base, ['type' => 'variable', 'sku' => 'W2',
        'variants' => ['n0' => ['values' => [$this->val->id], 'sku' => 'W2-B', 'price' => '900', 'sale_price' => $sale, 'is_active' => 1]]]);

    $this->postJson('/admin/products/validate', $variant('900.01'))->assertStatus(422)->assertJsonValidationErrors('variants.n0.sale_price');
    $this->postJson('/admin/products', $variant('900'))->assertOk();
});

it('shows no discount on the storefront card when both are equal', function () {
    $this->postJson('/admin/products', $this->base + ['sale_price' => '1500'])->assertOk();
    $p = app(CardPresenter::class)->decorate(collect([Product::query()->first()]))->first();

    expect($p->display['old'])->toBeNull()->and($p->display['percent'])->toBeNull();
});
