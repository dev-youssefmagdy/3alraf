<?php

use App\Enums\AiDriverType;
use App\Enums\AiGenerationStatus;
use App\Enums\StockMovementType as T;
use App\Models\AiGeneration;
use App\Models\AiModel;
use App\Models\AiProvider;
use App\Models\AiTenantSetting;
use App\Models\BlogPost;
use App\Models\CustomPage;
use App\Models\StaticPage;
use App\Models\Banner;
use App\Models\Branch;
use App\Models\Bundle;
use App\Models\DiscountCode;
use App\Models\FlashSale;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\TenantAdmin;
use App\Models\TenantProduct;
use App\Services\Ai\GeneratorService;
use App\Services\Stock\StockLedger;
use Database\Seeders\FoundationSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use App\Models\ApprovedProductClaim;
use App\Models\BrandMemoryEntry;
use App\Models\ContentAsset;
use App\Models\ContentCalendarItem;
use App\Services\Growth\BrandMemoryService;
use App\Services\Growth\GrowthAccess;
use App\Services\Growth\TruthGuardService;

use App\Services\Growth\IntegrationClientService;
use App\Services\Growth\SignatureService;

function ncClient(array $scopes = ['read'], ?string $webhook = null, ?Tenant $tenant = null): array
    {
        $tenant ??= current_tenant();
        $prev = current_tenant();
        app()->instance('currentTenant', $tenant);
        $r = app(IntegrationClientService::class)->create('n8n', $scopes, $webhook, null);
        app()->instance('currentTenant', $prev);

        return $r;
    }

    function ncCall($test, string $method, string $path, ?array $body, string $token, string $secret, array $headers = [], ?int $ts = null)
    {
        $raw = $body === null ? '' : json_encode($body);
        $ts ??= time();
        $h = ['Authorization' => 'Bearer '.$token, 'X-Timestamp' => (string) $ts, 'X-Signature' => app(SignatureService::class)->sign($secret, $ts, $raw), 'Accept' => 'application/json', 'CONTENT_TYPE' => 'application/json'] + $headers;
        $server = collect($h)->mapWithKeys(fn ($v, $k) => [$k === 'CONTENT_TYPE' ? $k : 'HTTP_'.strtoupper(str_replace('-', '_', $k)) => $v])->all();

        return $test->call($method, 'http://central.3alraf.test/api/integration/v1/'.$path, [], [], [], $server, $raw);
    }

