<?php

use App\Models\Attribute;
use App\Models\Brand;
use App\Models\Category;
use App\Models\CentralAdmin;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\TenantAdmin;
use App\Models\TenantProduct;
use App\Services\BarcodeService;
use Database\Seeders\FoundationSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(FoundationSeeder::class);
    Storage::fake('public');
    $this->c = 'http://3alraf.test/admin';
    $this->t = 'http://egy.3alraf.test/admin';
    $this->actingAs(CentralAdmin::first(), 'central');
});

function mkAttr(string $code, array $vals): Attribute
{
    $a = Attribute::create(['name' => ['ar' => $code, 'en' => $code], 'code' => $code, 'type' => 'select']);
    foreach ($vals as $i => $v) {
        $a->values()->create(['value' => ['ar' => $v, 'en' => $v], 'sort_order' => $i]);
    }

    return $a->load('values');
}

it('validates and creates brands, attributes and a category tree', function () {
    $this->postJson("{$this->c}/brands/validate", [])->assertStatus(422)->assertJsonValidationErrors(['name.ar']);
    $this->post("{$this->c}/brands", ['name' => ['ar' => 'سوني', 'en' => 'Sony'], 'is_active' => 1, 'logo' => UploadedFile::fake()->image('l.png', 50, 50)], ['Accept' => 'application/json'])->assertOk();
    $brand = Brand::first();
    expect($brand->slug)->toBe('sony');
    Storage::disk('public')->assertExists($brand->logo);

    $this->postJson("{$this->c}/attributes", [
        'name' => ['ar' => 'اللون', 'en' => 'Color'], 'code' => 'color', 'type' => 'color',
        'values' => [['value' => ['ar' => 'أحمر', 'en' => 'Red'], 'color_hex' => '#ff0000'], ['value' => ['ar' => 'أزرق'], 'color_hex' => '#0000ff']],
    ])->assertOk();
    expect(Attribute::first()->values)->toHaveCount(2);
    $this->postJson("{$this->c}/attributes/validate", ['name' => ['ar' => 'x'], 'code' => 'x', 'type' => 'color', 'values' => [['value' => ['ar' => 'a']]]])->assertJsonValidationErrors(['values.0.color_hex']);

    $this->postJson("{$this->c}/categories", ['name' => ['ar' => 'إلكترونيات', 'en' => 'Electronics'], 'is_active' => 1])->assertOk();
    $root = Category::first();
    $this->postJson("{$this->c}/categories", ['name' => ['ar' => 'هواتف'], 'parent_id' => $root->id])->assertOk();
    $child = Category::where('parent_id', $root->id)->first();
    $this->postJson("{$this->c}/categories", ['name' => ['ar' => 'ذكية'], 'parent_id' => $child->id])->assertOk();
    $leaf = Category::where('parent_id', $child->id)->first();
    expect($leaf->path)->toBe("{$root->id}/{$child->id}/{$leaf->id}")->and($leaf->depth)->toBe(2);

    // cycle guard + move subtree
    $this->putJson("{$this->c}/categories/{$root->id}", ['name' => ['ar' => 'إلكترونيات'], 'parent_id' => $leaf->id])->assertStatus(422);
    $other = Category::create(['name' => ['ar' => 'أخرى'], 'slug' => 'other', 'path' => '', 'depth' => 0]);
    $other->update(['path' => (string) $other->id]);
    $this->putJson("{$this->c}/categories/{$child->id}", ['name' => ['ar' => 'هواتف'], 'parent_id' => $other->id])->assertOk();
    expect($leaf->fresh()->path)->toBe("{$other->id}/{$child->id}/{$leaf->id}");
    $this->deleteJson("{$this->c}/categories/{$other->id}")->assertStatus(422);

    foreach (['brands', 'attributes', 'categories', 'products'] as $m) {
        $this->get("{$this->c}/$m")->assertOk();
        $this->get("{$this->c}/$m/create")->assertOk();
    }
});

