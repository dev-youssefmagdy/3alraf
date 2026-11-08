<?php

// P5 (MARKETER_PLAN.md, MCP.md §22/§28): suggestion write API — create per type + validation, unknown fields, dedup / cooldown,
// edit own draft + revisions, submit, list / filter / status / result, and that NO approve / apply / reject endpoint exists.

use App\DTOs\Marketing\MarketingTokenData;
use App\Models\Admin;
use App\Models\BlogPost;
use App\Models\Category;
use App\Models\MarketingSuggestion;
use App\Models\Product;
use App\Models\Tag;
use App\Services\BarcodeService;
use App\Services\Marketing\MarketingTokenService;
use App\Services\Marketing\Suggestions\SuggestionEngine;
use Database\Seeders\FoundationSeeder;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    $this->seed(FoundationSeeder::class);
    Cache::flush();
    config(['app.url' => 'http://3alraf.test', 'app.host' => '3alraf.test']);
    $this->owner = Admin::query()->where('email', 'owner@egy.test')->first();
    $this->mkToken = fn (array $scopes = ['marketing:read', 'suggestions:write']) => app(MarketingTokenService::class)->create(new MarketingTokenData('mcp', $scopes, null, null))[1];
    $this->tok = ($this->mkToken)();
    $this->product = function (string $slug, string $price = '100', string $cost = '60') {
        $p = Product::create(['type' => 'simple', 'name' => ['en' => 'Item '.$slug, 'ar' => 'صنف '.$slug], 'description' => ['en' => 'x'], 'slug' => $slug, 'sku' => strtoupper($slug),
            'barcode' => app(BarcodeService::class)->generateUnique()]);

        return listProduct($p, ['price' => $price, 'cost' => $cost, 'is_active' => true]);
    };
    $this->call = fn (string $method, string $path, array $body = [], ?string $tok = null) => $this->flushHeaders()
        ->withHeaders(['Accept' => 'application/json', 'Authorization' => 'Bearer '.($tok ?? $this->tok)])->json($method, '/api/internal/marketing/'.$path, $body);
    $this->base = fn (array $extra = []) => array_replace_recursive(['title' => 'Suggestion title', 'reasoning' => 'Because the data says so.', 'priority' => 'high'], $extra);
});