beforeEach(function () {
    $this->seed(FoundationSeeder::class);
    Cache::flush();
    RateLimiter::clear('ai:1');
    $this->tenant = Tenant::where('slug', 'egy')->first();
    $this->other = Tenant::firstOrCreate(['slug' => 'sa'], ['name' => 'KSA', 'country' => 'SA', 'currency_code' => 'SAR', 'currency_symbol' => 'SAR', 'currency_rate' => 1, 'default_locale' => 'ar', 'locales' => ['ar', 'en'], 'timezone' => 'Asia/Riyadh', 'is_active' => true]);
    app()->instance('currentTenant', $this->tenant);
    app(GrowthAccess::class)->set($this->tenant, true);
    app(GrowthAccess::class)->set($this->other, true);
    app()->instance('currentTenant', $this->tenant->refresh());
    $p = AiProvider::create(['driver' => AiDriverType::OPENAI, 'name' => 'oa-gen', 'api_key' => 'sk-test-1234567', 'is_active' => true]);
    $this->text = AiModel::create(['ai_provider_id' => $p->id, 'model_id' => 'gpt-gen', 'label' => 'T', 'kind' => 'text', 'capabilities' => ['json_mode' => true], 'is_active' => true]);
    $this->image = AiModel::create(['ai_provider_id' => $p->id, 'model_id' => 'img-gen', 'label' => 'I', 'kind' => 'image', 'is_active' => true]);
    AiTenantSetting::withoutGlobalScopes()->updateOrCreate(['tenant_id' => $this->tenant->id], ['ai_enabled' => true, 'features' => ['generators' => true], 'allowed_model_ids' => [$this->text->id, $this->image->id],
        'default_ai_model_id' => $this->text->id, 'monthly_token_quota' => null, 'monthly_image_quota' => null, 'brand_voice' => null, 'forbidden_words' => []]);
    app(\App\Services\Ai\AiAccess::class)->forget();
    $this->owner = fn () => $this->actingAs(TenantAdmin::withoutGlobalScopes()->where('email', 'owner@egy.test')->first(), 'tenant');
    $this->base = 'http://egy.3alraf.test/admin/ai/generators';
    $this->product = function (string $slug, string $price = '100.00', string $cost = '50', int $stock = 20) {
        $pr = Product::create(['type' => 'simple', 'name' => ['en' => 'Item '.$slug, 'ar' => 'صنف '.$slug], 'description' => ['en' => 'x'], 'slug' => $slug, 'sku' => strtoupper($slug), 'barcode' => app(\App\Services\BarcodeService::class)->generateUnique()]);
        TenantProduct::create(['tenant_id' => $this->tenant->id, 'product_id' => $pr->id, 'price' => $price, 'cost' => $cost, 'is_active' => true]);
        if ($stock > 0) {
            app(StockLedger::class)->record(Branch::first()->id, $pr->id, 0, T::PURCHASE_IN, $stock, $cost);
        }

        return $pr;
    };
    $this->llm = fn (array $draft) => ['choices' => [['message' => ['content' => json_encode($draft)], 'finish_reason' => 'stop']], 'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 50]];
    $this->gen = function (string $kind, array $draft, array $o = []) {
        Http::swap(new \Illuminate\Http\Client\Factory);   // fakes stack: the first match wins, so start clean
        Http::fake(['*' => Http::response(($this->llm)($draft))]);

        return ($this->owner)()->postJson($this->base.'/generate', $o + ['kind' => $kind, 'goal' => 'Sell more', 'margin_floor' => 10])->assertCreated();
    };
    $this->start = now()->addDay()->format('Y-m-d H:i');
    $this->end = now()->addDays(5)->format('Y-m-d H:i');
});



function pkgDraft(int $pid, array $o = []): array
{
    return $o + ['short_description' => ['en' => 'Gentle aloe cleanser', 'ar' => 'غسول لطيف بالصبار'], 'long_description' => ['en' => 'A daily cleanser. Cures acne in 3 days. Contains aloe vera.', 'ar' => 'غسول يومي'],
        'angles' => [['name' => 'Daily', 'pitch' => 'Simple routine'], ['name' => 'Gift', 'pitch' => 'Nice gift'], ['name' => 'Value', 'pitch' => 'Good price']],
        'hooks' => ['Stop scrolling', '100% original guaranteed product'], 'reel_scripts' => ['Show the bottle'], 'tiktok_scripts' => ['Quick demo'], 'stories' => ['Poll'], 'ad_copy' => ['Fresh skin'], 'ctas' => ['Order now'],
        'faq' => [['q' => 'How to use?', 'a' => 'Twice daily.']], 'bundle_ideas' => ['With toner'], 'photo_briefs' => ['Shoot on white'], 'ab_variants' => [['label' => 'A', 'text' => 'Variant A']]];
}

function claimProduct($t, string $slug = 'aloe'): Product
{
    $p = ($t->product)($slug);
    $p->update(['description' => ['en' => 'Gentle daily cleanser with aloe vera.']]);
    app(BrandMemoryService::class)->addClaim($p->id, 'ingredient', 'Contains aloe vera', 1);

    return $p;
}

it('truth guard removes unsupported claims, keeps supported ones, flags conflicts and prohibited words', function () {
    $p = claimProduct($this);
    $g = app(TruthGuardService::class);
    $r = $g->check('Contains aloe vera. Cures acne in 3 days. 100% original. Great for daily use.', $p->id);
    expect($r['ok'])->toBeFalse()->and($r['text'])->toContain('Contains aloe vera')->toContain('Great for daily use')->not->toContain('Cures')->not->toContain('original')
        ->and(collect($r['removed'])->pluck('type')->all())->toContain('benefit', 'authenticity');
    $ar = $g->check('يعالج حب الشباب نهائيا. منتج أصلي.', $p->id);
    expect($ar['text'])->toBe('')->and($ar['removed'])->toHaveCount(2);
    // a claim from the facts (description) is supported even without an approved claim
    $p->update(['description' => ['en' => 'Natural cleanser, alcohol free']]);
    expect($g->check('A natural cleanser.', $p->id)['ok'])->toBeTrue();
    // conflict: facts state alcohol, text says alcohol free
    $p->update(['description' => ['en' => 'Contains alcohol denat for fast drying']]);
    $c = $g->check('A fresh alcohol-free formula.', $p->id);
    expect($c['flagged'])->toHaveCount(1)->and($c['flagged'][0]['reason'])->toContain('conflict');
    app(BrandMemoryService::class)->save(['kind' => 'prohibited_words', 'title' => 'No', 'body' => 'cheap, bargain'], null, 1);
    expect($g->check('A cheap price.', $p->id)['flagged'][0]['reason'])->toBe('prohibited_word');
});

it('content package: generated unsupported claims are removed, approve creates assets with UTM links once', function () {
    $p = claimProduct($this);
    $r = ($this->gen)('content_package', pkgDraft($p->id), ['kind' => 'content_package', 'product_ids' => [$p->id]]);
    $g = AiGeneration::find($r->json('data.id'));
    expect($g->type->value)->toBe('content_package')->and($g->output['errors'])->toBe([])
        ->and($g->output['draft']['long_description']['en'])->toContain('Contains aloe vera')->not->toContain('Cures')
        ->and($g->output['draft']['hooks'])->toBe(['Stop scrolling'])
        ->and(collect($g->output['warnings'])->pluck('code')->all())->toContain('claims_removed');
    ($this->owner)()->get("{$this->base}/{$g->id}")->assertOk()->assertSee('Stop scrolling');
    ($this->owner)()->get($this->base)->assertOk();
    ($this->owner)()->postJson("{$this->base}/{$g->id}/approve")->assertOk()->assertJsonPath('data.status', 'approved');
    $a = ContentAsset::where('type', 'hook')->first();
    expect(ContentAsset::count())->toBeGreaterThan(10)->and($a->utm_content)->toBe('ca'.$a->id)->and($a->tracked_url)->toContain('utm_content=ca'.$a->id)->toContain('/product/')
        ->and(ContentAsset::where('type', 'angle')->count())->toBe(3);
    $n = ContentAsset::count();
    ($this->owner)()->postJson("{$this->base}/{$g->id}/approve")->assertStatus(422);
    expect(ContentAsset::count())->toBe($n);
    // tenant isolation
    app()->instance('currentTenant', $this->other);
    expect(ContentAsset::count())->toBe(0)->and(ApprovedProductClaim::count())->toBe(0);
});

it('content package: a conflict with product facts blocks approval until edited', function () {
    $p = claimProduct($this, 'aloe2');
    $p->update(['description' => ['en' => 'Contains alcohol denat']]);
    $r = ($this->gen)('content_package', pkgDraft($p->id, ['short_description' => ['en' => 'An alcohol-free cleanser', 'ar' => 'x']]), ['kind' => 'content_package', 'product_ids' => [$p->id]]);
    $g = AiGeneration::find($r->json('data.id'));
    expect(collect($g->output['errors'])->pluck('code')->all())->toContain('truth_conflict');
    ($this->owner)()->postJson("{$this->base}/{$g->id}/approve")->assertStatus(422);
    expect(ContentAsset::count())->toBe(0);
    $fixed = pkgDraft($p->id, ['short_description' => ['en' => 'A gentle cleanser', 'ar' => 'x']]);
    $res = ($this->owner)()->putJson("{$this->base}/{$g->id}", ['draft' => $fixed])->assertOk();
    expect(collect($res->json('data.errors')))->toBeEmpty();
    ($this->owner)()->postJson("{$this->base}/{$g->id}/approve")->assertOk();
});

it('content package needs the growth flag, growth.manage and exactly one product', function () {
    $p = claimProduct($this, 'aloe3');
    $body = ['kind' => 'content_package', 'goal' => 'x', 'product_ids' => [$p->id]];
    ($this->owner)()->postJson($this->base.'/generate', array_merge($body, ['product_ids' => []]))->assertStatus(422);
    app(GrowthAccess::class)->set($this->tenant, false);
    app()->instance('currentTenant', $this->tenant->refresh());
    ($this->owner)()->postJson($this->base.'/generate', $body)->assertStatus(422);
    expect(AiGeneration::count())->toBe(0);
});

it('truth guard also runs on other generators when products are attached (growth tenants)', function () {
    $p = claimProduct($this, 'aloe4');
    $r = ($this->gen)('blog_post', ['slug' => 'aloe-post', 'title' => ['en' => 'Aloe'], 'content' => ['en' => '<p>A daily cleanser. It cures acne in 3 days.</p>']], ['kind' => 'blog_post', 'product_ids' => [$p->id]]);
    $g = AiGeneration::find($r->json('data.id'));
    expect($g->output['draft']['content']['en'])->not->toContain('cures')->and(collect($g->output['warnings'])->pluck('code')->all())->toContain('claims_removed');
});

it('brand memory: edits are versioned, API is tenant scoped and read-only, truth check endpoint works', function () {
    $p = claimProduct($this, 'aloe5');
    $e = app(BrandMemoryService::class)->save(['kind' => 'voice', 'title' => 'Voice', 'body' => 'Warm, Egyptian'], null, 1);
    app(BrandMemoryService::class)->save(['title' => 'Voice', 'body' => 'Warm and short'], $e->id, 1);
    expect($e->fresh()->version)->toBe(2)->and(app(BrandMemoryService::class)->versions($e->id)->first()->body)->toBe('Warm, Egyptian');
    [, $tok, $sec] = ncClient(['read']);
    $res = ncCall($this, 'GET', 'brand-memory?product_id='.$p->id, null, $tok, $sec)->assertOk();
    expect($res->json('data.entries.0.body'))->toBe('Warm and short')->and($res->json('data.approved_claims.0.claim'))->toBe('Contains aloe vera');
    ncCall($this, 'POST', 'truth-guard/check', ['text' => 'Cures acne fast. Nice bottle.', 'product_id' => $p->id], $tok, $sec)->assertOk()->assertJsonPath('data.ok', false)->assertJsonPath('data.removed.0.type', 'benefit');
    // another tenant's client sees nothing of ours and cannot query our product
    [, $t2, $s2] = ncClient(['read'], null, $this->other);
    expect(ncCall($this, 'GET', 'brand-memory', null, $t2, $s2)->assertOk()->json('data.entries'))->toBe([]);
    ncCall($this, 'GET', 'brand-memory?product_id='.$p->id, null, $t2, $s2)->assertNotFound();
    // facts endpoint carries approved claims
    expect(ncCall($this, 'GET', 'products/'.$p->id.'/facts', null, $tok, $sec)->json('data.approved_claims.0.claim'))->toBe('Contains aloe vera');
    // admin UI renders
    ($this->owner)()->get('http://egy.3alraf.test/admin/growth/brand-memory')->assertOk()->assertSee('Voice');
    ($this->owner)()->get('http://egy.3alraf.test/admin/growth/content')->assertOk();
});

it('content calendar: schedule, manual publish is idempotent, other tenant assets rejected', function () {
    $p = claimProduct($this, 'aloe6');
    $r = ($this->gen)('content_package', pkgDraft($p->id), ['kind' => 'content_package', 'product_ids' => [$p->id]]);
    ($this->owner)()->postJson("{$this->base}/{$r->json('data.id')}/approve")->assertOk();
    $asset = ContentAsset::where('type', 'story')->first();
    $url = 'http://egy.3alraf.test/admin/growth/content';
    ($this->owner)()->postJson("$url/schedule", ['content_asset_id' => $asset->id, 'channel' => 'instagram', 'scheduled_for' => now()->addDay()->format('Y-m-d H:i')])->assertOk();
    $item = ContentCalendarItem::first();
    ($this->owner)()->postJson("$url/{$item->id}/publish")->assertOk();
    $at = $item->fresh()->published_at;
    ($this->owner)()->postJson("$url/{$item->id}/publish")->assertOk();
    expect($item->fresh()->status)->toBe('published')->and($item->fresh()->published_at->eq($at))->toBeTrue()->and($asset->fresh()->status)->toBe('published');
    ($this->owner)()->postJson("$url/schedule", ['content_asset_id' => 999999, 'channel' => 'instagram', 'scheduled_for' => now()->addDay()->format('Y-m-d H:i')])->assertStatus(422);
    app()->instance('currentTenant', $this->other);
    expect(ContentCalendarItem::count())->toBe(0);
});
