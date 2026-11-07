<?php

use App\Enums\AffiliateStatus;
use App\Models\Affiliate;
use App\Models\Attribute;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\QrCode;
use App\Models\Admin;
use App\Services\Affiliates\AffiliateService;
use App\Services\Qr\QrCodeManager;
use App\Services\QrCodeService;
use App\Services\CatalogService;
use Database\Seeders\FoundationSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(FoundationSeeder::class);
    $this->media = Storage::disk('media');
    $this->aff = Affiliate::where('email', 'aff@egy.test')->first();
    Affiliate::query()->where('id', '!=', $this->aff->id)->update(['status' => 'pending']);
    $this->aff->update(['status' => AffiliateStatus::APPROVED]);
    $this->t = '/admin';
    $this->s = '/ar';

    $this->simple = Product::create(['type' => 'simple', 'name' => ['ar' => 'بسيط'], 'slug' => 'qr-simple', 'sku' => 'QS', 'barcode' => '2000000000022']);
    $this->var = Product::create(['type' => 'variable', 'name' => ['ar' => 'متغير'], 'slug' => 'qr-var', 'sku' => 'QV']);
    $attr = Attribute::create(['name' => ['ar' => 'q'], 'code' => 'qcolor', 'type' => 'select']);
    $vals = collect([1, 2])->map(fn ($i) => $attr->values()->create(['value' => ['ar' => "v$i"], 'sort_order' => $i]));
    $this->variants = $vals->map(function ($v, $i) {
        $pv = $this->var->variants()->create(['combination_hash' => (string) $v->id, 'sku' => 'QV'.$i, 'barcode' => '20000000001'.$i.'0', 'is_active' => true]);
        $pv->values()->sync([$v->id]);

        return $pv;
    });
    $this->enable = fn (Product $p) => listProduct($p, ['price' => 100, 'cost' => 50, 'is_active' => true]);
    $this->url = fn (string $path) => url($path);   // QR codes always point at APP_URL (never a store subdomain)
});

it('builds a high-correction PNG with the centred logo that is a valid, larger-than-plain image', function () {
    $png = app(QrCodeService::class)->png('/ar/product/x');
    $img = imagecreatefromstring($png);
    expect(substr($png, 0, 8))->toBe("\x89PNG\r\n\x1a\n")->and(imagesx($img))->toBe(600)->and(imagesy($img))->toBe(600);
    // the centre shows the configured logo (config qr.logo_path), not the plain QR modules
    $logoPath = config('qr.logo_path');
    $plain = (function () use ($logoPath) { config(['qr.logo_path' => '/nope']); $p = app(QrCodeService::class)->png('/ar/product/x'); config(['qr.logo_path' => $logoPath]); return $p; })();
    $centre = fn (string $bytes) => array_sum(array_map(fn ($xy) => imagecolorat(imagecreatefromstring($bytes), $xy[0], $xy[1]), [[300, 300], [290, 290], [310, 310], [300, 280], [280, 300]]));
    expect(strlen($png))->not->toBe(strlen($plain))->and($centre($png))->not->toBe($centre($plain));
    // a missing logo degrades to the plain code instead of failing
    expect(imagesx(imagecreatefromstring($plain)))->toBe(600);
});

it('creates product, variant and affiliate QR files on enable, with the documented URLs', function () {
    $svc = app(CatalogService::class);
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
    expect(QrCode::first()->path)->toStartWith('qr/products/')->and(QrCode::first()->path)->toEndWith('.png');
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
    $tv = $tp->variants()->first();
    $tvPaths = QrCode::where('owner_type', ProductVariant::class)->where('owner_id', $tv->id)->pluck('path')->all();
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
    app(CatalogService::class)->delete($tp->fresh());
    expect(QrCode::count())->toBe(0);
    foreach ($remaining as $p) {
        $this->media->assertMissing($p);
    }
    expect($this->media->allFiles())->toBe([]);
});

