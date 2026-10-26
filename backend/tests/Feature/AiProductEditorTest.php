<?php

use App\Enums\AiDriverType;
use App\Enums\AiGenerationStatus;
use App\Models\AiGeneration;
use App\Models\AiModel;
use App\Models\AiProvider;
use App\Models\AiTenantSetting;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\TenantAdmin;
use App\Models\TenantProduct;
use Database\Seeders\FoundationSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function () {
    $this->seed(FoundationSeeder::class);
    Cache::flush();
    RateLimiter::clear('ai:1');
    $this->tenant = Tenant::where('slug', 'egy')->first();
    $this->other = Tenant::firstOrCreate(['slug' => 'sa'], ['name' => 'KSA', 'country' => 'SA', 'currency_code' => 'SAR', 'currency_symbol' => 'SAR', 'currency_rate' => 1, 'default_locale' => 'ar', 'locales' => ['ar', 'en'], 'timezone' => 'Asia/Riyadh', 'is_active' => true]);
    app()->instance('currentTenant', $this->tenant);
    $p = AiProvider::create(['driver' => AiDriverType::OPENAI, 'name' => 'oa-editor', 'api_key' => 'sk-test-1234567', 'is_active' => true]);
    $this->text = AiModel::create(['ai_provider_id' => $p->id, 'model_id' => 'gpt-ed', 'label' => 'T', 'kind' => 'text', 'capabilities' => ['json_mode' => true], 'is_active' => true]);
    AiTenantSetting::withoutGlobalScopes()->updateOrCreate(['tenant_id' => $this->tenant->id], ['ai_enabled' => true, 'features' => ['editor' => true], 'allowed_model_ids' => [$this->text->id],
        'default_ai_model_id' => $this->text->id, 'monthly_token_quota' => null, 'brand_voice' => null, 'forbidden_words' => ['cheap']]);
    app(\App\Services\Ai\AiAccess::class)->forget();
    $this->owner = fn () => $this->actingAs(TenantAdmin::withoutGlobalScopes()->where('email', 'owner@egy.test')->first(), 'tenant');
    $this->base = 'http://egy.3alraf.test/admin/ai/editor';
    $this->product = function (Tenant $t, string $slug) {
        $pr = Product::create(['type' => 'simple', 'name' => ['en' => 'Gold Watch', 'ar' => 'ساعة ذهبية'], 'description' => ['en' => 'Nice'], 'slug' => $slug, 'sku' => strtoupper($slug), 'barcode' => app(\App\Services\BarcodeService::class)->generateUnique()]);
        TenantProduct::create(['tenant_id' => $t->id, 'product_id' => $pr->id, 'price' => '99.00', 'cost' => '20', 'is_active' => true]);

        return $pr;
    };
    $this->llm = fn (array $fields) => ['choices' => [['message' => ['content' => json_encode(['fields' => $fields])], 'finish_reason' => 'stop']], 'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 50]];
    $this->run = function (Product $pr, array $fields, array $out, array $o = []) {
        Http::fake(['*' => Http::response(($this->llm)($out))]);

        return ($this->owner)()->postJson($this->base.'/generate', $o + ['product_ids' => [$pr->id], 'fields' => $fields, 'locales' => ['en', 'ar'], 'preset' => 'seo']);
    };
});

it('creates per-field proposals, sanitises html and removes forbidden words', function () {
    $pr = ($this->product)($this->tenant, 'w-1');
    $r = ($this->run)($pr, ['name', 'description'], [
        'name' => ['en' => 'Cheap <b>Premium</b> Watch', 'ar' => 'ساعة ذهبية'],
        'description' => ['en' => '<p onclick="x()">Great</p><script>alert(1)</script><a href="javascript:alert(1)">l</a><img src=x onerror=alert(1)>', 'ar' => 'وصف'],
    ])->assertCreated();
    $g = AiGeneration::find($r->json('data.0.id'));
    expect($g->status)->toBe(AiGenerationStatus::DRAFT)
        ->and($g->output['proposals']['name']['en']['new'])->toBe('Premium Watch')
        ->and($g->output['proposals']['name'])->not->toHaveKey('ar')   // unchanged -> nothing to review
        ->and($g->output['proposals']['description']['en']['new'])->toBe('<p>Great</p>l')
        ->and($g->output['warnings'])->not->toBeEmpty();
});

it('applies only accepted fields into the tenant overlay, never the central product, and undoes via the audit log', function () {
    $pr = ($this->product)($this->tenant, 'w-2');
    $id = ($this->run)($pr, ['name', 'description'], ['name' => ['en' => 'Premium Watch', 'ar' => 'ساعة فاخرة'], 'description' => ['en' => '<p>Better</p>']])->json('data.0.id');
    ($this->owner)()->postJson("{$this->base}/generations/$id/apply", ['accept' => ['name.en']])->assertOk()->assertJsonPath('data.status', 'approved')->assertJsonPath('data.can_undo', true);
    $tp = TenantProduct::where('product_id', $pr->id)->first();
    expect($tp->content)->toBe(['name' => ['en' => 'Premium Watch']])->and($pr->fresh()->getTranslation('name', 'en'))->toBe('Gold Watch')
        ->and($pr->fresh()->getTranslation('description', 'en'))->toBe('Nice');
    // second apply refused (already reviewed)
    ($this->owner)()->postJson("{$this->base}/generations/$id/apply", ['accept' => ['name.ar']])->assertStatus(422);
    // storefront overlay is in-memory only
    expect(TenantProduct::with('product')->find($tp->id)->applyContent()->product->getTranslation('name', 'en'))->toBe('Premium Watch');

    ($this->owner)()->postJson("{$this->base}/generations/$id/undo")->assertOk()->assertJsonPath('data.can_undo', false);
    expect(TenantProduct::find($tp->id)->content)->toBeNull();
});