it('creates one suggestion per type through the API with the right status, version, hash and target snapshot', function () {
    $p = ($this->product)('alpha');
    $p2 = ($this->product)('beta', '80', '30');
    $tag = Tag::create(['name' => ['en' => 'Hot', 'ar' => 'رائج'], 'slug' => 'hot', 'is_active' => true]);
    $cases = [
        'product' => ['target_entity_type' => 'product', 'target_entity_id' => $p->id, 'payload' => ['name' => ['ar' => 'اسم أفضل'], 'tags_add' => [$tag->id], 'pin' => ['list' => 'recommended', 'position' => 1]]],
        'bundle' => ['payload' => ['name' => ['ar' => 'باقة', 'en' => 'Pack'], 'price' => '150', 'items' => [['product_id' => $p->id, 'quantity' => 1], ['product_id' => $p2->id, 'quantity' => 1]]]],
        'slider' => ['payload' => ['placement' => 'home_hero', 'title' => ['ar' => 'عرض'], 'link_url' => 'http://3alraf.test/ar/product/alpha']],
        'offer' => ['payload' => ['name' => ['ar' => 'عرض'], 'starts_at' => now()->addDay()->toDateTimeString(), 'ends_at' => now()->addDays(3)->toDateTimeString(),
            'items' => [['product_id' => $p->id, 'discount_type' => 'percent', 'value' => 20]]]],
        'coupon' => ['payload' => ['code' => 'save10', 'type' => 'percent', 'value' => 10, 'scope' => 'products', 'product_ids' => [$p->id], 'ends_at' => now()->addMonth()->toDateTimeString()]],
        'seo' => ['target_entity_type' => 'product', 'target_entity_id' => $p->id, 'payload' => ['meta_title' => ['ar' => 'عنوان'], 'meta_description' => ['ar' => str_repeat('وصف ', 20)]]],
        'blog' => ['payload' => ['title' => ['ar' => 'مقال جديد'], 'slug' => 'new-article', 'content' => ['ar' => '<p>نص</p><script>alert(1)</script>']]],
        'seasonal_theme' => ['payload' => ['key' => 'ramadan', 'name' => ['ar' => 'رمضان'], 'tokens' => ['color-brand-600' => '#1E5B4F', 'color-accent' => 'd4a017']]],
        'social_content' => ['target_entity_type' => 'product', 'target_entity_id' => $p->id, 'payload' => ['platform' => 'instagram', 'format' => 'reel', 'caption' => ['ar' => 'كابشن'], 'hashtags' => ['عرض', '#sale']]],
        'media_asset' => ['target_entity_type' => 'product', 'target_entity_id' => $p->id, 'payload' => ['asset_kind' => 'image', 'brief' => 'Flat lay on marble', 'aspect_ratio' => '4:5']],
    ];
    foreach ($cases as $type => $body) {
        $res = ($this->call)('POST', 'suggestions/'.$type, ($this->base)($body + ['source_meta' => ['model' => 'claude', 'tool' => 'create_'.$type.'_suggestion']]));
        $res->assertCreated()->assertJsonPath('success', true)->assertJsonPath('data.type', $type)->assertJsonPath('data.status', 'pending')->assertJsonPath('data.version', 1)
            ->assertJsonPath('data.own', true)->assertJsonPath('data.submitted', true);
        expect(strlen($res->json('data.content_hash')))->toBe(64);
    }
    expect(MarketingSuggestion::count())->toBe(10);
    $seo = MarketingSuggestion::where('type', 'seo')->first();
    expect($seo->target_snapshot)->toBe(['meta_title' => ['ar' => null], 'meta_description' => ['ar' => null]])->and($seo->target_snapshot_hash)->not->toBeNull()
        ->and($seo->source_meta['tool'])->toBe('create_seo_suggestion')->and($seo->revisions()->count())->toBe(1);
    // normalisation: XSS stripped, colours lowercased, link stored site-relative, coupon code uppercased, hashtags prefixed
    expect(MarketingSuggestion::where('type', 'blog')->first()->payload['content']['ar'])->toBe('<p>نص</p>')
        ->and(MarketingSuggestion::where('type', 'seasonal_theme')->first()->payload['tokens'])->toBe(['color-accent' => '#d4a017', 'color-brand-600' => '#1e5b4f'])
        ->and(MarketingSuggestion::where('type', 'slider')->first()->payload['link_url'])->toBe('/ar/product/alpha')
        ->and(MarketingSuggestion::where('type', 'coupon')->first()->payload['code'])->toBe('SAVE10')
        ->and(MarketingSuggestion::where('type', 'social_content')->first()->payload['hashtags'])->toBe(['#عرض', '#sale']);
});

