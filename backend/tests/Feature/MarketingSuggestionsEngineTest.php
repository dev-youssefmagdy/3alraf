<?php

// P5 (MARKETER_PLAN.md, MCP.md §16.3 / §23 / §28): approval + execution engine and the marketer Suggestions Center — permission matrix,
// approve / reject / edit-invalidates, apply per type through the store services, stale detection, idempotency, failed apply, rollback,
// SEO slug change + 301, duplicate slug, blog draft → explicit publish / schedule, seasonal theme preview / contrast / apply / revert,
// coupon / offer margin guards, XSS sanitisation, pages (ar / en) and the dashboard pending card.

use App\DTOs\Marketing\SuggestionEditData;
use App\DTOs\Marketing\SuggestionInput;
use App\Enums\ProductListType;
use App\Models\Admin;
use App\Models\Banner;
use App\Models\BlogPost;
use App\Models\Bundle;
use App\Models\Category;
use App\Models\DiscountCode;
use App\Models\FlashSale;
use App\Models\MarketingSuggestion;
use App\Models\MarketingSuggestionExecution;
use App\Models\MarketingTheme;
use App\Models\Product;
use App\Models\SeoRedirect;
use App\Models\StaticPage;
use App\Models\Tag;
use App\Services\BarcodeService;
use App\Services\Lists\ProductListService;
use App\Services\Marketing\Suggestions\SuggestionActor;
use App\Services\Marketing\Suggestions\SuggestionEngine;
use App\Services\Marketing\Suggestions\SuggestionException;
use App\Services\Marketing\ThemeService;
use App\Support\Marketing\RichText;
use Database\Seeders\FoundationSeeder;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(FoundationSeeder::class);
    Cache::flush();
    config(['app.url' => 'http://3alraf.test', 'app.host' => '3alraf.test']);
    $this->owner = Admin::query()->where('email', 'owner@egy.test')->first();
    $this->engine = app(SuggestionEngine::class);
    $this->staff = function (array $perms = [], ?string $role = null, string $email = 's@egy.test') {
        $a = Admin::create(['name' => 'S '.$email, 'email' => $email, 'password' => 'password', 'is_active' => true]);
        $perms && $a->givePermissionTo($perms);
        $role && $a->assignRole($role);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $a->fresh();
    };
    $this->product = function (string $slug, string $price = '100', string $cost = '60') {
        $p = Product::create(['type' => 'simple', 'name' => ['en' => 'Item '.$slug, 'ar' => 'صنف '.$slug], 'description' => ['en' => 'x'], 'slug' => $slug, 'sku' => strtoupper($slug),
            'barcode' => app(BarcodeService::class)->generateUnique()]);

        return listProduct($p, ['price' => $price, 'cost' => $cost, 'is_active' => true]);
    };
    // create (system actor, submitted) → returns the suggestion
    $this->make = function (string $type, array $payload, ?string $tt = null, ?int $tid = null, array $extra = []) {
        [$s] = $this->engine->create(new SuggestionInput($type, $extra['title'] ?? 'T '.$type, null, 'why', 'normal', $tt, $tid, $payload, null, null, 'scheduled', [], null, false), SuggestionActor::system());

        return $s;
    };
    $this->approveApply = function (MarketingSuggestion $s, array $options = [], ?Admin $by = null) {
        $by ??= $this->owner;
        $this->engine->approve($s->fresh(), $by);

        return $this->engine->apply($s->fresh(), $by, null, $options);
    };
});

it('enforces the lifecycle state machine, approval binding to the version and edit-invalidates-approval', function () {
    $p = ($this->product)('alpha');
    $s = ($this->make)('product', ['name' => ['ar' => 'اسم 1']], 'product', $p->id);
    expect($s->status->value)->toBe('pending');
    // apply without approval is refused, nothing executed
    expect(fn () => $this->engine->apply($s->fresh(), $this->owner))->toThrow(SuggestionException::class);
    $a = $this->engine->approve($s->fresh(), $this->owner, 'ok');
    expect($a->version)->toBe(1)->and($s->fresh()->status->value)->toBe('approved')->and($s->fresh()->validApproval()?->id)->toBe($a->id);
    // edit after approval: approval invalidated, back to edited, version 2
    $this->engine->update($s->fresh(), new SuggestionEditData(null, null, null, null, ['name' => ['ar' => 'اسم 2']], null, false, 'tweak'), SuggestionActor::admin($this->owner));
    $s->refresh();
    expect($s->status->value)->toBe('edited')->and($s->version)->toBe(2)->and($a->fresh()->invalidated_at)->not->toBeNull()->and($s->validApproval())->toBeNull();
    expect(fn () => $this->engine->apply($s->fresh(), $this->owner))->toThrow(SuggestionException::class, __('marketing.suggestions.errors.approval_required'));
    // illegal transitions
    $this->engine->reject($s->fresh(), $this->owner, 'no');
    try {
        $this->engine->approve($s->fresh(), $this->owner);
        $this->fail('approved a rejected suggestion');
    } catch (SuggestionException $e) {
        expect($e->errorCode)->toBe('illegal_transition')->and($e->status)->toBe(409);
    }
    $this->engine->archive($s->fresh(), $this->owner);
    expect(fn () => $this->engine->reject($s->fresh(), $this->owner))->toThrow(SuggestionException::class);
    expect($s->activity()->pluck('action')->all())->toEqual(['archived', 'rejected', 'edited', 'approved', 'created'])
        ->and(Product::find($p->id)->content)->toBeNull();   // nothing was applied
});