it('creates a variable product with unique EAN-13 variant barcodes and syncs variants', function () {
    $color = mkAttr('color', ['Red', 'Blue']);
    $size = mkAttr('size', ['S', 'M']);
    $cat = Category::create(['name' => ['ar' => 'c'], 'slug' => 'c', 'path' => '1', 'depth' => 0]);
    $combos = [[$color->values[0]->id, $size->values[0]->id], [$color->values[0]->id, $size->values[1]->id], [$color->values[1]->id, $size->values[0]->id]];
    $variants = [];
    foreach ($combos as $i => $ids) {
        $variants["n$i"] = ['values' => $ids, 'sku' => "TS-$i", 'is_active' => 1];
    }
    $payload = ['type' => 'variable', 'name' => ['ar' => 'قميص', 'en' => 'Shirt'], 'sku' => 'TS', 'category_ids' => [$cat->id], 'is_active' => 1, 'variants' => $variants];

    $this->postJson("{$this->c}/products", $payload)->assertOk();
    $p = Product::with('variants')->first();
    expect($p->barcode)->toBeNull()->and($p->variants)->toHaveCount(3);
    $codes = $p->variants->pluck('barcode');
    expect($codes->unique())->toHaveCount(3);
    $codes->each(fn ($c) => expect(BarcodeService::isValidEan13($c))->toBeTrue());
    $this->get("{$this->c}/barcode/{$codes[0]}")->assertOk()->assertHeader('Content-Type', 'image/svg+xml');
    $this->get("{$this->c}/barcode/{$codes[0]}?format=png")->assertOk()->assertHeader('Content-Type', 'image/png');

    // duplicate combination + duplicate sku rejected
    $dup = $payload;
    $dup['sku'] = 'TS2';
    $dup['variants'] = ['a' => ['values' => $combos[0], 'sku' => 'X'], 'b' => ['values' => array_reverse($combos[0]), 'sku' => 'X']];
    $this->postJson("{$this->c}/products/validate", $dup)->assertJsonValidationErrors(['variants.b.values', 'variants.b.sku']);

    // edit: keep one (with id), drop two, add new -> barcodes of kept stay stable
    $keep = $p->variants[0];
    $upd = $payload;
    $upd['variants'] = [
        0 => ['id' => $keep->id, 'values' => $combos[0], 'sku' => 'TS-KEEP', 'is_active' => 1],
        1 => ['values' => [$color->values[1]->id, $size->values[1]->id], 'sku' => 'TS-NEW', 'is_active' => 1],
    ];
    $this->putJson("{$this->c}/products/{$p->id}", $upd)->assertOk();
    $p->refresh();
    expect($p->variants)->toHaveCount(2)->and($keep->fresh()->barcode)->toBe($keep->barcode)->and($keep->fresh()->sku)->toBe('TS-KEEP');
    $this->get("{$this->c}/products/{$p->id}/edit")->assertOk()->assertSee('TS-KEEP');
});

it('creates a simple product with auto barcode and images, and lists datatable', function () {
    $cat = Category::create(['name' => ['ar' => 'c'], 'slug' => 'c', 'path' => '1', 'depth' => 0]);
    $res = $this->post("{$this->c}/products", [
        'type' => 'simple', 'name' => ['ar' => 'سماعة'], 'sku' => 'HP-1', 'category_ids' => [$cat->id], 'is_active' => 1,
        'images' => [UploadedFile::fake()->image('a.jpg', 800, 600), UploadedFile::fake()->image('b.jpg', 300, 300)],
    ], ['Accept' => 'application/json'])->assertOk();
    $p = Product::with('images')->first();
    expect(BarcodeService::isValidEan13($p->barcode))->toBeTrue()->and($p->images)->toHaveCount(2);
    Storage::disk('public')->assertExists($p->images[0]->path);
    Storage::disk('public')->assertExists($p->images[0]->thumb_path);
    $this->postJson("{$this->c}/products/validate", ['type' => 'simple', 'name' => ['ar' => 'x'], 'sku' => 'HP-1', 'category_ids' => [$cat->id], 'barcode' => '1234567890123'])
        ->assertJsonValidationErrors(['sku', 'barcode']);
    $this->getJson("{$this->c}/products/datatable?draw=1&start=0&length=10")->assertOk()->assertJsonPath('recordsTotal', 1);
    $this->getJson("{$this->c}/brands/datatable?draw=1")->assertOk();
});

