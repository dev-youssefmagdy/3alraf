<?php

// Filter bars of the admin lists: declared in BaseDataTable::filters(), rendered by <x-datatable>, applied by ajax().

use App\Models\Admin;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Supplier;
use Database\Seeders\FoundationSeeder;

beforeEach(function () {
    $this->seed(FoundationSeeder::class);
    $this->actingAs(Admin::query()->where('email', 'owner@egy.test')->first(), 'admin');

    $this->product = function (string $key, array $extra = []): Product {
        return Product::query()->create(['type' => 'simple', 'name' => ['ar' => "منتج $key", 'en' => "Product $key"], 'slug' => "filter-$key", 'sku' => "SKU-$key", 'is_active' => true, ...$extra]);
    };
    $this->skus = fn (array $query) => collect($this->getJson(route('admin.products.datatable', $query + ['draw' => 1, 'start' => 0, 'length' => 100]))->assertOk()->json('data'))->pluck('sku')->sort()->values()->all();
});

it('filters products by name in either language, barcode and brand', function () {
    $brand = Brand::query()->create(['name' => ['ar' => 'ماركة', 'en' => 'Acme'], 'slug' => 'acme', 'is_active' => true]);
    ($this->product)('one', ['barcode' => '6221111', 'brand_id' => $brand->id]);
    ($this->product)('two', ['barcode' => '6222222']);

    expect(($this->skus)(['name' => 'منتج one']))->toBe(['SKU-one'])
        ->and(($this->skus)(['name' => 'PRODUCT TWO']))->toBe(['SKU-two'])
        ->and(($this->skus)(['barcode' => '2222']))->toBe(['SKU-two'])
        ->and(($this->skus)(['brand_id' => $brand->id]))->toBe(['SKU-one'])
        ->and(($this->skus)(['name' => 'Product', 'barcode' => '6221']))->toBe(['SKU-one'])
        ->and(($this->skus)(['name' => '100%']))->toBe([]);
});

it('finds a product by the barcode of one of its variants', function () {
    $p = ($this->product)('var', ['type' => 'variable']);
    ProductVariant::query()->create(['product_id' => $p->id, 'combination_hash' => 'h1', 'sku' => 'V-1', 'barcode' => '7770001']);
    ($this->product)('other');

    expect(($this->skus)(['barcode' => '7770001']))->toBe(['SKU-var']);
});

it('filters products by category including its subcategories', function () {
    $parent = Category::query()->create(['name' => ['ar' => 'أب', 'en' => 'Parent'], 'slug' => 'parent', 'is_active' => true, 'depth' => 0, 'path' => '']);
    $parent->update(['path' => (string) $parent->id]);
    $child = Category::query()->create(['parent_id' => $parent->id, 'name' => ['ar' => 'ابن', 'en' => 'Child'], 'slug' => 'child', 'is_active' => true, 'depth' => 1, 'path' => $parent->id.'/0']);
    $child->update(['path' => $parent->id.'/'.$child->id]);
    ($this->product)('in-parent')->categories()->attach($parent);
    ($this->product)('in-child')->categories()->attach($child);
    ($this->product)('outside');

    expect(($this->skus)(['category_id' => $parent->id]))->toBe(['SKU-in-child', 'SKU-in-parent'])
        ->and(($this->skus)(['category_id' => $child->id]))->toBe(['SKU-in-child']);
});

it('keeps the drafts tab and ignores values the controls could not send', function () {
    ($this->product)('live');
    ($this->product)('draft', ['is_draft' => true]);

    expect(($this->skus)(['drafts' => 1, 'name' => 'Product']))->toBe(['SKU-draft'])
        ->and(($this->skus)(['brand_id' => 999999, 'status' => 'nope']))->toBe(['SKU-draft', 'SKU-live'])
        ->and(($this->skus)(['name' => ['x']]))->toBe(['SKU-draft', 'SKU-live']);
});

it('shows the product filter bar', function () {
    $this->get(route('admin.products.index'))->assertOk()
        ->assertSee('name="barcode"', false)->assertSee('name="category_id"', false)->assertSee('name="brand_id"', false)->assertSee('name="name"', false)->assertSee('dt-actions', false);
});

it('filters a plain list', function () {
    Supplier::query()->create(['name' => 'Nile Traders', 'phone' => '0100', 'is_active' => true]);
    Supplier::query()->create(['name' => 'Delta Foods', 'phone' => '0111', 'is_active' => false]);
    $names = fn (array $q) => collect($this->getJson(route('admin.suppliers.datatable', $q + ['draw' => 1, 'start' => 0, 'length' => 10]))->assertOk()->json('data'))->pluck('name')->all();

    expect($names(['name' => 'nile']))->toBe(['Nile Traders'])->and($names(['status' => '0']))->toBe(['Delta Foods']);
});

it('runs every declared filter of every table without a database error', function () {
    $tables = collect(glob(app_path('DataTables/*DataTable.php')))->map(fn ($f) => 'App\\DataTables\\'.basename($f, '.php'))
        ->reject(fn ($c) => $c === \App\DataTables\BaseDataTable::class);

    $checked = 0;
    foreach ($tables as $class) {
        $table = app($class);
        foreach ($table->filters() as $filter) {
            $value = match ($filter->type) { 'date' => '2026-01-01', 'select' => (string) (array_key_first($filter->options()) ?? ''), default => 'x' };
            $query = $table->query();
            $filter->apply($query, $value);
            $query->count();
            $checked++;
        }
    }

    expect($checked)->toBeGreaterThan(80);
});