it('applies the permission matrix: owner and the marketer role approve, others are refused (typed approvals included)', function () {
    $p = ($this->product)('alpha');
    $seo = ($this->make)('seo', ['meta_title' => ['ar' => 'عنوان']], 'product', $p->id);
    $prod = ($this->make)('product', ['name' => ['ar' => 'اسم']], 'product', $p->id);
    $marketer = ($this->staff)([], 'marketer', 'm@egy.test');
    $baseOnly = ($this->staff)(['ai_suggestions.view', 'ai_suggestions.approve'], null, 'b@egy.test');
    $viewer = ($this->staff)(['ai_suggestions.view'], null, 'v@egy.test');
    $nobody = ($this->staff)(['orders.view'], null, 'n@egy.test');

    // routes
    $this->actingAs($viewer, 'admin')->postJson(route('marketer.suggestions.approve', $prod->id))->assertForbidden();
    $this->actingAs($viewer, 'admin')->postJson(route('marketer.suggestions.apply', $prod->id))->assertForbidden();
    $this->actingAs($viewer, 'admin')->get(route('marketer.suggestions.show', $prod->id))->assertOk()->assertDontSee('data-action="approve"', false);
    $this->actingAs($nobody, 'admin')->get(route('marketer.suggestions.index'))->assertForbidden();
    // base approve permission is not enough for SEO (needs ai_seo.approve)
    $this->actingAs($baseOnly, 'admin')->postJson(route('marketer.suggestions.approve', $seo->id))->assertForbidden()->assertJsonPath('error', 'forbidden');
    $this->actingAs($baseOnly, 'admin')->postJson(route('marketer.suggestions.approve', $prod->id), ['note' => 'fine'])->assertOk();
    // the marketer role approves SEO, the owner applies
    $this->actingAs($marketer, 'admin')->get(route('marketer.suggestions.show', $seo->id))->assertOk()->assertSee('data-action="approve"', false);
    $this->actingAs($marketer, 'admin')->postJson(route('marketer.suggestions.approve', $seo->id))->assertOk();
    $this->actingAs($this->owner, 'admin')->postJson(route('marketer.suggestions.apply', $seo->id), ['idempotency_key' => 'abc-1'])->assertOk();
    expect($seo->fresh()->status->value)->toBe('applied')->and($seo->fresh()->approved_by)->toBe($marketer->id)->and($seo->fresh()->applied_by)->toBe($this->owner->id);
    // reject needs ai_suggestions.reject
    $other = ($this->make)('product', ['name' => ['ar' => 'آخر']], 'product', $p->id);
    $this->actingAs($baseOnly, 'admin')->postJson(route('marketer.suggestions.reject', $other->id))->assertForbidden();
    $this->actingAs($marketer, 'admin')->postJson(route('marketer.suggestions.reject', $other->id), ['note' => 'not now'])->assertOk();
    expect($other->fresh()->status->value)->toBe('rejected')->and($other->approvals()->first()->note)->toBe('not now');
});

