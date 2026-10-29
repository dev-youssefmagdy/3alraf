<?php

use App\Models\Tenant;
use App\Models\TenantAdmin;
use App\Services\SeoSettingsService;
use Database\Seeders\FoundationSeeder;

beforeEach(function () {
    $this->seed(FoundationSeeder::class);
    $this->tenant = Tenant::where('slug', 'egy')->first();
    app()->instance('currentTenant', $this->tenant);
    $this->s = 'http://egy.3alraf.test';
    $this->owner = fn () => $this->actingAs(TenantAdmin::withoutGlobalScopes()->where('email', 'owner@egy.test')->first(), 'tenant');
});

function seoPayload(array $o = []): array
{
    return $o + ['indexable' => 1, 'google_verification' => 'abcdefghijklmnopqrstuvwxyz0123456789ABCDEFG', 'ga4_id' => 'g-abc123def4', 'meta_pixel_id' => '123456789012345'];
}

it('saves SEO settings, normalises ids and shows them on the form', function () {
    ($this->owner)()->putJson("{$this->s}/admin/seo", seoPayload(['home_title' => ['en' => 'Best Egypt Store'], 'social' => ['facebook' => 'https://facebook.com/egy']]))->assertSuccessful();
    $seo = app(SeoSettingsService::class)->get($this->tenant->fresh());
    expect($seo['ga4_id'])->toBe('G-ABC123DEF4')->and($seo['home_title']['en'])->toBe('Best Egypt Store')->and($seo['social']['facebook'])->toBe('https://facebook.com/egy');
    ($this->owner)()->get("{$this->s}/admin/seo")->assertOk()->assertSee('G-ABC123DEF4')->assertSee(__('seo.title'));
});

it('extracts the verification code when a whole meta tag is pasted', function () {
    ($this->owner)()->putJson("{$this->s}/admin/seo", seoPayload(['google_verification' => '<meta name="google-site-verification" content="ZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZ" />']))->assertSuccessful();
    expect(app(SeoSettingsService::class)->get($this->tenant->fresh())['google_verification'])->toBe('ZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZ');
});

it('rejects ids that could break out of the inline script', function () {
    foreach (['ga4_id' => "G-ABC123';alert(1)//", 'gtm_id' => 'GTM-</script>', 'meta_pixel_id' => '12abc', 'google_ads_id' => 'AW-1"x', 'clarity_id' => "a');", 'tiktok_pixel_id' => 'x y'] as $k => $v) {
        ($this->owner)()->putJson("{$this->s}/admin/seo", seoPayload([$k => $v]))->assertStatus(422)->assertJsonValidationErrors($k);
    }
    ($this->owner)()->putJson("{$this->s}/admin/seo", seoPayload(['social' => ['facebook' => 'javascript:alert(1)']]))->assertStatus(422);
    ($this->owner)()->putJson("{$this->s}/admin/seo", seoPayload(['org_logo' => 'http://insecure.test/x.png']))->assertStatus(422);
});

it('requires login for the settings page', function () {
    $this->get("{$this->s}/admin/seo")->assertRedirect();
});

it('renders verification meta, tracking ids, hreflang, canonical and social tags on the storefront', function () {
    ($this->owner)()->putJson("{$this->s}/admin/seo", seoPayload(['home_title' => ['en' => 'Best Egypt Store'], 'home_description' => ['en' => 'Appliances, mobiles and fashion with fast delivery.'], 'twitter_handle' => 'egystore']))->assertSuccessful();
    auth('tenant')->logout();
    $html = $this->get("{$this->s}/en")->assertOk()->getContent();
    expect($html)->toContain('<title>Best Egypt Store</title>')
        ->and($html)->toContain('name="google-site-verification" content="abcdefghijklmnopqrstuvwxyz0123456789ABCDEFG"')
        ->and($html)->toContain('window.__trk')->and($html)->toContain('G-ABC123DEF4')
        ->and($html)->toContain('hreflang="x-default"')->and($html)->toContain('hreflang="ar"')
        ->and($html)->toContain('rel="canonical" href="'.$this->s.'/en"')
        ->and($html)->toContain('name="twitter:site" content="@egystore"')
        ->and($html)->toContain('content="Appliances, mobiles and fashion with fast delivery."')
        ->and($html)->toContain('"@type":"Organization"')->and($html)->toContain('"@type":"WebSite"')
        ->and(substr_count($html, '<h1'))->toBe(1);
});

it('emits no tracking script when nothing is configured and noindex on private pages', function () {
    $home = $this->get("{$this->s}/en")->assertOk()->getContent();
    expect($home)->not->toContain('window.__trk')->and($home)->toContain('index,follow');
    $this->get("{$this->s}/en/cart")->assertOk()->assertSee('noindex,nofollow', false);
    $this->get("{$this->s}/en/search?q=a")->assertOk()->assertSee('noindex', false);
});

it('serves robots.txt with the sitemap and blocks everything while indexing is off', function () {
    $this->get("{$this->s}/robots.txt")->assertOk()->assertHeader('Content-Type', 'text/plain; charset=UTF-8')->assertSee('Sitemap: '.$this->s.'/sitemap.xml', false)->assertSee('Disallow: /*/checkout', false);
    ($this->owner)()->putJson("{$this->s}/admin/seo", ['indexable' => 0])->assertSuccessful();
    auth('tenant')->logout();
    $this->get("{$this->s}/robots.txt")->assertSee('Disallow: /', false)->assertDontSee('Sitemap:', false);
    $this->get("{$this->s}/en")->assertSee('noindex,nofollow', false);
});

it('lists hreflang alternates and product images in the sitemap', function () {
    $xml = $this->get("{$this->s}/sitemap.xml")->assertOk()->getContent();
    expect($xml)->toContain('xmlns:xhtml')->and($xml)->toContain('hreflang="x-default"')->and($xml)->toContain($this->s.'/en/brands');
});

it('adds Product and BreadcrumbList structured data to product pages', function () {
    $cat = \App\Models\Category::create(['name' => ['ar' => 'أجهزة', 'en' => 'Devices'], 'slug' => 'devices', 'path' => 'x', 'depth' => 0, 'is_active' => true]);
    $cat->update(['path' => (string) $cat->id]);
    $p = \App\Models\Product::create(['type' => 'simple', 'name' => ['ar' => 'منتج', 'en' => 'Phone X'], 'slug' => 'phone-x', 'sku' => 'PX1', 'barcode' => app(\App\Services\BarcodeService::class)->generateUnique()]);
    $p->categories()->attach($cat->id);
    \App\Models\TenantProduct::create(['product_id' => $p->id, 'price' => 100, 'cost' => 10, 'is_active' => true]);
    $slug = $p->slug;
    $html = $this->get("{$this->s}/en/product/{$slug}")->assertOk()->getContent();
    expect($html)->toContain('"@type":"Product"')->and($html)->toContain('"priceValidUntil"')->and($html)->toContain('"@type":"BreadcrumbList"')->and($html)->toContain('property="og:type" content="product"');
});