it('rejects invalid payloads, unknown fields, foreign links and bad targets with 422 in the envelope', function () {
    $p = ($this->product)('alpha');
    $bad = [
        ['seo', ['target_entity_type' => 'product', 'target_entity_id' => $p->id, 'payload' => ['meta_title' => ['ar' => 'x'], 'sql' => 'DROP TABLE products']], 'payload.sql'],
        ['seo', ['target_entity_type' => 'product', 'target_entity_id' => $p->id, 'payload' => ['meta_title' => ['ar' => 'x']], 'table' => 'products'], 'table'],
        ['slider', ['payload' => ['placement' => 'home_hero', 'link_url' => 'https://evil.example.com/x']], 'payload.link_url'],
        ['slider', ['payload' => ['placement' => 'home_hero', 'link_url' => 'javascript:alert(1)']], 'payload.link_url'],
        ['slider', ['payload' => ['placement' => 'home_hero', 'link_url' => '/admin/settings']], 'payload.link_url'],
        ['blog', ['payload' => ['title' => ['ar' => 'x'], 'slug' => 'Bad Slug!']], 'payload.slug'],
        ['seasonal_theme', ['payload' => ['key' => 'x', 'name' => ['ar' => 'x'], 'tokens' => ['--evil' => '#fff']]], 'payload.tokens.--evil'],
        ['seasonal_theme', ['payload' => ['key' => 'x', 'name' => ['ar' => 'x'], 'tokens' => ['color-brand-600' => 'red;}body{display:none']]], 'payload.tokens.color-brand-600'],
        ['coupon', ['payload' => ['code' => 'X1X', 'type' => 'bogus', 'value' => 1]], 'payload.type'],
        ['product', ['target_entity_type' => 'category', 'target_entity_id' => 1, 'payload' => ['name' => ['ar' => 'x']]], 'target_entity_type'],
        ['media_asset', ['target_entity_type' => 'product', 'target_entity_id' => $p->id, 'payload' => ['asset_kind' => 'image', 'brief' => 'x', 'canva_url' => 'https://evil.com/design']], 'payload.canva_url'],
    ];
    foreach ($bad as [$type, $body, $field]) {
        ($this->call)('POST', 'suggestions/'.$type, ($this->base)($body))->assertStatus(422)->assertJsonPath('error', 'validation_failed')->assertJsonValidationErrors([$field]);
    }
    // missing target / unknown target id
    ($this->call)('POST', 'suggestions/seo', ($this->base)(['payload' => ['meta_title' => ['ar' => 'x']]]))->assertStatus(422)->assertJsonPath('error', 'target_not_found');
    ($this->call)('POST', 'suggestions/product', ($this->base)(['target_entity_type' => 'product', 'target_entity_id' => 999999, 'payload' => ['name' => ['ar' => 'x']]]))->assertStatus(422)->assertJsonPath('error', 'target_not_found');
    // business rule: empty change, negative-margin bundle
    ($this->call)('POST', 'suggestions/product', ($this->base)(['target_entity_type' => 'product', 'target_entity_id' => $p->id, 'payload' => []]))->assertStatus(422);
    ($this->call)('POST', 'suggestions/bundle', ($this->base)(['payload' => ['name' => ['ar' => 'b'], 'price' => '10', 'items' => [['product_id' => $p->id, 'quantity' => 1]]]]))
        ->assertStatus(422)->assertJsonValidationErrors(['payload.price']);
    // unknown type ⇒ 404
    ($this->call)('POST', 'suggestions/nonsense', ($this->base)(['payload' => ['x' => 1]]))->assertNotFound();
    expect(MarketingSuggestion::count())->toBe(0);
});

it('prevents duplicates while open and re-creation of a recently rejected suggestion unless a force_reason is given', function () {
    $p = ($this->product)('alpha');
    $body = ($this->base)(['target_entity_type' => 'product', 'target_entity_id' => $p->id, 'payload' => ['name' => ['ar' => 'اسم']]]);
    $id = ($this->call)('POST', 'suggestions/product', $body)->assertCreated()->json('data.id');
    ($this->call)('POST', 'suggestions/product', $body)->assertStatus(409)->assertJsonPath('error', 'duplicate_suggestion')->assertJsonPath('data.existing_id', $id);
    // same payload with other whitespace / key order is the same suggestion
    ($this->call)('POST', 'suggestions/product', ($this->base)(['target_entity_type' => 'product', 'target_entity_id' => $p->id, 'payload' => ['name' => ['ar' => '  اسم ']], 'title' => 'other']))->assertStatus(409);

    app(SuggestionEngine::class)->reject(MarketingSuggestion::find($id), $this->owner, 'no');
    ($this->call)('POST', 'suggestions/product', $body)->assertStatus(409)->assertJsonPath('error', 'recently_rejected');
    ($this->call)('POST', 'suggestions/product', $body + ['force_reason' => 'short'])->assertStatus(422);
    $new = ($this->call)('POST', 'suggestions/product', $body + ['force_reason' => 'Stock doubled since the rejection, new angle.'])->assertCreated()->json('data.id');
    expect(MarketingSuggestion::find($new)->source_meta['overrides_rejected_id'])->toBe($id);
});