it('applies product merchandising through the services and rolls it back', function () {
    $p = ($this->product)('alpha');
    $hot = Tag::create(['name' => ['en' => 'Hot'], 'slug' => 'hot', 'is_active' => true]);
    $old = Tag::create(['name' => ['en' => 'Old'], 'slug' => 'old', 'is_active' => true]);
    $p->tags()->sync([$old->id]);
    $other = ($this->product)('beta');
    app(ProductListService::class)->pin(ProductListType::RECOMMENDED, $other->id);
    $s = ($this->make)('product', ['name' => ['ar' => 'اسم جذاب'], 'description' => ['ar' => '<p>وصف <b>قوي</b></p><img src=x onerror=alert(1)>'], 'tags_add' => [$hot->id], 'tags_remove' => [$old->id],
        'gift' => ['suitable' => true, 'recipients' => ['mother'], 'occasions' => ['mothers_day']], 'pin' => ['list' => 'recommended', 'position' => 1]], 'product', $p->id);
    $exec = ($this->approveApply)($s);
    expect($exec->status->value)->toBe('succeeded');
    $p->refresh();
    expect($p->content['name']['ar'])->toBe('اسم جذاب')->and($p->getTranslation('name', 'ar', false))->toBe('صنف alpha')   // catalog text untouched
        ->and($p->content['description']['ar'])->toBe('<p>وصف <b>قوي</b></p>')
        ->and($p->tags()->pluck('tags.id')->all())->toBe([$hot->id])->and($p->gift_suitable)->toBeTrue()->and($p->gift_occasions)->toBe(['mothers_day'])
        ->and(app(ProductListService::class)->pinnedIds(ProductListType::RECOMMENDED))->toBe([$p->id, $other->id]);
    $this->get('/ar/product/alpha')->assertOk()->assertSee('اسم جذاب');

    $rb = $this->engine->rollback($s->fresh(), $this->owner);
    $p->refresh();
    expect($rb->kind)->toBe('rollback')->and($rb->rollback_of)->toBe($exec->id)->and($p->content)->toBeNull()->and($p->tags()->pluck('tags.id')->all())->toBe([$old->id])
        ->and($p->gift_suitable)->toBeFalse()->and(app(ProductListService::class)->pinnedIds(ProductListType::RECOMMENDED))->toBe([$other->id])
        ->and($s->fresh()->status->value)->toBe('archived')->and($s->fresh()->rolled_back_at)->not->toBeNull();
    // rollback is idempotent
    expect($this->engine->rollback($s->fresh(), $this->owner)->id)->toBe($rb->id);
});

it('detects stale targets: apply is blocked with a conflict until re-snapshotted and re-approved', function () {
    $p = ($this->product)('alpha');
    $s = ($this->make)('seo', ['meta_title' => ['ar' => 'عنوان جديد']], 'product', $p->id);
    $this->engine->approve($s->fresh(), $this->owner);
    // somebody edits the product meta title after the suggestion was made
    $p->update(['meta_title' => ['ar' => 'تغيير يدوي']]);
    try {
        $this->engine->apply($s->fresh(), $this->owner);
        $this->fail('stale apply went through');
    } catch (SuggestionException $e) {
        expect($e->errorCode)->toBe('stale_target')->and($e->status)->toBe(409)->and($e->data['current'])->toBe(['meta_title' => ['ar' => 'تغيير يدوي']]);
    }
    expect($s->fresh()->status->value)->toBe('approved')->and(MarketingSuggestionExecution::count())->toBe(0)->and($s->activity()->first()->action)->toBe('conflict');
    $this->actingAs($this->owner, 'admin')->get(route('marketer.suggestions.show', $s->id))->assertOk()->assertSee('data-stale', false)->assertSee('data-action="resnapshot"', false);
    $this->engine->resnapshot($s->fresh(), $this->owner);
    expect($s->fresh()->status->value)->toBe('edited')->and($s->fresh()->version)->toBe(2)->and($s->fresh()->validApproval())->toBeNull();
    $exec = ($this->approveApply)($s);
    expect($exec->status->value)->toBe('succeeded')->and($p->fresh()->content['meta_title']['ar'])->toBe('عنوان جديد');
});

it('is idempotent: the same key returns the same execution and an applied suggestion is never applied twice', function () {
    $p = ($this->product)('alpha');
    $s = ($this->make)('coupon', ['code' => 'ONCE10', 'type' => 'percent', 'value' => 10, 'scope' => 'products', 'product_ids' => [$p->id], 'ends_at' => now()->addMonth()->toDateTimeString()]);
    $this->engine->approve($s->fresh(), $this->owner);
    $e1 = $this->engine->apply($s->fresh(), $this->owner, 'key-1');
    $e2 = $this->engine->apply($s->fresh(), $this->owner, 'key-1');
    $e3 = $this->engine->apply($s->fresh(), $this->owner, 'key-2');   // other key: already applied ⇒ previous result, no second coupon
    $this->actingAs($this->owner, 'admin')->postJson(route('marketer.suggestions.apply', $s->id), ['idempotency_key' => 'key-3'])->assertOk();
    expect($e2->id)->toBe($e1->id)->and($e3->id)->toBe($e1->id)->and(DiscountCode::where('code', 'ONCE10')->count())->toBe(1)
        ->and(MarketingSuggestionExecution::where('kind', 'apply')->count())->toBe(1)->and(DiscountCode::where('code', 'ONCE10')->first()->is_active)->toBeFalse();
});

