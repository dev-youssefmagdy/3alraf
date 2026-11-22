<?php

use App\Enums\StockMovementType as T;
use App\Models\Admin;
use App\Models\Branch;
use App\Models\Category;
use App\Models\FlashSale;
use App\Models\FlashSaleItem;
use App\Models\Product;
use App\Services\BarcodeService;
use App\Services\Stock\StockLedger;
use Database\Seeders\FoundationSeeder;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    Cache::flush();
    $this->seed(FoundationSeeder::class);
    $this->cat = Category::create(['name' => ['ar' => 'أجهزة', 'en' => 'Devices'], 'slug' => 'devices', 'path' => 'x', 'depth' => 0, 'is_active' => true]);
    $this->cat->update(['path' => (string) $this->cat->id]);
    $this->product = Product::create(['type' => 'simple', 'name' => ['ar' => 'منتج', 'en' => 'Product'], 'slug' => 'p1', 'sku' => 'P1', 'barcode' => app(BarcodeService::class)->generateUnique()]);
    $this->product->categories()->attach($this->cat->id);
    listProduct($this->product, ['price' => 100, 'cost' => 60, 'is_active' => true]);
    app(StockLedger::class)->record(Branch::first()->id, $this->product->id, 0, T::PURCHASE_IN, 10, '60.00');
});

it('returns a rail per root category with its products on the API home', function () {
    $this->getJson('/api/v1/home', ['Accept-Language' => 'en'])
        ->assertOk()
        ->assertJsonCount(1, 'data.category_rails')
        ->assertJsonPath('data.category_rails.0.id', $this->cat->id)
        ->assertJsonPath('data.category_rails.0.name', 'Devices')
        ->assertJsonPath('data.category_rails.0.slug', 'devices')
        ->assertJsonPath('data.category_rails.0.products.0.id', $this->product->id);
});

it('falls back to the localized flash sale label when the title is empty', function (string $locale, string $label) {
    $sale = FlashSale::create(['name' => [], 'starts_at' => now()->subHour(), 'ends_at' => now()->addDay(), 'is_active' => true]);
    FlashSaleItem::create(['flash_sale_id' => $sale->id, 'product_id' => $this->product->id, 'product_variant_id' => 0, 'discount_type' => 'percent', 'value' => 10]);

    $this->getJson('/api/v1/home', ['Accept-Language' => $locale])
        ->assertOk()
        ->assertJsonPath('data.flash_sale.name', $label);
})->with([
    'arabic' => ['ar', 'العروض الخاطفة'],
    'english' => ['en', 'Flash sale'],
]);

it('keeps the admin title of a flash sale when one is set', function () {
    $sale = FlashSale::create(['name' => ['ar' => 'عرض الجمعة', 'en' => 'Friday deal'], 'starts_at' => now()->subHour(), 'ends_at' => now()->addDay(), 'is_active' => true]);
    FlashSaleItem::create(['flash_sale_id' => $sale->id, 'product_id' => $this->product->id, 'product_variant_id' => 0, 'discount_type' => 'percent', 'value' => 10]);

    $this->getJson('/api/v1/home', ['Accept-Language' => 'en'])->assertJsonPath('data.flash_sale.name', 'Friday deal');
});

it('lets an admin save a flash sale without a title', function () {
    $this->actingAs(Admin::query()->where('email', 'owner@egy.test')->first(), 'admin');

    $this->postJson('/admin/flash-sales', ['name' => ['ar' => '', 'en' => ''], 'starts_at' => now()->format('Y-m-d\TH:i'), 'ends_at' => now()->addDay()->format('Y-m-d\TH:i'),
        'is_active' => 1, 'items' => [['item' => $this->product->id.'-0', 'discount_type' => 'percent', 'value' => 10]]])
        ->assertOk()
        ->assertJsonMissingValidationErrors(['name', 'name.ar']);

    $sale = FlashSale::sole();
    expect($sale->getTranslations('name'))->toBe([])
        ->and($sale->displayName())->toBe(__('store.flash_sale'));
});