it('refuses undo when the listing text changed after the edit', function () {
    $pr = ($this->product)($this->tenant, 'w-3');
    $id = ($this->run)($pr, ['name'], ['name' => ['en' => 'Premium Watch']])->json('data.0.id');
    ($this->owner)()->postJson("{$this->base}/generations/$id/apply", ['accept' => ['name.en']])->assertOk();
    $tp = TenantProduct::where('product_id', $pr->id)->first();
    app(\App\Services\TenantCatalogService::class)->updateContent($tp, ['name' => ['en' => 'Manual']]);
    ($this->owner)()->postJson("{$this->base}/generations/$id/undo")->assertStatus(422)->assertJsonPath('reason', 'conflict');
});

it('validates fields, locales and accept keys', function () {
    $pr = ($this->product)($this->tenant, 'w-4');
    ($this->owner)()->postJson($this->base.'/generate', ['product_ids' => [$pr->id], 'fields' => ['sku'], 'locales' => ['en'], 'preset' => 'seo'])->assertStatus(422)->assertJsonValidationErrors('fields.0');
    ($this->owner)()->postJson($this->base.'/generate', ['product_ids' => [$pr->id], 'fields' => ['name'], 'locales' => ['fr'], 'preset' => 'seo'])->assertStatus(422)->assertJsonValidationErrors('locales.0');
    ($this->owner)()->postJson($this->base.'/generate', ['product_ids' => [$pr->id], 'fields' => ['name'], 'locales' => ['en'], 'preset' => 'custom'])->assertStatus(422)->assertJsonValidationErrors('instruction');
    $id = ($this->run)($pr, ['name'], ['name' => ['en' => 'Premium Watch']])->json('data.0.id');
    ($this->owner)()->postJson("{$this->base}/generations/$id/apply", ['accept' => ['price.en']])->assertStatus(422);
    ($this->owner)()->postJson("{$this->base}/generations/$id/apply", ['accept' => ['name.fr']])->assertStatus(422);
    ($this->owner)()->postJson("{$this->base}/generations/$id/apply", ['accept' => ['name.ar']])->assertStatus(422);   // not proposed
    expect(TenantProduct::where('product_id', $pr->id)->first()->content)->toBeNull();
});

it('rejects a proposal without touching the product', function () {
    $pr = ($this->product)($this->tenant, 'w-5');
    $id = ($this->run)($pr, ['name'], ['name' => ['en' => 'Premium Watch']])->json('data.0.id');
    ($this->owner)()->postJson("{$this->base}/generations/$id/reject")->assertOk()->assertJsonPath('data.status', 'rejected');
    expect(TenantProduct::where('product_id', $pr->id)->first()->content)->toBeNull();
});

it('queues one generation per product in bulk', function () {
    $a = ($this->product)($this->tenant, 'b-1');
    $b = ($this->product)($this->tenant, 'b-2');
    Http::fake(['*' => Http::response(($this->llm)(['name' => ['en' => 'Premium Watch']]))]);
    $r = ($this->owner)()->postJson($this->base.'/generate', ['product_ids' => [$a->id, $b->id], 'fields' => ['name'], 'locales' => ['en'], 'preset' => 'seo'])->assertCreated();
    expect($r->json('data'))->toHaveCount(2)->and($r->json('data.0.bulk_id'))->not->toBeNull()->and($r->json('data.0.bulk_id'))->toBe($r->json('data.1.bulk_id'));
});

it('isolates tenants: other tenant products and generations are invisible', function () {
    $mine = ($this->product)($this->tenant, 'i-1');
    $theirs = ($this->product)($this->other, 'i-2');
    ($this->owner)()->postJson($this->base.'/generate', ['product_ids' => [$theirs->id], 'fields' => ['name'], 'locales' => ['en'], 'preset' => 'seo'])->assertStatus(422)->assertJsonValidationErrors('product_ids.0');
    $id = ($this->run)($mine, ['name'], ['name' => ['en' => 'Premium Watch']])->json('data.0.id');
    app()->instance('currentTenant', $this->other);
    expect(AiGeneration::find($id))->toBeNull();
    app()->instance('currentTenant', $this->tenant);
    $names = collect(($this->owner)()->getJson($this->base.'/products?q=Gold')->json('data'))->pluck('id');
    expect($names)->toContain($mine->id)->not->toContain($theirs->id);
});

it('needs the editor permission and feature', function () {
    $this->actingAs(TenantAdmin::withoutGlobalScopes()->where('email', 'owner@egy.test')->first(), 'tenant')->get($this->base)->assertOk();
    AiTenantSetting::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->update(['features' => ['editor' => false]]);
    app(\App\Services\Ai\AiAccess::class)->forget();
    ($this->owner)()->get($this->base)->assertForbidden();
});