it('records a failed apply as failed (never applied) and allows a retry', function () {
    $p = ($this->product)('alpha');
    $s = ($this->make)('coupon', ['code' => 'RACE5', 'type' => 'percent', 'value' => 5, 'scope' => 'products', 'product_ids' => [$p->id], 'ends_at' => now()->addMonth()->toDateTimeString()]);
    $this->engine->approve($s->fresh(), $this->owner);
    DiscountCode::create(['code' => 'RACE5', 'type' => 'fixed', 'value' => 1, 'min_order' => 0, 'scope' => 'all', 'is_active' => true]);   // created meanwhile by hand
    $exec = $this->engine->apply($s->fresh(), $this->owner, 'k1');
    expect($exec->status->value)->toBe('failed')->and($exec->error)->toContain(__('marketing.suggestions.errors.code_taken'))
        ->and($s->fresh()->status->value)->toBe('failed')->and($s->fresh()->applied_at)->toBeNull()->and($s->fresh()->last_error)->not->toBeNull()
        ->and(DiscountCode::where('code', 'RACE5')->count())->toBe(1);
    $this->actingAs($this->owner, 'admin')->get(route('marketer.suggestions.show', $s->id))->assertOk()->assertSee('data-last-error', false);
    // fix the payload (edit ⇒ re-approval) and retry
    $this->engine->update($s->fresh(), new SuggestionEditData(null, null, null, null, ['code' => 'RACE6', 'type' => 'percent', 'value' => 5, 'scope' => 'products', 'product_ids' => [$p->id],
        'ends_at' => now()->addMonth()->toDateTimeString()], null, false, null), SuggestionActor::admin($this->owner));
    $exec2 = ($this->approveApply)($s);
    expect($exec2->status->value)->toBe('succeeded')->and($s->fresh()->status->value)->toBe('applied')->and(DiscountCode::where('code', 'RACE6')->exists())->toBeTrue();
});

it('changes SEO meta + slug with a 301 redirect, refuses duplicate slugs and rolls back (product, category, static page, site)', function () {
    $p = ($this->product)('old-slug');
    ($this->product)('taken-slug');
    expect(fn () => ($this->make)('seo', ['slug' => 'taken-slug'], 'product', $p->id))->toThrow(SuggestionException::class);

    $s = ($this->make)('seo', ['meta_title' => ['ar' => 'عنوان SEO'], 'meta_description' => ['ar' => str_repeat('وصف مفيد ', 8)], 'slug' => 'new-slug',
        'notes' => ['keywords' => ['هدايا'], 'internal_links' => ['http://3alraf.test/ar/category/x']]], 'product', $p->id);
    expect($s->payload['notes']['internal_links'])->toBe(['/ar/category/x']);
    $exec = ($this->approveApply)($s);
    expect($exec->status->value)->toBe('succeeded')->and($p->fresh()->slug)->toBe('new-slug')->and($p->fresh()->content['meta_title']['ar'])->toBe('عنوان SEO');
    $this->get('/ar/product/old-slug?utm_source=x')->assertStatus(301)->assertRedirect('/ar/product/new-slug?utm_source=x');
    $this->get('/en/product/old-slug')->assertStatus(301)->assertRedirect('/en/product/new-slug');
    $this->get('/ar/product/new-slug')->assertOk()->assertSee('<title>عنوان SEO', false);
    expect(SeoRedirect::first()->hits)->toBe(2);

    // chain: new-slug → newer-slug keeps old-slug pointing at the live URL
    $s2 = ($this->make)('seo', ['slug' => 'newer-slug'], 'product', $p->id);
    ($this->approveApply)($s2);
    $this->get('/ar/product/old-slug')->assertRedirect('/ar/product/newer-slug');
    $this->get('/ar/product/new-slug')->assertRedirect('/ar/product/newer-slug');
    // rollback of the 2nd change: slug back, its redirect removed, the old one points back
    $this->engine->rollback($s2->fresh(), $this->owner);
    expect($p->fresh()->slug)->toBe('new-slug');
    $this->get('/ar/product/new-slug')->assertOk();
    $this->get('/ar/product/old-slug')->assertRedirect('/ar/product/new-slug');

    // category: meta stored in seo_metas and rendered by the storefront layout
    $c = Category::create(['name' => ['ar' => 'عطور', 'en' => 'Perfume'], 'slug' => 'perfume', 'path' => '', 'depth' => 0, 'is_active' => true]);
    $cs = ($this->make)('seo', ['meta_title' => ['ar' => 'أفضل العطور'], 'meta_description' => ['ar' => 'تشكيلة عطور أصلية بأسعار مناسبة مع شحن سريع لكل المحافظات']], 'category', $c->id);
    ($this->approveApply)($cs);
    $this->get('/ar/category/perfume')->assertOk()->assertSee('<title>أفضل العطور', false)->assertSee('تشكيلة عطور أصلية', false);
    $this->engine->rollback($cs->fresh(), $this->owner);
    $this->get('/ar/category/perfume')->assertOk()->assertDontSee('أفضل العطور', false);

    // static page slug + site home title
    $sp = StaticPage::create(['slug' => 'about-store', 'title' => ['ar' => 'من نحن'], 'content' => ['ar' => 'نص'], 'is_active' => true]);
    ($this->approveApply)(($this->make)('seo', ['slug' => 'about-our-store'], 'static_page', $sp->id));
    $this->get('/ar/page/about-store')->assertRedirect('/ar/page/about-our-store');
    $home = ($this->make)('seo', ['meta_title' => ['ar' => 'عالراف | هدايا']], 'site');
    expect($home->target_entity_id)->toBeNull();
    ($this->approveApply)($home);
    expect(setting('seo')['home_title']['ar'])->toBe('عالراف | هدايا');
});

