<?php

// Admin product form: gallery and variant images use <x-image-dropzone>, still posting the same images[] / remove_images[] / variants[..][image] fields.

use App\Models\Admin;
use App\Models\Product;
use App\Services\BarcodeService;
use App\Services\ImageUploadService;
use Database\Seeders\FoundationSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(FoundationSeeder::class);
    Cache::flush();
    Storage::fake(config('media.disk'));
    config(['app.url' => 'http://3alraf.test', 'app.host' => '3alraf.test']);
    $this->actingAs(Admin::query()->where('email', 'owner@egy.test')->first(), 'admin');
});

it('renders the gallery dropzone and an empty variant dropzone template on the create form', function () {
    $this->get('/admin/products/create')->assertOk()
        ->assertSee('data-dropzone', false)
        ->assertSee('name="images[]"', false)
        ->assertSee('data-max="12"', false)
        ->assertSee('name="variants[__INDEX__][image]"', false)
        ->assertDontSee('name="remove_images[]"', false);
});

it('shows existing gallery images as removable tiles on the edit form', function () {
    $product = Product::create(['type' => 'simple', 'name' => ['en' => 'Item', 'ar' => 'صنف'], 'description' => ['en' => 'D', 'ar' => 'و'],
        'slug' => 'dropzone-item', 'sku' => 'DZ-1', 'barcode' => app(BarcodeService::class)->generateUnique()]);
    app(ImageUploadService::class)->attach($product, [UploadedFile::fake()->image('a.jpg', 50, 50)], 'products');
    $image = $product->images()->firstOrFail();

    $this->get("/admin/products/{$product->id}/edit")->assertOk()
        ->assertSee('name="remove_images[]" value="'.$image->id.'"', false)
        ->assertSee($image->thumb_url, false);
});
