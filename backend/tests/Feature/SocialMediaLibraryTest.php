<?php

// P6 (MARKETER_PLAN.md, MCP.md §21 / §28): private product social-media library — upload validation, private storage + protected routes, permissions,
// versions, approval, exclusion from public output, the admin product tab, internal API (metadata + signed preview), social_content / media_asset apply + rollback.

use App\DTOs\Marketing\MarketingTokenData;
use App\DTOs\Marketing\SuggestionInput;
use App\Models\Admin;
use App\Models\Product;
use App\Models\SocialMediaAsset;
use App\Services\BarcodeService;
use App\Services\Marketing\MarketingTokenService;
use App\Services\Marketing\Suggestions\SuggestionActor;
use App\Services\Marketing\Suggestions\SuggestionEngine;
use Database\Seeders\FoundationSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(FoundationSeeder::class);
    Cache::flush();
    Storage::fake(config('media.private_disk'));
    Storage::fake(config('media.disk'));
    config(['app.url' => 'http://3alraf.test', 'app.host' => '3alraf.test']);
    $this->owner = Admin::query()->where('email', 'owner@egy.test')->first();
    $this->staff = function (array $perms = [], ?string $role = null, string $email = 's@egy.test') {
        $a = Admin::create(['name' => 'S '.$email, 'email' => $email, 'password' => 'password', 'is_active' => true]);
        $perms && $a->givePermissionTo($perms);
        $role && $a->assignRole($role);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $a->fresh();
    };
    $this->product = listProduct(Product::create(['type' => 'simple', 'name' => ['en' => 'Lib item', 'ar' => 'صنف'], 'description' => ['en' => 'x'], 'slug' => 'lib-item', 'sku' => 'LIB-1',
        'barcode' => app(BarcodeService::class)->generateUnique()]), ['price' => '100', 'cost' => '60', 'is_active' => true]);
    $this->upload = fn (array $extra = [], ?UploadedFile $f = null) => $this->post('/marketer/media', array_merge(['product_id' => $this->product->id, 'title' => 'SECRET-ASSET-TITLE', 'file' => $f ?? UploadedFile::fake()->image('hero.jpg', 800, 800)], $extra), ['Accept' => 'application/json']);
    // a real UploadedFile (not UploadedFile::fake()) so MIME detection reads the bytes, as in production
    $this->mp4 = function () {
        file_put_contents($tmp = tempnam(sys_get_temp_dir(), 'mp4'), "\x00\x00\x00\x18ftypisom\x00\x00\x02\x00isomiso2mp41".str_repeat("\x00", 4096));

        return new UploadedFile($tmp, 'clip.mp4', 'video/mp4', null, true);
    };
    $this->disk = fn () => Storage::disk(config('media.private_disk'));
});

it('stores uploads on the private disk only, with versions and no public URL', function () {
    $this->actingAs($this->owner, 'admin');
    $id = ($this->upload)()->assertOk()->json('data.id');
    $a = SocialMediaAsset::findOrFail($id);
    expect($a->status->value)->toBe('pending_review')->and($a->kind->value)->toBe('image')->and($a->version)->toBe(1)->and($a->width)->toBe(800)
        ->and($a->path)->toStartWith('social-media/'.$this->product->id.'/')->and($a->disk)->toBe(config('media.private_disk'))->and($a->original_name)->toBe('hero.jpg')
        ->and($a->versions()->count())->toBe(1);
    ($this->disk)()->assertExists($a->path);
    Storage::disk(config('media.disk'))->assertDirectoryEmpty('/');
    expect($a->toArray())->not->toHaveKeys(['path', 'thumb_path', 'disk']);   // never serialised

    $r = $this->get("/marketer/media/$id/file")->assertOk();
    expect($r->headers->get('Cache-Control'))->toContain('no-store')->toContain('private')->and($r->headers->get('X-Robots-Tag'))->toContain('noindex')
        ->and($r->headers->get('X-Content-Type-Options'))->toBe('nosniff')->and($r->headers->get('Content-Disposition'))->toStartWith('inline');
    expect($this->get("/marketer/media/$id/download")->assertOk()->headers->get('Content-Disposition'))->toStartWith('attachment');
    $this->get("/marketer/media/$id/thumb")->assertOk();

    // new version keeps the old one downloadable and goes back to review
    $this->post("/marketer/media/$id/approve", [], ['Accept' => 'application/json'])->assertOk();
    $this->post("/marketer/media/$id/version", ['file' => UploadedFile::fake()->image('hero2.png', 300, 300), 'note' => 'v2'], ['Accept' => 'application/json'])->assertOk();
    $a->refresh();
    expect($a->version)->toBe(2)->and($a->status->value)->toBe('pending_review')->and($a->versions()->count())->toBe(2)->and($a->mime)->toBe('image/png');
    $this->get("/marketer/media/$id/download?v=1")->assertOk();
    $this->get("/marketer/media/$id/download?v=9")->assertNotFound();
});

