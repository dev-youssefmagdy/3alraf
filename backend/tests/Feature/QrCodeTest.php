<?php

use App\Enums\AffiliateStatus;
use App\Models\Affiliate;
use App\Models\Attribute;
use App\Models\Product;
use App\Models\QrCode;
use App\Models\Tenant;
use App\Models\TenantAdmin;
use App\Models\TenantProduct;
use App\Models\TenantProductVariant;
use App\Services\Affiliates\AffiliateService;
use App\Services\Qr\QrCodeManager;
use App\Services\QrCodeService;
use App\Services\TenantCatalogService;
use App\Services\Tenancy\TenantContext;
use Database\Seeders\FoundationSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(FoundationSeeder::class);
    $this->tenant = Tenant::where('slug', 'egy')->first();
    app()->instance('currentTenant', $this->tenant);
    $this->media = Storage::disk('media');
    $this->aff = Affiliate::where('email', 'aff@egy.test')->first();
    Affiliate::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('id', '!=', $this->aff->id)->update(['status' => 'pending']);
    $this->aff->update(['status' => AffiliateStatus::APPROVED]);
    $this->t = 'http://egy.3alraf.test/admin';
    $this->s = 'http://egy.3alraf.test/ar';

    $this->simple = Product::create(['type' => 'simple', 'name' => ['ar' => 'بسيط'], 'slug' => 'qr-simple', 'sku' => 'QS', 'barcode' => '2000000000022']);
    $this->var = Product::create(['type' => 'variable', 'name' => ['ar' => 'متغير'], 'slug' => 'qr-var', 'sku' => 'QV']);
    $attr = Attribute::create(['name' => ['ar' => 'q'], 'code' => 'qcolor', 'type' => 'select']);
    $vals = collect([1, 2])->map(fn ($i) => $attr->values()->create(['value' => ['ar' => "v$i"], 'sort_order' => $i]));
    $this->variants = $vals->map(function ($v, $i) {
        $pv = $this->var->variants()->create(['combination_hash' => (string) $v->id, 'sku' => 'QV'.$i, 'barcode' => '20000000001'.$i.'0', 'is_active' => true]);
        $pv->values()->sync([$v->id]);

        return $pv;
    });
    TenantProduct::query()->whereIn('product_id', [$this->simple->id, $this->var->id])->delete();
    $this->enable = fn (Product $p) => TenantProduct::create(['product_id' => $p->id, 'price' => 100, 'cost' => 50, 'is_active' => true]);
    $this->otherTenant = fn () => Tenant::create(['slug' => 'ksa', 'name' => 'KSA', 'country' => 'SA', 'currency_code' => 'SAR', 'currency_symbol' => 'SAR', 'currency_rate' => 1, 'default_locale' => 'ar', 'locales' => ['ar'], 'timezone' => 'Asia/Riyadh', 'is_active' => true]);
    $this->url = fn (string $path) => 'http://egy.3alraf.test'.$path;
});

it('builds a high-correction PNG with the centred logo that is a valid, larger-than-plain image', function () {
    $png = app(QrCodeService::class)->png('http://egy.3alraf.test/ar/product/x');
    $img = imagecreatefromstring($png);
    expect(substr($png, 0, 8))->toBe("\x89PNG\r\n\x1a\n")->and(imagesx($img))->toBe(600)->and(imagesy($img))->toBe(600);
    // centre is the (red) brand mark, not QR black/white
    $c = imagecolorsforindex($img, imagecolorat($img, 300, 300));
    $plain = (function () { config(['qr.logo_path' => '/nope']); $p = app(QrCodeService::class)->png('http://egy.3alraf.test/ar/product/x'); config(['qr.logo_path' => resource_path('branding/3alraf-logo.png')]); return $p; })();
    expect(strlen($png))->not->toBe(strlen($plain))->and($c['red'] > 150 || $c['blue'] > 150 || $c['green'] > 150)->toBeTrue();
    // a missing logo degrades to the plain code instead of failing
    expect(imagesx(imagecreatefromstring($plain)))->toBe(600);
});

