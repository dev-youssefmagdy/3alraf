<?php

// Library image -> real catalog image (gallery / main / one variant), done by a person with ai_media.approve. The API / MCP can never do this.

use App\Models\Product;
use App\Models\SocialMediaAsset;
use App\Services\BarcodeService;
use Database\Seeders\FoundationSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(FoundationSeeder::class);
    Cache::flush();
    Storage::fake(config('media.private_disk'));
    Storage::fake(config('media.disk'));
    config(['app.url' => 'http://3alraf.test', 'app.host' => '3alraf.test']);
    $this->owner = marketerOwner();
    $this->product = listProduct(Product::create(['type' => 'simple', 'name' => ['en' => 'Use item', 'ar' => 'صنف'], 'description' => ['en' => 'x'], 'slug' => 'use-item', 'sku' => 'USE-1',
        'barcode' => app(BarcodeService::class)->generateUnique()]), ['price' => '100', 'cost' => '60', 'is_active' => true]);
    $this->variant = $this->product->variants()->create(['combination_hash' => 'use-1', 'sku' => 'USE-1-A', 'barcode' => app(BarcodeService::class)->generateUnique(), 'is_active' => true, 'price' => 100, 'cost' => 60]);
    $this->other = Product::create(['type' => 'simple', 'name' => ['en' => 'Other', 'ar' => 'آخر'], 'description' => ['en' => 'x'], 'slug' => 'other-item', 'sku' => 'OTH-1', 'barcode' => app(BarcodeService::class)->generateUnique()]);
    $this->otherVariant = $this->other->variants()->create(['combination_hash' => 'oth-1', 'sku' => 'OTH-1-A', 'barcode' => app(BarcodeService::class)->generateUnique(), 'is_active' => true]);
    $this->asset = function (string $name = 'a.jpg') {
        $this->actingAs($this->owner, 'marketer');

        return $this->post('/marketer/media', ['product_id' => $this->product->id, 'title' => 'AI hero', 'file' => UploadedFile::fake()->image($name, 900, 900)], ['Accept' => 'application/json'])->assertOk()->json('data.id');
    };
    $this->use = fn (int $id, array $body) => $this->postJson("/marketer/media/$id/use-on-product", $body);
});

it('adds an item to the product gallery, approving it in the same step, and records it', function () {
    $id = ($this->asset)();
    expect(SocialMediaAsset::find($id)->status->value)->toBe('pending_review')->and($this->product->images()->count())->toBe(0);
    ($this->use)($id, ['target' => 'gallery'])->assertOk();
    $a = SocialMediaAsset::find($id);
    expect($a->status->value)->toBe('approved')->and($this->product->images()->count())->toBe(1)->and($a->generation_meta['used_on'][0]['target'])->toBe('gallery');
});

it('makes it the main image: first in the gallery, existing images move after it', function () {
    $first = ($this->asset)();
    ($this->use)($first, ['target' => 'gallery'])->assertOk();
    $oldId = $this->product->images()->first()->id;
    $second = ($this->asset)('b.jpg');
    ($this->use)($second, ['target' => 'main'])->assertOk();
    $ids = $this->product->fresh()->images()->pluck('id')->all();
    expect($ids)->toHaveCount(2)->and($ids[1])->toBe($oldId)->and($ids[0])->not->toBe($oldId);
});

it('replaces the image of one variant only', function () {
    $a = ($this->asset)(); $b = ($this->asset)('b.jpg');
    ($this->use)($a, ['target' => 'variant', 'variant_id' => $this->variant->id])->assertOk();
    $first = $this->variant->images()->first()->id;
    ($this->use)($b, ['target' => 'variant', 'variant_id' => $this->variant->id])->assertOk();
    $imgs = $this->variant->fresh()->images()->get();
    expect($imgs)->toHaveCount(1)->and($imgs->first()->id)->not->toBe($first)->and($this->product->images()->count())->toBe(0);
});

it('refuses a missing / foreign variant, a bad target, videos and archived items', function () {
    $id = ($this->asset)();
    ($this->use)($id, ['target' => 'variant'])->assertStatus(422);
    ($this->use)($id, ['target' => 'variant', 'variant_id' => $this->otherVariant->id])->assertStatus(422);
    ($this->use)($id, ['target' => 'nope'])->assertStatus(422);
    $this->postJson("/marketer/media/$id/archive")->assertOk();
    ($this->use)($id, ['target' => 'gallery'])->assertStatus(422);
    expect($this->product->images()->count())->toBe(0)->and($this->other->images()->count())->toBe(0);
});

it('needs ai_media.approve', function () {
    $id = ($this->asset)();
    auth('marketer')->logout();
    $this->actingAs(makeMarketer(['ai_media.view', 'ai_media.edit', 'ai_media.upload'], null, 'e@egy.test'), 'marketer');
    ($this->use)($id, ['target' => 'gallery'])->assertForbidden();
    expect($this->product->images()->count())->toBe(0);
});

it('shows the use-on-product card only to approvers', function () {
    $id = ($this->asset)();
    $this->get("/marketer/media/$id")->assertOk()->assertSee('data-use-on-product', false);
    auth('marketer')->logout();
    $this->actingAs(makeMarketer(['ai_media.view'], null, 'v@egy.test'), 'marketer');
    $this->get("/marketer/media/$id")->assertOk()->assertDontSee('data-use-on-product', false);
});