it('refuses malicious or unsupported uploads', function (callable $make, string $field) {
    $this->actingAs($this->owner, 'admin');
    ($this->upload)([], $make())->assertStatus(422)->assertJsonValidationErrors([$field]);
    expect(SocialMediaAsset::count())->toBe(0);
})->with([
    'php as jpg' => [fn () => UploadedFile::fake()->createWithContent('shell.jpg', '<?php system($_GET["c"]); ?>'), 'file'],
    'svg' => [fn () => UploadedFile::fake()->createWithContent('x.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'), 'file'],
    'html' => [fn () => UploadedFile::fake()->createWithContent('x.mp4', '<html><script>alert(1)</script></html>'), 'file'],
    'pdf' => [fn () => UploadedFile::fake()->createWithContent('x.pdf', "%PDF-1.4\n"), 'file'],
]);

it('validates kinds, size limits, dimensions and Canva links', function () {
    $this->actingAs($this->owner, 'admin');
    ($this->upload)(['canva_url' => 'https://evil.com/design/1'])->assertStatus(422)->assertJsonValidationErrors(['canva_url']);
    ($this->upload)(['canva_url' => 'javascript:alert(1)'])->assertStatus(422)->assertJsonValidationErrors(['canva_url']);
    ($this->upload)(['kind' => 'video'])->assertStatus(422)->assertJsonValidationErrors(['file']);          // image file, video kind
    ($this->upload)([], UploadedFile::fake()->image('tiny.jpg', 10, 10))->assertStatus(422)->assertJsonValidationErrors(['file']);
    config(['marketing.media.image_max_kb' => 1]);
    ($this->upload)([], UploadedFile::fake()->image('big.jpg', 1200, 1200))->assertStatus(422)->assertJsonValidationErrors(['file']);
    $this->post('/marketer/media', ['product_id' => $this->product->id, 'kind' => 'image'], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonValidationErrors(['file']);
    $this->post('/marketer/media', ['product_id' => 999999, 'kind' => 'post'], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonValidationErrors(['product_id']);
    config(['marketing.media.image_max_kb' => 15360]);

    $v = ($this->upload)(['duration_seconds' => 15, 'canva_url' => 'https://www.canva.com/design/abc/edit', 'platforms' => ['reels', 'tiktok'], 'tags' => 'Summer, summer , gift'], ($this->mp4)())->assertOk()->json('data.id');
    $a = SocialMediaAsset::findOrFail($v);
    expect($a->kind->value)->toBe('video')->and($a->mime)->toBe('video/mp4')->and($a->duration_seconds)->toBe(15)->and($a->thumb_path)->toBeNull()->and($a->platforms)->toBe(['reels', 'tiktok'])
        ->and($a->tags)->toBe(['summer', 'gift'])->and($a->canva_url)->toBe('https://www.canva.com/design/abc/edit');
    // text-only post draft: no file, plain text only
    $p = $this->post('/marketer/media', ['product_id' => $this->product->id, 'kind' => 'post', 'title' => '<b>Idea</b>', 'caption' => ['ar' => '<script>x</script>كابشن']], ['Accept' => 'application/json'])->assertOk()->json('data.id');
    $post = SocialMediaAsset::findOrFail($p);
    expect($post->status->value)->toBe('draft')->and($post->hasFile())->toBeFalse()->and($post->title)->toBe('Idea')->and($post->caption['ar'])->toBe('كابشن');
    $this->get("/marketer/media/$p/file")->assertNotFound();
});

it('is never publicly reachable', function () {
    $this->actingAs($this->owner, 'admin');
    $id = ($this->upload)()->assertOk()->json('data.id');
    $a = SocialMediaAsset::findOrFail($id);
    auth('admin')->logout();
    $this->flushSession();
    foreach (['file', 'thumb', 'download'] as $r) {
        $this->get("/marketer/media/$id/$r")->assertRedirect();                 // guest → login
    }
    expect(in_array($this->get('/storage/'.$a->path)->getStatusCode(), [403, 404], true))->toBeTrue()
        ->and($this->get('/media/'.$a->path)->getStatusCode())->toBe(404);
    // the signed route refuses a missing / tampered signature
    $this->get("/marketer/media-preview/$id?v=1")->assertForbidden();
    $url = app(App\Services\Marketing\Media\SocialMediaLibraryService::class)->signedPreviewUrl($a);
    $this->get($url)->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
    $this->get(str_replace('v=1', 'v=2', $url))->assertForbidden();
    $this->travel(11)->minutes();
    $this->get($url)->assertForbidden();
});

it('never appears in the storefront, public API or sitemap', function () {
    $this->actingAs($this->owner, 'admin');
    $id = ($this->upload)()->assertOk()->json('data.id');
    $a = SocialMediaAsset::findOrFail($id);
    $this->post("/marketer/media/$id/approve", [], ['Accept' => 'application/json'])->assertOk();
    auth('admin')->logout();
    $this->flushSession();
    $s = rtrim(config('app.url'), '/');
    foreach (["$s/ar/product/lib-item", "$s/en/product/lib-item", "$s/sitemap.xml"] as $u) {
        $this->get($u)->assertOk()->assertDontSee('SECRET-ASSET-TITLE')->assertDontSee('social-media/', false)->assertDontSee(basename($a->path), false);
    }
    foreach (["$s/api/v1/products", "$s/api/v1/products/lib-item", "$s/api/v1/products/{$this->product->id}"] as $u) {
        $r = $this->getJson($u);
        expect($r->getContent())->not->toContain('SECRET-ASSET-TITLE')->not->toContain('social-media/');
    }
    expect($this->product->fresh()->images()->count())->toBe(0);
});

it('enforces the permission matrix', function () {
    $this->actingAs($this->owner, 'admin');
    $id = ($this->upload)()->assertOk()->json('data.id');
    auth('admin')->logout();

    $viewer = ($this->staff)(['ai_media.view'], null, 'v@egy.test');
    $this->actingAs($viewer, 'admin');
    $this->get('/marketer/media')->assertOk();
    $this->get("/marketer/media/$id")->assertOk()->assertDontSee('data-action="approve"', false)->assertDontSee('data-upload-form', false);
    $this->get("/marketer/media/$id/file")->assertOk();
    ($this->upload)()->assertForbidden();
    $this->putJson("/marketer/media/$id", ['title' => 'x'])->assertForbidden();
    $this->postJson("/marketer/media/$id/approve")->assertForbidden();
    $this->postJson("/marketer/media/$id/reject")->assertForbidden();
    $this->deleteJson("/marketer/media/$id")->assertForbidden();

    $editor = ($this->staff)(['ai_media.view', 'ai_media.edit'], null, 'e@egy.test');
    $this->actingAs($editor, 'admin');
    $this->putJson("/marketer/media/$id", ['title' => 'Edited'])->assertOk();
    $this->postJson("/marketer/media/$id/approve")->assertForbidden();

    $none = ($this->staff)(['gift_finder.view'], null, 'n@egy.test');
    $this->actingAs($none, 'admin');
    $this->get('/marketer/media')->assertForbidden();
    $this->get("/marketer/media/$id/file")->assertForbidden();

    $marketer = ($this->staff)([], 'marketer', 'm@egy.test');
    $this->actingAs($marketer, 'admin');
    $this->postJson("/marketer/media/$id/approve")->assertOk();
    expect(SocialMediaAsset::find($id)->status->value)->toBe('approved');
    // a caption change on an approved item goes back to review; a campaign change does not
    $this->putJson("/marketer/media/$id", ['campaign' => 'Eid'])->assertOk();
    expect(SocialMediaAsset::find($id)->status->value)->toBe('approved');
    $this->putJson("/marketer/media/$id", ['caption' => ['ar' => 'جديد']])->assertOk();
    expect(SocialMediaAsset::find($id)->status->value)->toBe('pending_review');
    $this->postJson("/marketer/media/$id/reject", ['note' => 'no'])->assertOk();
    expect(SocialMediaAsset::find($id)->review_note)->toBe('no');
    $this->deleteJson("/marketer/media/$id")->assertOk();
    expect(SocialMediaAsset::find($id))->toBeNull()->and(SocialMediaAsset::withTrashed()->find($id))->not->toBeNull();
    $this->get("/marketer/media/$id/file")->assertNotFound();
});

it('shows the library page and the admin product tab (ar + en), filters, grid and list', function () {
    $this->actingAs($this->owner, 'admin');
    ($this->upload)(['campaign' => 'Eid', 'platforms' => ['tiktok'], 'tags' => 'gift'])->assertOk();
    foreach (['ar', 'en'] as $loc) {
        $this->withSession(['admin_locale' => $loc]);
        $this->get('/marketer/media')->assertOk()->assertSee('data-panel="marketer"', false)->assertSee('data-library-grid', false)->assertSee('data-internal-notice', false)->assertSee('SECRET-ASSET-TITLE');
        $this->get('/marketer/media?view=list')->assertOk()->assertSee('data-library-list', false);
        $this->get('/marketer/media?platform=facebook')->assertOk()->assertSee('data-library-empty', false);
        $this->get('/marketer/media?platform=tiktok&campaign=Eid&tag=gift')->assertOk()->assertSee('SECRET-ASSET-TITLE');
        $this->get("/admin/products/{$this->product->id}/edit")->assertOk()->assertSee('data-social-tab', false)->assertSee(__('marketing.media.tab', [], $loc))->assertDontSee('data-media-library', false);
        $this->get("/admin/products/{$this->product->id}/edit?tab=social")->assertOk()->assertSee('data-media-library', false)->assertSee('SECRET-ASSET-TITLE')->assertDontSee('name="sku"', false);
    }
    expect(__('marketing.media.tab', [], 'ar'))->toBe('محتوى السوشيال ميديا')->and(__('marketing.media.tab', [], 'en'))->toBe('Social media content');
    // product editor without library permission: no tab, ?tab=social is ignored
    $ed = ($this->staff)(['products.edit'], null, 'pe@egy.test');
    $this->actingAs($ed, 'admin');
    $this->get("/admin/products/{$this->product->id}/edit?tab=social")->assertOk()->assertDontSee('data-social-tab', false)->assertDontSee('data-media-library', false)->assertDontSee('SECRET-ASSET-TITLE');
    // the nav placeholder is live
    $this->actingAs($this->owner, 'admin')->get('/marketer')->assertSee(route('marketer.media.index'), false);
});

it('exposes metadata and signed previews on the internal API, never paths or bytes', function () {
    $this->actingAs($this->owner, 'admin');
    $id = ($this->upload)()->assertOk()->json('data.id');
    auth('admin')->logout();
    [, $plain] = app(MarketingTokenService::class)->create(new MarketingTokenData('mcp', ['marketing:read'], 30, null));
    $h = ['Authorization' => 'Bearer '.$plain];
    $this->getJson('/api/internal/marketing/media')->assertUnauthorized();
    $list = $this->getJson('/api/internal/marketing/media?kind=image', $h)->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $id)->assertJsonPath('data.0.internal_only', true);
    $one = $this->getJson("/api/internal/marketing/media/$id", $h)->assertOk()->assertJsonPath('data.versions.0.version', 1);
    $this->getJson("/api/internal/marketing/products/{$this->product->id}/social-media", $h)->assertOk()->assertJsonPath('meta.total', 1);
    $this->getJson('/api/internal/marketing/products/999999/social-media', $h)->assertNotFound();
    $this->getJson('/api/internal/marketing/media/999999', $h)->assertNotFound();
    $a = SocialMediaAsset::findOrFail($id);
    foreach ([$list, $one] as $r) {
        expect($r->getContent())->not->toContain($a->path);
    }
    $this->flushHeaders();
    $this->get($one->json('data.preview_url'))->assertOk();
    $this->postJson('/api/internal/marketing/media', [], $h)->assertNotFound();   // read-only
});

it('applies social_content and media_asset suggestions as private drafts and rolls them back', function () {
    $engine = app(SuggestionEngine::class);
    $make = function (string $type, array $payload) use ($engine) {
        [$s] = $engine->create(new SuggestionInput($type, 'T '.$type, null, 'why', 'normal', 'product', $this->product->id, $payload, null, null, 'scheduled', [], null, false), SuggestionActor::system());

        return $s;
    };
    expect(array_map(fn ($t) => $engine->registry()->get($t)->applicable(), ['social_content', 'media_asset']))->toBe([true, true]);

    $s = $make('social_content', ['platform' => 'instagram', 'format' => 'reel', 'caption' => ['ar' => 'كابشن'], 'hashtags' => ['عرض'], 'cta' => 'اطلب', 'media_brief' => 'لقطة', 'campaign' => 'Eid', 'canva_url' => 'https://www.canva.com/design/x']);
    $engine->approve($s->fresh(), $this->owner);
    $x = $engine->apply($s->fresh(), $this->owner);
    $s->refresh();
    expect($x->status->value)->toBe('succeeded')->and($s->status->value)->toBe('applied')->and($s->result_entity_type)->toBe('social_media_asset')->and($x->after['published'])->toBeFalse();
    $a = SocialMediaAsset::findOrFail($s->result_entity_id);
    expect($a->kind->value)->toBe('post')->and($a->status->value)->toBe('draft')->and($a->source)->toBe('suggestion')->and($a->suggestion_id)->toBe($s->id)->and($a->product_id)->toBe($this->product->id)
        ->and($a->caption['ar'])->toBe('كابشن')->and($a->hashtags)->toBe(['#عرض'])->and($a->platforms)->toBe(['instagram'])->and($a->format)->toBe('reel')->and($a->campaign)->toBe('Eid')->and($a->hasFile())->toBeFalse();
    // idempotent: a second apply creates nothing
    $engine->apply($s->fresh(), $this->owner);
    expect(SocialMediaAsset::count())->toBe(1);
    $this->actingAs($this->owner, 'admin')->get('/marketer/suggestions/'.$s->id)->assertOk()->assertSee(route('marketer.media.show', $a->id), false);

    $engine->rollback($s->fresh(), $this->owner);
    expect($a->fresh()->status->value)->toBe('archived')->and($s->fresh()->rolled_back_at)->not->toBeNull();

    $m = $make('media_asset', ['asset_kind' => 'video', 'brief' => 'Flat lay', 'aspect_ratio' => '9:16', 'duration_seconds' => 15, 'platform' => 'tiktok', 'tags' => ['Gift'], 'title' => ['en' => 'Clip']]);
    $engine->approve($m->fresh(), $this->owner);
    expect($engine->apply($m->fresh(), $this->owner)->status->value)->toBe('succeeded');
    $b = SocialMediaAsset::findOrFail($m->fresh()->result_entity_id);
    expect($b->title)->toBe('Clip')->and($b->kind->value)->toBe('post')->and($b->aspect_ratio)->toBe('9:16')->and($b->platforms)->toBe(['tiktok'])->and($b->tags)->toBe(['gift'])->and($b->brief)->toContain('Flat lay');
    // a file uploaded later turns the brief into a real video item, version 1
    $this->actingAs($this->owner, 'admin')->post("/marketer/media/{$b->id}/version", ['file' => ($this->mp4)()], ['Accept' => 'application/json'])->assertOk();
    expect($b->fresh()->kind->value)->toBe('video')->and($b->fresh()->version)->toBe(1)->and($b->fresh()->status->value)->toBe('pending_review');
    // deleted product: apply refused
    $g = $make('media_asset', ['asset_kind' => 'image', 'brief' => 'Other']);
    $engine->approve($g->fresh(), $this->owner);
    $this->product->delete();
    expect(fn () => $engine->apply($g->fresh(), $this->owner))->toThrow(App\Services\Marketing\Suggestions\SuggestionException::class);
});