it('creates product, variant and affiliate QR files on enable, with the documented URLs', function () {
    $svc = app(TenantCatalogService::class);
    $tpS = ($this->enable)($this->simple);
    $tpV = ($this->enable)($this->var);
    app(QrCodeManager::class)->syncProduct($tpV, true);
    app(QrCodeManager::class)->syncProduct($tpS, true);

    $urls = QrCode::query()->pluck('url')->all();
    $a = $this->aff->uuid;
    expect($urls)->toContain(($this->url)('/ar/product/qr-simple'))
        ->toContain(($this->url)("/ar/product/qr-simple?utm_source=affiliate&aff=$a"))
        ->toContain(($this->url)('/ar/product/qr-var'))
        ->toContain(($this->url)('/ar/product/qr-var?variant='.$this->variants[0]->id))
        ->toContain(($this->url)('/ar/product/qr-var?variant='.$this->variants[1]->id.'&utm_source=affiliate&aff='.$a))
        ->toContain(($this->url)("/ar/product/qr-var?utm_source=affiliate&aff=$a"));
    // 1 simple + (1 product + 2 variants) = 4 plain; one approved affiliate doubles it
    expect(QrCode::count())->toBe(8);
    QrCode::all()->each(fn ($q) => $this->media->assertExists($q->path));
    expect(QrCode::first()->path)->toStartWith("tenants/{$this->tenant->id}/qr/products/")->and(QrCode::first()->path)->toEndWith('.png');
    unset($svc);
});

it('is idempotent and replaces + deletes the old file when the url changes', function () {
    $tp = ($this->enable)($this->simple);
    $m = app(QrCodeManager::class);
    $m->syncProduct($tp, true);
    $before = QrCode::orderBy('id')->get();
    $m->syncProduct($tp, true);
    expect(QrCode::count())->toBe($before->count())->and(QrCode::orderBy('id')->pluck('path')->all())->toBe($before->pluck('path')->all());

    $plain = $before->firstWhere('affiliate_id', null);
    $this->simple->update(['slug' => 'qr-renamed']);
    $m->syncProduct($tp->fresh(), true);
    $after = QrCode::find($plain->id);
    expect($after->url)->toBe(($this->url)('/ar/product/qr-renamed'))->and($after->path)->not->toBe($plain->path);
    $this->media->assertMissing($plain->path);
    $this->media->assertExists($after->path);
    expect(QrCode::count())->toBe($before->count());
});

it('keeps no orphan files: disabling a listing, a variant or an affiliate removes the files', function () {
    $tp = ($this->enable)($this->var);
    $m = app(QrCodeManager::class);
    $m->syncProduct($tp, true);
    $all = QrCode::pluck('path')->all();
    expect($all)->toHaveCount(6); // product + 2 variants, x (none + affiliate)

    // variant switched off -> its plain + affiliate codes disappear
    $tv = TenantProductVariant::where('tenant_product_id', $tp->id)->first();
    $tvPaths = QrCode::where('owner_type', TenantProductVariant::class)->where('owner_id', $tv->id)->pluck('path')->all();
    $tv->update(['is_active' => false]);
    $m->syncProduct($tp->fresh(), true);
    expect(QrCode::count())->toBe(4);
    foreach ($tvPaths as $p) {
        $this->media->assertMissing($p);
    }

    // affiliate suspended -> only its codes go
    $affPaths = QrCode::where('affiliate_id', $this->aff->id)->pluck('path')->all();
    app(AffiliateService::class)->setStatus($this->aff, AffiliateStatus::SUSPENDED);
    expect(QrCode::where('affiliate_id', $this->aff->id)->count())->toBe(0)->and(QrCode::count())->toBe(2);
    foreach ($affPaths as $p) {
        $this->media->assertMissing($p);
    }

    // re-approved -> regenerated
    app(AffiliateService::class)->setStatus($this->aff->fresh(), AffiliateStatus::APPROVED);
    expect(QrCode::where('affiliate_id', $this->aff->id)->count())->toBe(2);

    // listing removed -> everything goes
    $remaining = QrCode::pluck('path')->all();
    app(TenantCatalogService::class)->delete($tp->fresh());
    expect(QrCode::count())->toBe(0);
    foreach ($remaining as $p) {
        $this->media->assertMissing($p);
    }
    expect($this->media->allFiles())->toBe([]);
});