it('creates the blog post as a draft on apply and publishes / schedules only through the explicit actions', function () {
    BlogPost::create(['slug' => 'exists', 'title' => ['ar' => 'مقال قديم'], 'content' => ['ar' => 'x'], 'is_active' => true, 'published_at' => now()]);
    expect(fn () => ($this->make)('blog', ['title' => ['ar' => 'آخر'], 'slug' => 'exists']))->toThrow(SuggestionException::class);
    expect(fn () => ($this->make)('blog', ['title' => ['ar' => 'مقال قديم'], 'slug' => 'other']))->toThrow(SuggestionException::class);

    $s = ($this->make)('blog', ['title' => ['ar' => 'دليل هدايا رمضان'], 'slug' => 'ramadan-gifts', 'meta_title' => ['ar' => 'هدايا رمضان 2027'],
        'content' => ['ar' => '<h2>مقدمة</h2><p onclick="x()">نص <a href="/ar/category/gifts">هدايا</a> <a href="https://evil.com">شر</a></p><script>steal()</script><iframe src="x"></iframe>'],
        'outline' => ['مقدمة', 'أفكار'], 'internal_links' => [['url' => '/ar/category/gifts', 'anchor' => 'هدايا']]]);
    expect($s->payload['content']['ar'])->toBe('<h2>مقدمة</h2><p>نص <a href="/ar/category/gifts">هدايا</a> شر</p>');
    $this->actingAs($this->owner, 'admin')->get(route('marketer.suggestions.show', $s->id))->assertOk()->assertSee('data-article="ar"', false)->assertDontSee('steal()', false);

    $exec = ($this->approveApply)($s);
    $post = BlogPost::find($s->fresh()->result_entity_id);
    expect($exec->status->value)->toBe('succeeded')->and($post->slug)->toBe('ramadan-gifts')->and($post->is_active)->toBeFalse()->and($post->published_at)->toBeNull()
        ->and($s->fresh()->result_entity_type)->toBe('blog_post');
    $this->get('/ar/blog/ramadan-gifts')->assertNotFound();

    // publish needs ai_blog.approve as well
    $noBlog = ($this->staff)(['ai_suggestions.view', 'ai_suggestions.apply'], null, 'nb@egy.test');
    $this->actingAs($noBlog, 'admin')->postJson(route('marketer.suggestions.publish', $s->id))->assertForbidden();
    // schedule, then publish now
    $this->actingAs($this->owner, 'admin')->postJson(route('marketer.suggestions.publish', $s->id), ['publish_at' => now()->addDays(2)->format('Y-m-d H:i')])->assertOk();
    expect($post->fresh()->is_active)->toBeTrue()->and($post->fresh()->published_at->isFuture())->toBeTrue();
    $this->get('/ar/blog/ramadan-gifts')->assertNotFound();
    $this->actingAs($this->owner, 'admin')->postJson(route('marketer.suggestions.publish', $s->id))->assertOk();
    $this->get('/ar/blog/ramadan-gifts')->assertOk()->assertSee('href="/ar/category/gifts"', false)->assertDontSee('evil.com', false)->assertSee('<title>هدايا رمضان 2027', false);
    expect(MarketingSuggestionExecution::where('suggestion_id', $s->id)->pluck('kind')->sort()->values()->all())->toBe(['apply', 'publish', 'schedule']);
    // a published post is not deleted by a rollback
    expect(fn () => $this->engine->rollback($s->fresh(), $this->owner))->toThrow(SuggestionException::class);
});