it('edits only own pending drafts (new version + revision diff), submits drafts, and refuses edits after approval or by another token', function () {
    $p = ($this->product)('alpha');
    $res = ($this->call)('POST', 'suggestions/seo', ($this->base)(['target_entity_type' => 'product', 'target_entity_id' => $p->id, 'draft' => true,
        'payload' => ['meta_title' => ['ar' => 'قديم']]]))->assertCreated()->assertJsonPath('data.submitted', false);
    $id = $res->json('data.id');
    $hash = $res->json('data.content_hash');

    ($this->call)('PATCH', 'suggestions/'.$id, ['payload' => ['meta_title' => ['ar' => 'جديد']], 'note' => 'better', 'title' => 'Better title'])->assertOk()
        ->assertJsonPath('data.version', 2)->assertJsonPath('data.status', 'pending')->assertJsonPath('data.title', 'Better title');
    $s = MarketingSuggestion::find($id);
    expect($s->content_hash)->not->toBe($hash)->and($s->revisions()->count())->toBe(2)
        ->and(collect($s->revisions()->first()->diff)->pluck('field')->sort()->values()->all())->toBe(['payload', 'title']);
    ($this->call)('PATCH', 'suggestions/'.$id, ['payload' => ['meta_title' => ['ar' => 'x']], 'evil' => 1])->assertStatus(422)->assertJsonValidationErrors(['evil']);

    // another token cannot edit / submit it
    $other = ($this->mkToken)();
    ($this->call)('PATCH', 'suggestions/'.$id, ['title' => 'Hijack'], $other)->assertStatus(403)->assertJsonPath('error', 'not_owner');
    ($this->call)('POST', 'suggestions/'.$id.'/submit', [], $other)->assertStatus(403);

    // a draft cannot be approved before it is submitted
    expect(fn () => app(SuggestionEngine::class)->approve($s->fresh(), $this->owner))->toThrow(\App\Services\Marketing\Suggestions\SuggestionException::class);
    ($this->call)('POST', 'suggestions/'.$id.'/submit')->assertOk()->assertJsonPath('data.submitted', true);
    app(SuggestionEngine::class)->approve($s->fresh(), $this->owner);
    ($this->call)('PATCH', 'suggestions/'.$id, ['title' => 'After approval'])->assertStatus(409)->assertJsonPath('error', 'not_editable');
    ($this->call)('GET', 'suggestions/'.$id.'/status')->assertOk()->assertJsonPath('data.status', 'approved')->assertJsonPath('data.approved', true);
});