it('generates codes through the real flows: bulk enable, listing update, affiliate approval, central product edit', function () {
    $this->actingAs(TenantAdmin::withoutGlobalScopes()->where('email', 'owner@egy.test')->first(), 'tenant');
    $this->postJson("{$this->t}/catalog/bulk", ['rows' => [$this->simple->id => ['enabled' => 1, 'price' => '10', 'cost' => '5']]])->assertOk();
    $tp = TenantProduct::where('product_id', $this->simple->id)->first();
    expect(QrCode::where('owner_id', $tp->id)->whereNull('affiliate_id')->count())->toBe(1)->and(QrCode::where('affiliate_id', $this->aff->id)->count())->toBe(1);

    // deactivating the listing removes its codes
    $this->putJson("{$this->t}/catalog/{$tp->id}", ['price' => '10', 'cost' => '5', 'is_active' => 0])->assertOk();
    expect(QrCode::count())->toBe(0);
    $this->putJson("{$this->t}/catalog/{$tp->id}", ['price' => '10', 'cost' => '5', 'is_active' => 1])->assertOk();
    expect(QrCode::count())->toBe(2);

    // central slug change refreshes every tenant's codes (queued job, sync in tests)
    $old = QrCode::pluck('path')->all();
    $cat = \App\Models\Category::create(['name' => ['ar' => 'c'], 'slug' => 'c', 'path' => '1', 'depth' => 0]);
    $this->actingAs(\App\Models\CentralAdmin::first(), 'central');
    app()->forgetInstance('currentTenant');
    app()->offsetUnset('currentTenant');
    $this->putJson('http://3alraf.test/admin/products/'.$this->simple->id, ['type' => 'simple', 'name' => ['ar' => 'بسيط'], 'slug' => 'qr-slug-2', 'sku' => 'QS', 'category_ids' => [$cat->id], 'is_active' => 1])->assertOk();
    app()->instance('currentTenant', $this->tenant);
    expect(QrCode::pluck('url')->all())->each->toContain('qr-slug-2');
    foreach ($old as $p) {
        $this->media->assertMissing($p);
    }
});

it('serves the admin download, isolates tenants, and affiliates only get their own stored png', function () {
    $tp = ($this->enable)($this->var);
    app(QrCodeManager::class)->syncProduct($tp, true);
    $this->actingAs(TenantAdmin::withoutGlobalScopes()->where('email', 'owner@egy.test')->first(), 'tenant');

    $this->get("{$this->t}/catalog/{$tp->id}/edit")->assertOk()->assertSee(__('admin.qr_download'))->assertSee(media_url(QrCode::whereNull('affiliate_id')->first()->path), false);
    $r = $this->get("{$this->t}/catalog/{$tp->id}/qr")->assertOk()->assertHeader('Content-Type', 'image/png');
    expect($r->headers->get('Content-Disposition'))->toContain('qr-qr-var.png')->and(substr($r->getContent(), 1, 3))->toBe('PNG');
    $tv = TenantProductVariant::where('tenant_product_id', $tp->id)->first();
    $this->get("{$this->t}/catalog/{$tp->id}/qr?variant={$tv->id}")->assertOk()->assertHeader('Content-Type', 'image/png');
    $this->get("{$this->t}/catalog/{$tp->id}/qr?variant=999999")->assertNotFound();

    // missing file is regenerated on demand (self healing)
    $q = QrCode::whereNull('affiliate_id')->where('owner_type', TenantProduct::class)->first();
    $this->media->delete($q->path);
    $this->get("{$this->t}/catalog/{$tp->id}/qr")->assertOk();
    $this->media->assertExists($q->fresh()->path);

    // another tenant's admin cannot reach it
    $other = ($this->otherTenant)();
    $this->get("http://{$other->slug}.3alraf.test/admin/catalog/{$tp->id}/qr")->assertStatus(404);
});