it('previews seasonal themes only for authorised admins, warns on contrast, applies, reverts and rolls back', function () {
    $s = ($this->make)('seasonal_theme', ['key' => 'ramadan', 'name' => ['ar' => 'رمضان'], 'tokens' => ['color-brand-600' => '#1e5b4f', 'color-brand-800' => '#0f3d34', 'color-slate-500' => '#cccccc']]);
    $check = $this->engine->warnings($s);
    expect(collect($check['warnings'])->implode(' '))->toContain(__('marketing.suggestions.contrast_pairs.muted_text'));
    $url = app(ThemeService::class)->previewUrl($s, 'ar');
    // guests and unsigned / unauthorised requests never see the preview
    $this->get($url)->assertOk()->assertDontSee('id="seasonal-theme"', false);
    $viewer = ($this->staff)(['orders.view'], null, 'o@egy.test');
    $this->actingAs($viewer, 'admin')->get($url)->assertOk()->assertDontSee('#1e5b4f', false);
    $this->actingAs($this->owner, 'admin')->get('/ar?theme_preview='.$s->id)->assertOk()->assertDontSee('#1e5b4f', false);
    $this->actingAs($this->owner, 'admin')->get($url)->assertOk()->assertSee('--color-brand-600:#1e5b4f;', false)->assertSee('data-theme-preview-bar', false)->assertSee('noindex,nofollow', false);
    $this->actingAs($this->owner, 'admin')->get('/ar?theme_preview=off')->assertOk()->assertDontSee('#1e5b4f', false);
    $this->actingAs($this->owner, 'admin')->get(route('marketer.suggestions.show', $s->id))->assertOk()->assertSee('data-pair="muted_text" data-ok="0"', false)->assertSee('data-swatches', false);
    $this->actingAs($this->owner, 'admin')->get(route('marketer.suggestions.preview', $s->id))->assertRedirect();

    // theme approval needs ai_theme.approve
    $noTheme = ($this->staff)(['ai_suggestions.view', 'ai_suggestions.approve'], null, 'nt@egy.test');
    $this->actingAs($noTheme, 'admin')->postJson(route('marketer.suggestions.approve', $s->id))->assertForbidden();

    ($this->approveApply)($s);
    $v1 = MarketingTheme::where('is_active', true)->first();
    expect($v1->key)->toBe('ramadan')->and($v1->version)->toBe(1)->and($v1->suggestion_id)->toBe($s->id);
    auth('admin')->logout();
    $this->get('/ar')->assertOk()->assertSee('<style id="seasonal-theme"', false)->assertSee('--color-brand-600:#1e5b4f;', false)->assertSee('content="#1e5b4f"', false);

    // second version, revert to the first, then defaults
    $s2 = ($this->make)('seasonal_theme', ['key' => 'ramadan', 'name' => ['ar' => 'رمضان 2'], 'tokens' => ['color-brand-600' => '#22553a']]);
    ($this->approveApply)($s2);
    expect(MarketingTheme::where('is_active', true)->first()->version)->toBe(2);
    $this->actingAs($this->owner, 'admin')->postJson(route('marketer.suggestions.themes.revert'))->assertOk();
    expect(MarketingTheme::where('is_active', true)->value('id'))->toBe($v1->id);
    $this->actingAs($this->owner, 'admin')->postJson(route('marketer.suggestions.themes.off'))->assertOk();
    auth('admin')->logout();
    $this->get('/ar')->assertOk()->assertDontSee('id="seasonal-theme"', false);
    // rollback of s2 restores what was active before it (v1)
    $this->engine->rollback($s2->fresh(), $this->owner);
    expect(MarketingTheme::where('is_active', true)->value('id'))->toBe($v1->id);
});