it('generates codes through the real flows: bulk enable, listing update, affiliate approval, product edit', function () {
    $this->actingAs(Admin::query()->where('email', 'owner@egy.test')->first(), 'admin');
    $this->postJson("{$this->t}/products/bulk", ['rows' => [$this->simple->id => ['enabled' => 1, 'price' => '10', 'cost' => '5']]])->assertOk();
    $tp = Product::whereKey($this->simple->id)->first();
    expect(QrCode::where('owner_id', $tp->id)->whereNull('affiliate_id')->count())->toBe(1)->and(QrCode::where('affiliate_id', $this->aff->id)->count())->toBe(1);

    // deactivating the listing removes its codes
    $this->putJson("{$this->t}/products/{$tp->id}", ['type' => 'simple', 'name' => ['ar' => 'بسيط'], 'sku' => $tp->sku, 'slug' => $tp->slug, 'category_ids' => [\App\Models\Category::firstOrCreate(['slug' => 'c'], ['name' => ['ar' => 'c'], 'path' => '1', 'depth' => 0])->id], 'price' => '10', 'cost' => '5', 'is_active' => 0])->assertOk();
    expect(QrCode::count())->toBe(0);
    $this->putJson("{$this->t}/products/{$tp->id}", ['type' => 'simple', 'name' => ['ar' => 'بسيط'], 'sku' => $tp->sku, 'slug' => $tp->slug, 'category_ids' => [\App\Models\Category::firstOrCreate(['slug' => 'c'], ['name' => ['ar' => 'c'], 'path' => '1', 'depth' => 0])->id], 'price' => '10', 'cost' => '5', 'is_active' => 1])->assertOk();
    expect(QrCode::count())->toBe(2);

    // a product slug change refreshes its codes (queued job, sync in tests)
    $old = QrCode::pluck('path')->all();
    $cat = \App\Models\Category::firstOrCreate(['slug' => 'c'], ['name' => ['ar' => 'c'], 'path' => '1', 'depth' => 0]);
    $this->actingAs(\App\Models\Admin::first(), 'admin');
    $this->putJson('/admin/products/'.$this->simple->id, ['type' => 'simple', 'name' => ['ar' => 'بسيط'], 'slug' => 'qr-slug-2', 'sku' => 'QS', 'category_ids' => [$cat->id], 'is_active' => 1, 'price' => '10', 'cost' => '5'])->assertOk();
    expect(QrCode::pluck('url')->all())->each->toContain('qr-slug-2');
    foreach ($old as $p) {
        $this->media->assertMissing($p);
    }
});

it('serves the admin download (self-healing) and affiliates only get their own stored png', function () {
    $tp = ($this->enable)($this->var);
    app(QrCodeManager::class)->syncProduct($tp, true);
    $this->actingAs(Admin::query()->where('email', 'owner@egy.test')->first(), 'admin');

    $this->get("{$this->t}/products/{$tp->id}/edit")->assertOk()->assertSee(__('admin.qr_download'))->assertSee(media_url(QrCode::whereNull('affiliate_id')->first()->path), false);
    $r = $this->get("{$this->t}/products/{$tp->id}/qr")->assertOk()->assertHeader('Content-Type', 'image/png');
    expect($r->headers->get('Content-Disposition'))->toContain('qr-qr-var.png')->and(substr($r->getContent(), 1, 3))->toBe('PNG');
    $tv = $tp->variants()->first();
    $this->get("{$this->t}/products/{$tp->id}/qr?variant={$tv->id}")->assertOk()->assertHeader('Content-Type', 'image/png');
    $this->get("{$this->t}/products/{$tp->id}/qr?variant=999999")->assertNotFound();

    // missing file is regenerated on demand (self healing)
    $q = QrCode::whereNull('affiliate_id')->where('owner_type', Product::class)->first();
    $this->media->delete($q->path);
    $this->get("{$this->t}/products/{$tp->id}/qr")->assertOk();
    $this->media->assertExists($q->fresh()->path);

    $this->get("{$this->t}/products/999999/qr")->assertNotFound();
});

it('affiliate web + api return the stored png; affiliate of another listing state gets 404', function () {
    $tp = ($this->enable)($this->var);
    app(QrCodeManager::class)->syncProduct($tp, true);
    $stored = QrCode::where('affiliate_id', $this->aff->id)->where('owner_type', Product::class)->first();

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
    $tok = $this->postJson('/api/v1/affiliate/login', ['email' => 'aff@egy.test', 'password' => 'password'])->json('data.token');
    $h = ['Authorization' => "Bearer $tok"];
    $items = collect($this->getJson('/api/v1/affiliate/products', $h)->assertOk()->json('data'));
    $row = $items->firstWhere('slug', 'qr-var');
    expect($row['qr_url'])->toBe(media_url($stored->path))->and($row['variants'])->toHaveCount(2)->and($row['variants'][0]['qr_url'])->toContain('qr/products');
    $this->get('/api/v1/affiliate/products/qr-var/qr', $h)->assertOk()->assertHeader('Content-Type', 'image/png');

    // suspended affiliate: no code
    $this->aff->update(['status' => AffiliateStatus::SUSPENDED]);
    app(QrCodeManager::class)->syncAffiliate($this->aff->fresh());
    expect(QrCode::where('affiliate_id', $this->aff->id)->count())->toBe(0);
});

it('qr:rebuild backfills, repairs missing files, is idempotent and honours --force', function () {
    ($this->enable)($this->simple);
    ($this->enable)($this->var);
    expect(QrCode::count())->toBe(0);
    $this->artisan('qr:rebuild', [])->assertSuccessful();
    $n = QrCode::count();
    expect($n)->toBe(8);

    $paths = QrCode::orderBy('id')->pluck('path')->all();
    $this->artisan('qr:rebuild')->assertSuccessful();
    expect(QrCode::orderBy('id')->pluck('path')->all())->toBe($paths); // nothing regenerated

    $this->media->delete($paths[0]);
    $this->artisan('qr:rebuild')->assertSuccessful();
    $this->media->assertExists($paths[0]);

    $this->artisan('qr:rebuild', ['--force' => true])->assertSuccessful();
    expect(QrCode::count())->toBe($n);
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
