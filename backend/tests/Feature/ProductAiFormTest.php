<?php

// Product edit → AI tools card and dialog (PRODUCT_AI_FREELLMAPI_PLAN.md §7.1): rendered with its shortcuts when the gateway is
// configured; only the card with disabled buttons and the "not configured" alert when it is not. Nothing on the create page.

use App\Models\Admin;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\BarcodeService;
use Database\Seeders\FoundationSeeder;

beforeEach(function () {
    $this->seed(FoundationSeeder::class);
    $this->withoutVite();
    $this->actingAs(Admin::query()->where('email', 'owner@egy.test')->first(), 'admin');
    $this->product = Product::create(['type' => 'variable', 'name' => ['en' => 'Philips Air Fryer', 'ar' => 'قلاية فيليبس'], 'slug' => 'fryer', 'sku' => 'PH-1']);
    $attr = Attribute::create(['name' => ['en' => 'Color', 'ar' => 'اللون'], 'code' => 'color', 'type' => 'color']);
    $black = AttributeValue::create(['attribute_id' => $attr->id, 'value' => ['en' => 'Black', 'ar' => 'أسود']]);
    $this->variant = ProductVariant::create(['product_id' => $this->product->id, 'combination_hash' => (string) $black->id, 'sku' => 'PH-1-B',
        'is_active' => true, 'barcode' => app(BarcodeService::class)->generateUnique()]);
    $this->variant->values()->attach($black->id);
    $this->edit = "/admin/products/{$this->product->id}/edit";
});

it('shows the AI tools card, its shortcuts, the variant link and the dialog when the gateway is configured', function () {
    config(['product_ai.base_url' => 'http://llm.test/v1', 'product_ai.api_key' => 'freellmapi-test']);

    $html = $this->get($this->edit)->assertOk()->getContent();

    expect($html)->toContain('data-product-ai-card')->toContain(__('admin.product_ai.card_title'))
        ->toContain('id="product-ai" data-modal data-product-ai')
        ->toContain('data-tab="image" data-target-type="product"')
        ->toContain('data-tab="titles" data-target-type="product"')
        ->toContain('data-tab="market" data-target-type="product"')
        ->toContain('data-tab="market" data-target-type="variant" data-target-id="'.$this->variant->id.'"')
        ->toContain(str_replace('/', '\\/', route('admin.products.ai.prices', $this->product))) // @json escapes slashes
        ->not->toContain(__('admin.product_ai.not_configured'));
    expect(substr_count($html, 'data-product-ai-open'))->toBe(6); // 3 card buttons + General + Pricing shortcuts + 1 variant link
});

it('shows the not-configured alert with disabled buttons and no dialog when the gateway is not configured', function () {
    config(['product_ai.base_url' => '', 'product_ai.api_key' => '']);

    $html = $this->get($this->edit)->assertOk()->getContent();

    expect($html)->toContain('data-product-ai-card')->toContain(e(__('admin.product_ai.not_configured')))
        ->not->toContain('data-product-ai-open')
        ->not->toContain('data-product-ai ');
    expect(preg_match_all('/<button type="button" class="btn btn-secondary" disabled\s/', $html))->toBe(3);
});

it('has no AI tools on the create page', function () {
    config(['product_ai.base_url' => 'http://llm.test/v1', 'product_ai.api_key' => 'freellmapi-test']);

    $this->get('/admin/products/create')->assertOk()->assertDontSee('data-product-ai', false);
});