it('guards coupon and offer margins (incl. flash + coupon stacking) and applies offers / bundles / sliders inactive', function () {
    $p = ($this->product)('alpha', '100', '80');
    // coupon 30% on a 20% margin product ⇒ refused; 10% ⇒ fine
    expect(fn () => ($this->make)('coupon', ['code' => 'BIG30', 'type' => 'percent', 'value' => 30, 'scope' => 'products', 'product_ids' => [$p->id]]))->toThrow(SuggestionException::class);
    expect(fn () => ($this->make)('coupon', ['code' => 'FIX50', 'type' => 'fixed', 'value' => 50, 'scope' => 'all']))->toThrow(SuggestionException::class);
    // offer below cost ⇒ refused
    expect(fn () => ($this->make)('offer', ['name' => ['ar' => 'x'], 'starts_at' => now()->addDay()->toDateTimeString(), 'ends_at' => now()->addDays(2)->toDateTimeString(),
        'items' => [['product_id' => $p->id, 'discount_type' => 'price', 'value' => 70]]]))->toThrow(SuggestionException::class);
    $offer = ($this->make)('offer', ['name' => ['ar' => 'عرض'], 'starts_at' => now()->addDay()->toDateTimeString(), 'ends_at' => now()->addDays(5)->toDateTimeString(),
        'items' => [['product_id' => $p->id, 'discount_type' => 'percent', 'value' => 10, 'qty_limit' => 20]]]);
    ($this->approveApply)($offer);
    $sale = FlashSale::find($offer->fresh()->result_entity_id);
    expect($sale->is_active)->toBeFalse()->and($sale->items()->first()->qty_limit)->toBe(20);
    // the same 10% coupon is fine alone but stacks with an active overlapping flash sale (90 − 10% = 81 ≥ 80 ok; 15% ⇒ 76.5 < 80 refused)
    $sale->update(['is_active' => true]);
    expect(fn () => ($this->make)('coupon', ['code' => 'STACK15', 'type' => 'percent', 'value' => 15, 'scope' => 'products', 'product_ids' => [$p->id],
        'starts_at' => now()->addDay()->toDateTimeString(), 'ends_at' => now()->addDays(3)->toDateTimeString()]))->toThrow(SuggestionException::class);
    $ok = ($this->make)('coupon', ['code' => 'OK10', 'type' => 'percent', 'value' => 10, 'scope' => 'products', 'product_ids' => [$p->id], 'usage_limit' => 100]);
    ($this->approveApply)($ok, ['activate' => true]);
    expect(DiscountCode::where('code', 'OK10')->first()->is_active)->toBeTrue();

    // bundle: created inactive through BundleService; slider: created inactive, activation refused without an image
    $b2 = ($this->product)('beta', '50', '20');
    $bundle = ($this->make)('bundle', ['name' => ['ar' => 'باقة', 'en' => 'Pack'], 'price' => '140', 'items' => [['product_id' => $p->id, 'quantity' => 1], ['product_id' => $b2->id, 'quantity' => 1]]]);
    ($this->approveApply)($bundle);
    $b = Bundle::find($bundle->fresh()->result_entity_id);
    expect($b->is_active)->toBeFalse()->and((string) $b->price)->toBe('140.00')->and($b->items()->count())->toBe(2);
    // update an existing bundle's price (only the price changes)
    $upd = ($this->make)('bundle', ['price' => '145'], 'bundle', $b->id);
    ($this->approveApply)($upd);
    expect((string) $b->fresh()->price)->toBe('145.00')->and($b->fresh()->items()->count())->toBe(2)->and($b->fresh()->name)->toBe($b->name);
    $this->engine->rollback($upd->fresh(), $this->owner);
    expect((string) $b->fresh()->price)->toBe('140.00');

    $slider = ($this->make)('slider', ['placement' => 'home_hero', 'title' => ['ar' => 'رمضان كريم'], 'link_url' => '/ar/category/gifts']);
    $this->engine->approve($slider->fresh(), $this->owner);
    expect($this->engine->apply($slider->fresh(), $this->owner, null, ['activate' => true])->status->value)->toBe('failed');
    $exec = $this->engine->apply($slider->fresh(), $this->owner, 'retry');
    $banner = Banner::find($slider->fresh()->result_entity_id);
    expect($exec->status->value)->toBe('succeeded')->and($banner->is_active)->toBeFalse()->and($banner->link_url)->toBe('/ar/category/gifts');
});

it('sanitises rich text with a strict allowlist (XSS)', function () {
    $evil = '<p style="x" onclick="a()">Hi<script>alert(1)</script><img src=x onerror=alert(2)><svg onload=alert(3)></svg>'
        .'<a href="javascript:alert(4)">j</a><a href="//evil.com">p</a><a href="/ar/x" onmouseover="b()">ok</a><a href="http://3alraf.test/en/y?a=1">abs</a>'
        .'<iframe src="https://evil.com"></iframe><h2>T</h2><style>body{}</style><!-- c --><unknown>txt</unknown>&lt;b&gt;</p>';
    $clean = RichText::clean($evil);
    foreach (['<script', 'alert', 'onerror', 'onclick', 'onmouseover', 'onload', 'style', 'javascript:', '<img', '<svg', '<iframe', 'evil.com', '<!--', '<unknown', 'href="//'] as $bad) {
        expect(strtolower($clean))->not->toContain(strtolower($bad));
    }
    expect($clean)->toContain('<a href="/ar/x">ok</a>')->toContain('<a href="/en/y?a=1">abs</a>')->toContain('<h2>T</h2>')->toContain('txt')->toContain('&lt;b&gt;')
        ->and(RichText::clean('<b>x</b> <i>y</i> <ul><li>z</li></ul>'))->toBe('<b>x</b> <i>y</i> <ul><li>z</li></ul>')
        ->and(RichText::render("plain & co\nline"))->toBe("plain &amp; co<br />\nline");
});

