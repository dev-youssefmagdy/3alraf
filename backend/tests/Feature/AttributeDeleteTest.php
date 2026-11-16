<?php

// Deleting an attribute whose values are used only by REMOVED (soft-deleted) variants: allowed. Used by a live variant: blocked.

use App\Models\Admin;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Category;
use App\Models\Language;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Retention\SoftDeletePurger;
use Database\Seeders\FoundationSeeder;

beforeEach(function () {
    $this->seed(FoundationSeeder::class);
    $this->actingAs(Admin::query()->where('email', 'owner@egy.test')->first(), 'admin');
    $this->tr = fn (string $s) => Language::activeList()->mapWithKeys(fn ($l) => [$l->code => $s])->all();
    $this->attr = function (string $code, array $values) {
        $this->postJson('/admin/attributes', ['name' => ($this->tr)(ucfirst($code)), 'code' => $code, 'type' => 'select',
            'values' => array_map(fn ($v) => ['value' => ($this->tr)($v)], $values)])->assertOk();

        return Attribute::query()->where('code', $code)->with('values')->first();
    };
    $cat = Category::create(['name' => ($this->tr)('Watches'), 'slug' => 'watches', 'path' => '', 'depth' => 0, 'is_active' => true]);
    $this->save = fn (?Product $p, array $variants) => $this->postJson($p ? "/admin/products/{$p->id}" : '/admin/products', ($p ? ['_method' => 'PUT'] : []) + [
        'type' => 'variable', 'name' => ($this->tr)('Watch'), 'sku' => 'W', 'category_ids' => [$cat->id], 'price' => '100', 'is_active' => 1, 'variants' => $variants])->assertOk();
});

it('deletes an attribute once the product variants using it were replaced by another attribute', function () {
    $old = ($this->attr)('bracelet', ['Steel', 'Leather']);
    ($this->save)(null, ['n0' => ['values' => [$old->values[0]->id], 'sku' => 'W-STEEL', 'is_active' => 1]]);
    $p = Product::query()->first();

    $new = ($this->attr)('dial', ['Black', 'Silver']);
    ($this->save)($p, ['n1' => ['values' => [$new->values[0]->id], 'sku' => 'W-BLACK', 'is_active' => 1]]); // replaces the old variant

    $this->deleteJson("/admin/attributes/{$old->id}")->assertOk();

    expect(Attribute::find($old->id))->toBeNull()
        ->and(Attribute::withTrashed()->find($old->id))->not->toBeNull()                                   // soft-deleted
        ->and(AttributeValue::withTrashed()->where('attribute_id', $old->id)->whereNotNull('deleted_at')->count())->toBe(2)
        ->and(ProductVariant::withTrashed()->where('sku', 'W-STEEL')->first()->label())->toBe('Steel')    // history keeps the real label
        ->and($p->variants()->first()->label())->toBe('Black')
        ->and($this->get('/admin/products/create')->viewData('attributes')->pluck('id'))->not->toContain($old->id);

    // the code is freed: a new attribute can reuse it
    ($this->attr)('bracelet', ['Mesh']);
    expect(Attribute::query()->where('code', 'bracelet')->count())->toBe(1);
});

it('purges a deleted attribute only once no variant (even a removed one) uses its values', function () {
    $a = ($this->attr)('bracelet', ['Steel', 'Leather']);
    ($this->save)(null, ['n0' => ['values' => [$a->values[0]->id], 'sku' => 'W-STEEL', 'is_active' => 1]]);
    $p = Product::query()->first();
    $b = ($this->attr)('dial', ['Black']);
    ($this->save)($p, ['n1' => ['values' => [$b->values[0]->id], 'sku' => 'W-BLACK', 'is_active' => 1]]);
    $this->deleteJson("/admin/attributes/{$a->id}")->assertOk();

    $this->travel(61)->days();
    app(SoftDeletePurger::class)->run(60, false, ['attribute_value', 'attribute']);
    expect(AttributeValue::withTrashed()->where('attribute_id', $a->id)->count())->toBe(1) // Steel: still the removed variant's label; Leather purged
        ->and(Attribute::withTrashed()->find($a->id))->not->toBeNull();

    ProductVariant::withTrashed()->where('sku', 'W-STEEL')->first()->forceDelete();
    app(SoftDeletePurger::class)->run(60, false, ['attribute_value', 'attribute']);
    expect(Attribute::withTrashed()->find($a->id))->toBeNull();
});

it('still refuses to delete an attribute a live variant uses', function () {
    $a = ($this->attr)('bracelet', ['Steel', 'Leather']);
    ($this->save)(null, ['n0' => ['values' => [$a->values[0]->id], 'sku' => 'W-STEEL', 'is_active' => 1]]);

    $this->deleteJson("/admin/attributes/{$a->id}")->assertStatus(422)->assertJsonValidationErrors('attribute');
    expect(Attribute::find($a->id))->not->toBeNull();
});