it('lets a tenant enable central products in bulk with isolated pricing', function () {
    $cat = Category::create(['name' => ['ar' => 'c'], 'slug' => 'c', 'path' => '1', 'depth' => 0]);
    $a = Product::create(['type' => 'simple', 'name' => ['ar' => 'A'], 'slug' => 'a', 'sku' => 'A', 'barcode' => '2000000000008']);
    $b = Product::create(['type' => 'simple', 'name' => ['ar' => 'B'], 'slug' => 'b', 'sku' => 'B', 'barcode' => '2000000000015']);
    $color = mkAttr('color', ['Red', 'Blue']);
    $v = Product::create(['type' => 'variable', 'name' => ['ar' => 'V'], 'slug' => 'v', 'sku' => 'V']);
    foreach ($color->values as $val) {
        $pv = $v->variants()->create(['combination_hash' => (string) $val->id, 'sku' => 'V'.$val->id, 'barcode' => app(BarcodeService::class)->generateUnique()]);
        $pv->values()->sync([$val->id]);
    }

    auth('central')->logout();
    $this->actingAs(TenantAdmin::withoutGlobalScopes()->first(), 'tenant');

    $this->postJson("{$this->t}/catalog/bulk/validate", ['rows' => [$a->id => ['enabled' => 1, 'price' => '', 'cost' => '5']]])->assertJsonValidationErrors(["rows.{$a->id}.price"]);
    $this->postJson("{$this->t}/catalog/bulk/validate", ['rows' => [$a->id => ['price' => '10']]])->assertJsonValidationErrors(['rows']);
    $this->postJson("{$this->t}/catalog/bulk/validate", ['rows' => [$a->id => ['enabled' => 1, 'price' => '10', 'sale_price' => '12', 'cost' => '5']]])->assertJsonValidationErrors(["rows.{$a->id}.sale_price"]);

    $this->postJson("{$this->t}/catalog/bulk", ['rows' => [
        $a->id => ['enabled' => 1, 'price' => '100', 'sale_price' => '80', 'cost' => '50'],
        $v->id => ['enabled' => 1, 'price' => '200', 'cost' => '120'],
        $b->id => ['price' => '1', 'cost' => '1'], // unchecked -> ignored
    ]])->assertOk();
    expect(TenantProduct::count())->toBe(2);
    $tpA = TenantProduct::where('product_id', $a->id)->first();
    expect($tpA->tenant_id)->toBe(Tenant::where('slug', 'egy')->value('id'))->and((string) $tpA->effectivePrice())->toBe('80.00');
    $tpV = TenantProduct::where('product_id', $v->id)->first();
    expect($tpV->variants)->toHaveCount(2);

    // already-enabled products no longer offered; enabling twice is a no-op
    $this->get("{$this->t}/catalog/available")->assertOk()->assertSee('B')->assertDontSee('>A<', false);
    $this->postJson("{$this->t}/catalog/bulk", ['rows' => [$a->id => ['enabled' => 1, 'price' => '1', 'cost' => '1']]])->assertOk();
    expect(TenantProduct::count())->toBe(2);

    $this->get("{$this->t}/catalog/{$tpV->id}/edit")->assertOk();
    $tv = $tpV->variants[0];
    $this->putJson("{$this->t}/catalog/{$tpV->id}", ['price' => '210', 'cost' => '125', 'is_active' => 1, 'variants' => [['id' => $tv->id, 'price' => '250', 'sale_price' => '240', 'is_active' => 0]]])->assertOk();
    expect((string) $tv->fresh()->price)->toBe('250.00')->and($tv->fresh()->is_active)->toBeFalse();
    $this->putJson("{$this->t}/catalog/{$tpV->id}", ['price' => '210', 'cost' => '1', 'variants' => [['id' => 999999]]])->assertStatus(422);

    $this->getJson("{$this->t}/catalog/datatable?draw=1&start=0&length=10")->assertOk()->assertJsonPath('recordsTotal', 2);
    $this->get("{$this->t}/catalog")->assertOk();

    // isolation: another tenant sees none of egy's listings and cannot edit them
    $sa = Tenant::create(['slug' => 'sa', 'name' => 'SA', 'country' => 'SA', 'currency_code' => 'SAR', 'currency_symbol' => 'SAR', 'currency_rate' => 1, 'default_locale' => 'ar', 'locales' => ['ar'], 'timezone' => 'Asia/Riyadh', 'is_active' => true]);
    $owner = TenantAdmin::withoutGlobalScopes()->create(['tenant_id' => $sa->id, 'name' => 'o', 'email' => 'o@sa.test', 'password' => 'password', 'is_active' => true]);
    $owner->syncRoles('tenant-owner');
    $this->actingAs($owner, 'tenant');
    $this->getJson('http://sa.3alraf.test/admin/catalog/datatable?draw=1&start=0&length=10')->assertOk()->assertJsonPath('recordsTotal', 0);
    $this->getJson("http://sa.3alraf.test/admin/catalog/{$tpA->id}/edit")->assertNotFound();
});