it('affiliate web + api return the stored png; affiliate of another listing state gets 404', function () {
    $tp = ($this->enable)($this->var);
    app(QrCodeManager::class)->syncProduct($tp, true);
    $stored = QrCode::where('affiliate_id', $this->aff->id)->where('owner_type', TenantProduct::class)->first();

    $this->actingAs($this->aff, 'affiliate');
    $page = $this->get("{$this->s}/affiliate/products")->assertOk();
    $page->assertSee(media_url($stored->path), false);
    $r = $this->get("{$this->s}/affiliate/qr/qr-var")->assertOk()->assertHeader('Content-Type', 'image/png');
    expect($r->getContent())->toBe($this->media->get($stored->path));
    $vid = $this->variants[0]->id;
    $this->get("{$this->s}/affiliate/qr/qr-var?variant=$vid")->assertOk()->assertHeader('Content-Type', 'image/png');
    $this->get("{$this->s}/affiliate/qr/qr-var?variant=99999")->assertNotFound();
    $this->get("{$this->s}/affiliate/qr/nope")->assertNotFound();

    // api resource points at the CDN file and lists variants
    $tok = $this->postJson('http://egy.3alraf.test/api/v1/affiliate/login', ['email' => 'aff@egy.test', 'password' => 'password'])->json('data.token');
    $h = ['Authorization' => "Bearer $tok"];
    $items = collect($this->getJson('http://egy.3alraf.test/api/v1/affiliate/products', $h)->assertOk()->json('data'));
    $row = $items->firstWhere('slug', 'qr-var');
    expect($row['qr_url'])->toBe(media_url($stored->path))->and($row['variants'])->toHaveCount(2)->and($row['variants'][0]['qr_url'])->toContain('qr/products');
    $this->get('http://egy.3alraf.test/api/v1/affiliate/products/qr-var/qr', $h)->assertOk()->assertHeader('Content-Type', 'image/png');

    // suspended affiliate: no code
    $this->aff->update(['status' => AffiliateStatus::SUSPENDED]);
    app(QrCodeManager::class)->syncAffiliate($this->aff->fresh());
    expect(QrCode::where('affiliate_id', $this->aff->id)->count())->toBe(0);
});

it('qr:rebuild backfills, repairs missing files, is idempotent and honours --tenant/--force', function () {
    ($this->enable)($this->simple);
    ($this->enable)($this->var);
    expect(QrCode::count())->toBe(0);
    $this->artisan('qr:rebuild', ['--tenant' => 'egy'])->assertSuccessful();
    $n = QrCode::count();
    expect($n)->toBe(8);

    $paths = QrCode::orderBy('id')->pluck('path')->all();
    $this->artisan('qr:rebuild')->assertSuccessful();
    expect(QrCode::orderBy('id')->pluck('path')->all())->toBe($paths); // nothing regenerated

    $this->media->delete($paths[0]);
    $this->artisan('qr:rebuild')->assertSuccessful();
    $this->media->assertExists($paths[0]);

    $this->artisan('qr:rebuild', ['--tenant' => 'nope'])->assertFailed();
    $this->artisan('qr:rebuild', ['--tenant' => 'egy', '--force' => true])->assertSuccessful();
    expect(QrCode::count())->toBe($n);
});

it('keeps tenants isolated: codes of another tenant are invisible and its context cannot sync them', function () {
    $tp = ($this->enable)($this->simple);
    app(QrCodeManager::class)->syncProduct($tp);
    $other = ($this->otherTenant)();
    $seen = TenantContext::run($other, fn () => QrCode::count());
    expect($seen)->toBe(0)->and(QrCode::count())->toBe(1)->and(current_tenant()->id)->toBe($this->tenant->id);
});

it('keeps a soft-deleted catalogue product codes and the storefront preselects ?variant', function () {
    $tp = ($this->enable)($this->simple);
    app(QrCodeManager::class)->syncProduct($tp);
    $this->simple->delete();
    app(QrCodeManager::class)->syncProduct($tp->fresh());
    expect(QrCode::count())->toBe(1);
    $this->media->assertExists(QrCode::first()->path);

    $js = file_get_contents(resource_path('js/lib/storefront.js'));
    expect($js)->toContain("get('variant')");
});
