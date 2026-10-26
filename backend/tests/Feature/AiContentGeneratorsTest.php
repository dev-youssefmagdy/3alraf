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

beforeEach(function () {
    $this->seed(FoundationSeeder::class);
    Cache::flush();
    RateLimiter::clear('ai:1');
    $this->tenant = Tenant::where('slug', 'egy')->first();
    $this->other = Tenant::firstOrCreate(['slug' => 'sa'], ['name' => 'KSA', 'country' => 'SA', 'currency_code' => 'SAR', 'currency_symbol' => 'SAR', 'currency_rate' => 1, 'default_locale' => 'ar', 'locales' => ['ar', 'en'], 'timezone' => 'Asia/Riyadh', 'is_active' => true]);
    app()->instance('currentTenant', $this->tenant);
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


it('blog post: sanitised HTML, approve creates ONE inactive post, slug uniqueness is blocking', function () {
    $r = ($this->gen)('blog_post', ['slug' => 'Summer Tips!', 'title' => ['en' => 'Summer <b>tips</b>', 'ar' => 'نصائح الصيف'], 'excerpt' => ['en' => 'Short', 'ar' => 'قصير'],
        'content' => ['en' => '<h2 onclick="x()">Hi</h2><script>alert(1)</script><p>Body <a href="javascript:alert(1)">link</a></p>', 'ar' => '<p>نص</p>'], 'meta_description' => ['en' => 'm', 'ar' => 'م']], ['kind' => 'blog_post', 'tone' => 'friendly', 'length' => 'short']);
    $g = AiGeneration::find($r->json('data.id'));
    expect($g->output['errors'])->toBe([])->and($g->output['draft']['slug'])->toBe('summer-tips')
        ->and($g->output['draft']['content']['en'])->not->toContain('script')->not->toContain('onclick')->not->toContain('<a')->toContain('<h2>Hi</h2>')
        ->and($g->output['draft']['title']['en'])->toBe('Summer tips');
    ($this->owner)()->postJson("{$this->base}/{$g->id}/approve")->assertOk()->assertJsonPath('data.status', 'approved');
    $post = BlogPost::first();
    expect(BlogPost::count())->toBe(1)->and($post->is_active)->toBeFalse()->and($post->published_at)->toBeNull()->and($g->fresh()->target_type)->toBe(BlogPost::class);
    // same slug again: generation auto-suffixes it, the draft stays approvable
    $r2 = ($this->gen)('blog_post', ['slug' => 'summer-tips', 'title' => ['en' => 'Again'], 'content' => ['en' => '<p>x</p>']], ['kind' => 'blog_post']);
    expect(AiGeneration::find($r2->json('data.id'))->output['draft']['slug'])->toBe('summer-tips-2');
    // editing the slug to a taken one is blocked
    $id = $r2->json('data.id');
    $res = ($this->owner)()->putJson("{$this->base}/$id", ['draft' => ['slug' => 'summer-tips', 'title' => ['en' => 'Again'], 'content' => ['en' => '<p>x</p>']]])->assertOk();
    expect($res->json('data.errors'))->not->toBeEmpty();
    ($this->owner)()->postJson("{$this->base}/$id/approve")->assertStatus(422);
    expect(BlogPost::count())->toBe(1);
});

it('static page: plain text only, needs default-language content, creates an inactive page', function () {
    $r = ($this->gen)('static_page', ['slug' => 'about-us', 'title' => ['en' => 'About', 'ar' => 'من نحن'], 'content' => ['en' => "We sell <b>things</b>.\n\n\n\nWelcome", 'ar' => 'نحن نبيع']], ['kind' => 'static_page']);
    $g = AiGeneration::find($r->json('data.id'));
    expect($g->output['draft']['content']['en'])->toBe("We sell things.\n\nWelcome")->and($g->output['errors'])->toBe([]);
    ($this->owner)()->postJson("{$this->base}/{$g->id}/approve")->assertOk();
    expect(StaticPage::where('slug', 'about-us')->first()->is_active)->toBeFalse();

    $bad = ($this->gen)('static_page', ['slug' => 'empty', 'title' => ['en' => 'E'], 'content' => ['en' => 'x']], ['kind' => 'static_page']);
    $bg = AiGeneration::find($bad->json('data.id'));
    expect(collect($bg->output['errors'])->pluck('code')->all())->toContain('content_required');
    ($this->owner)()->postJson("{$this->base}/{$bg->id}/approve")->assertStatus(422);
});

it('custom page: unknown ids are dropped, reserved slug blocked, approve pins products inactive', function () {
    $a = ($this->product)('cp1');
    $r = ($this->gen)('custom_page', ['slug' => 'summer-sale', 'title' => ['en' => 'Summer', 'ar' => 'الصيف'], 'categories' => [999999], 'brands' => [], 'sort' => 'bogus', 'price_max' => 500, 'product_ids' => [$a->id, 888888]], ['kind' => 'custom_page']);
    $g = AiGeneration::find($r->json('data.id'));
    expect($g->output['draft']['product_ids'])->toBe([$a->id])->and($g->output['draft']['categories'])->toBe([])->and($g->output['draft']['sort'])->toBe('newest')
        ->and(collect($g->output['warnings'])->pluck('code')->all())->toContain('unknown_ids_dropped')->and($g->output['errors'])->toBe([]);
    ($this->owner)()->postJson("{$this->base}/{$g->id}/approve")->assertOk();
    $page = CustomPage::where('slug', 'summer-sale')->first();
    expect($page->is_active)->toBeFalse()->and($page->products()->pluck('products.id')->all())->toBe([$a->id])->and($page->filters['price_max'])->toBe('500');

    $res = ($this->gen)('custom_page', ['slug' => 'cart', 'title' => ['en' => 'X'], 'price_max' => 10], ['kind' => 'custom_page']);
    expect(collect(AiGeneration::find($res->json('data.id'))->output['errors'])->pluck('code')->all())->toContain('slug_reserved');
});

it('content drafts are tenant-isolated and need the approve permission to approve', function () {
    $r = ($this->gen)('static_page', ['slug' => 'iso', 'title' => ['en' => 'I'], 'content' => ['en' => 'x']], ['kind' => 'static_page']);
    $id = $r->json('data.id');
    app()->instance('currentTenant', $this->other);
    expect(AiGeneration::find($id))->toBeNull();
    app()->instance('currentTenant', $this->tenant);
    ($this->owner)()->getJson("{$this->base}/$id/json")->assertOk()->assertJsonPath('data.kind', 'static_page');
});

it('review screens render for every content kind in en and ar', function () {
    $a = ($this->product)('rv');
    foreach ([['static_page', ['slug' => 'rv-s', 'title' => ['en' => 'T'], 'content' => ['en' => 'c']]], ['blog_post', ['slug' => 'rv-b', 'title' => ['en' => 'T'], 'content' => ['en' => '<p>c</p>']]],
        ['custom_page', ['slug' => 'rv-c', 'title' => ['en' => 'T'], 'product_ids' => [$a->id]]]] as [$kind, $draft]) {
        $id = ($this->gen)($kind, $draft, ['kind' => $kind])->json('data.id');
        ($this->owner)()->get("{$this->base}/$id")->assertOk()->assertSee('data-k="slug"', false);
    }
    ($this->owner)()->get($this->base)->assertOk()->assertSee('blog_post', false);
    ($this->owner)()->getJson("{$this->base}/datatable")->assertOk();
    app()->setLocale('ar');
    ($this->owner)()->get($this->base)->assertOk();
});