it('renders every Suggestions Center page for the owner (ar + en) and lights the dashboard pending card', function () {
    $p = ($this->product)('alpha');
    $ids = [
        ($this->make)('product', ['name' => ['ar' => 'اسم']], 'product', $p->id)->id,
        ($this->make)('seasonal_theme', ['key' => 'eid', 'name' => ['ar' => 'عيد'], 'tokens' => ['color-accent' => '#ffcc00']])->id,
        ($this->make)('seo', ['meta_title' => ['ar' => 'x']], 'product', $p->id)->id,
        ($this->make)('social_content', ['platform' => 'tiktok', 'format' => 'video', 'caption' => ['ar' => 'x']], 'product', $p->id)->id,
        ($this->make)('blog', ['title' => ['ar' => 'مقال'], 'slug' => 'maqal', 'content' => ['ar' => '<p>x</p>']])->id,
    ];
    $pages = ['/marketer/suggestions', '/marketer/suggestions/log', ...array_map(fn ($pg) => '/marketer/suggestions/'.$pg, \App\Enums\Marketing\SuggestionTypeKey::pages())];
    $this->actingAs($this->owner, 'admin');
    foreach (['ar', 'en'] as $loc) {
        $this->withSession(['admin_locale' => $loc]);
        foreach ($pages as $u) {
            $this->get($u)->assertOk()->assertSee('data-panel="marketer"', false);
        }
        foreach ($ids as $id) {
            $this->get('/marketer/suggestions/'.$id)->assertOk()->assertSee('data-compare', false);
            $this->get('/marketer/suggestions/'.$id.'/edit')->assertOk()->assertSee('data-edit-form', false);
        }
    }
    $this->get('/marketer/suggestions/datatable?page=seo&status=pending')->assertOk()->assertJsonPath('recordsTotal', 1);
    $this->get('/marketer/suggestions/datatable?product_id='.$p->id)->assertOk()->assertJsonPath('recordsTotal', 3);
    $this->get('/marketer/suggestions/log/executions')->assertOk();
    $this->get('/marketer/suggestions/'.$ids[3])->assertDontSee('data-p6', false);   // P6: social types are applicable now
    // the nav shows the Suggestions group, the dashboard counts pending suggestions
    $this->get('/marketer')->assertOk()->assertSee('data-pending-suggestions', false)->assertSee(route('marketer.suggestions.index'), false);
    expect(trim(strip_tags((string) str($this->get('/marketer')->getContent())->betweenFirst('data-pending-suggestions>', '</p>'))))->toBe('5');
});

it('edits through the panel form (json / list / locale fields), validating before saving', function () {
    $p = ($this->product)('alpha');
    $b2 = ($this->product)('beta', '50', '20');
    $s = ($this->make)('bundle', ['name' => ['ar' => 'باقة'], 'price' => '140', 'items' => [['product_id' => $p->id, 'quantity' => 1], ['product_id' => $b2->id, 'quantity' => 1]]]);
    $this->actingAs($this->owner, 'admin');
    $form = ['title' => 'Better pack', 'priority' => 'high', 'payload' => ['name' => ['ar' => 'باقة رمضان', 'en' => ''], 'price' => '159', 'items' => 'not json']];
    $this->postJson(route('marketer.suggestions.validate', $s->id), $form)->assertStatus(422)->assertJsonValidationErrors(['payload.items']);
    $form['payload']['items'] = json_encode([['product_id' => $p->id, 'quantity' => 2], ['product_id' => $b2->id, 'quantity' => 1]]);
    $this->postJson(route('marketer.suggestions.validate', $s->id), $form)->assertOk();
    $this->putJson(route('marketer.suggestions.update', $s->id), $form + ['note' => 'qty 2'])->assertOk()->assertJsonPath('redirect', route('marketer.suggestions.show', $s->id));
    $s->refresh();
    expect($s->status->value)->toBe('edited')->and($s->version)->toBe(2)->and($s->title)->toBe('Better pack')->and($s->payload['price'])->toBe('159.00')
        ->and($s->payload['name'])->toBe(['ar' => 'باقة رمضان'])->and(collect($s->payload['items'])->firstWhere('product_id', $p->id)['quantity'])->toBe(2);
    $viewer = ($this->staff)(['ai_suggestions.view'], null, 'v2@egy.test');
    $this->actingAs($viewer, 'admin')->get(route('marketer.suggestions.edit', $s->id))->assertForbidden();
});

it('shows an edit button in the suggestions table only where a person can still edit', function () {
    $p = ($this->product)('alpha');
    $b2 = ($this->product)('beta', '50', '20');
    $s = ($this->make)('bundle', ['name' => ['ar' => 'باقة'], 'price' => '140', 'items' => [['product_id' => $p->id, 'quantity' => 1], ['product_id' => $b2->id, 'quantity' => 1]]]);
    $actions = fn ($who) => $this->actingAs($who, 'admin')->get('/marketer/suggestions/datatable')->assertOk()->json('data.0.actions');
    $edit = route('marketer.suggestions.edit', $s->id);

    expect($actions($this->owner))->toContain($edit);
    expect($actions(($this->staff)(['ai_suggestions.view'], null, 'v3@egy.test')))->not->toContain($edit);
    $s->forceFill(['status' => 'rejected'])->save();
    expect($actions($this->owner))->not->toContain($edit);
});