it('lists, filters and shows suggestions with status and result (marketing:read)', function () {
    $p = ($this->product)('alpha');
    $c = Category::create(['name' => ['en' => 'Cat'], 'slug' => 'cat', 'path' => '', 'depth' => 0, 'is_active' => true]);
    ($this->call)('POST', 'suggestions/seo', ($this->base)(['target_entity_type' => 'category', 'target_entity_id' => $c->id, 'payload' => ['meta_title' => ['ar' => 'فئة']]]))->assertCreated();
    ($this->call)('POST', 'suggestions/product', ($this->base)(['title' => 'Name tweak', 'target_entity_type' => 'product', 'target_entity_id' => $p->id, 'payload' => ['name' => ['ar' => 'اسم']]]))->assertCreated();
    ($this->call)('GET', 'suggestions')->assertOk()->assertJsonPath('meta.total', 2)->assertJsonCount(2, 'data');
    ($this->call)('GET', 'suggestions?type=seo')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.target.type', 'category');
    ($this->call)('GET', 'suggestions?search=tweak&mine=1')->assertOk()->assertJsonPath('meta.total', 1);
    ($this->call)('GET', 'suggestions?status=approved')->assertOk()->assertJsonPath('meta.total', 0);
    ($this->call)('GET', 'suggestions?status=bogus')->assertStatus(422);
    $id = MarketingSuggestion::where('type', 'product')->value('id');
    ($this->call)('GET', 'suggestions/'.$id)->assertOk()->assertJsonPath('data.payload.name.ar', 'اسم')->assertJsonPath('data.compare.0.proposed', 'اسم')
        ->assertJsonPath('data.compare.0.current', 'صنف alpha')->assertJsonStructure(['data' => ['revisions', 'validation' => ['errors', 'warnings']]]);
    ($this->call)('GET', 'suggestions/'.$id.'/result')->assertOk()->assertJsonPath('data.executions', []);
    ($this->call)('GET', 'suggestions/999999')->assertNotFound();
    // read-only token: can list, cannot write
    $ro = ($this->mkToken)(['marketing:read']);
    ($this->call)('GET', 'suggestions', [], $ro)->assertOk();
    ($this->call)('POST', 'suggestions/product', ($this->base)(['target_entity_type' => 'product', 'target_entity_id' => $p->id, 'payload' => ['name' => ['ar' => 'z']]]), $ro)
        ->assertForbidden()->assertJsonPath('error', 'forbidden_scope');
});

it('has no approve / reject / apply / rollback endpoints: the API can never change a suggestion decision', function () {
    $p = ($this->product)('alpha');
    $id = ($this->call)('POST', 'suggestions/product', ($this->base)(['target_entity_type' => 'product', 'target_entity_id' => $p->id, 'payload' => ['name' => ['ar' => 'اسم']]]))->json('data.id');
    foreach (['approve', 'reject', 'apply', 'rollback', 'archive', 'publish'] as $action) {
        foreach (['POST', 'PATCH', 'PUT'] as $m) {
            expect(in_array(($this->call)($m, 'suggestions/'.$id.'/'.$action, ['note' => 'ok'])->status(), [404, 405], true))->toBeTrue("$m $action");
        }
    }
    // a PATCH cannot sneak a status / approval field in either
    ($this->call)('PATCH', 'suggestions/'.$id, ['status' => 'approved'])->assertStatus(422)->assertJsonValidationErrors(['status']);
    ($this->call)('PATCH', 'suggestions/'.$id, ['approved_by' => 1, 'version' => 9])->assertStatus(422);
    expect(MarketingSuggestion::find($id)->status->value)->toBe('pending')->and(MarketingSuggestion::find($id)->approvals()->count())->toBe(0);
    foreach (app('router')->getRoutes() as $r) {
        if (str_starts_with($r->uri(), 'api/')) {
            expect($r->uri())->not->toMatch('/suggestions.*(approve|apply|reject|rollback)/');
        }
    }
});

it('patches common fields without resending the payload (type-required payload keys apply only when a payload is sent)', function () {
    $id = ($this->call)('POST', 'suggestions/blog', ($this->base)(['payload' => ['title' => ['ar' => 'مقال'], 'slug' => 'maqal-1']]))->assertCreated()->json('data.id');
    ($this->call)('PATCH', 'suggestions/'.$id, ['title' => 'Renamed suggestion', 'priority' => 'urgent'])->assertOk()->assertJsonPath('data.title', 'Renamed suggestion')
        ->assertJsonPath('data.priority', 'urgent')->assertJsonPath('data.payload.slug', 'maqal-1');
    ($this->call)('PATCH', 'suggestions/'.$id, ['payload' => ['slug' => 'no-title']])->assertStatus(422)->assertJsonValidationErrors(['payload.title']);
});
