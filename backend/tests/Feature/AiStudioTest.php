<?php

use App\Enums\AiDriverType;
use App\Enums\SocialPlatform;
use App\Exceptions\AiException;
use App\Jobs\RunAiGenerationJob;
use App\Models\AiGeneration;
use App\Models\AiModel;
use App\Models\AiProvider;
use App\Models\AiTenantSetting;
use App\Models\AiUsageLog;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\TenantAdmin;
use App\Models\TenantProduct;
use App\Services\Ai\AiGateway;
use App\Services\Ai\Studio\MarketingCalendar;
use App\Services\Ai\StudioService;
use App\Services\Ai\Support\AiResponse;
use Carbon\CarbonImmutable;
use Database\Seeders\FoundationSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;

const PNG_B64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

beforeEach(function () {
    $this->seed(FoundationSeeder::class);
    Cache::flush();
    RateLimiter::clear('ai:1');
    $this->tenant = Tenant::where('slug', 'egy')->first();
    $this->other = Tenant::firstOrCreate(['slug' => 'sa'], ['name' => 'KSA', 'country' => 'SA', 'currency_code' => 'SAR', 'currency_symbol' => 'SAR', 'currency_rate' => 1, 'default_locale' => 'ar', 'locales' => ['ar', 'en'], 'timezone' => 'Asia/Riyadh', 'is_active' => true]);
    app()->instance('currentTenant', $this->tenant);
    $p = AiProvider::create(['driver' => AiDriverType::OPENAI, 'name' => 'oa-studio', 'api_key' => 'sk-test-1234567', 'is_active' => true]);
    $this->text = AiModel::create(['ai_provider_id' => $p->id, 'model_id' => 'gpt-st', 'label' => 'T', 'kind' => 'text', 'capabilities' => ['json_mode' => true], 'is_active' => true]);
    $this->image = AiModel::create(['ai_provider_id' => $p->id, 'model_id' => 'img-st', 'label' => 'I', 'kind' => 'image', 'is_active' => true]);
    $this->setup = fn (array $extra = [], ?array $allowed = null) => tap(AiTenantSetting::withoutGlobalScopes()->updateOrCreate(['tenant_id' => $this->tenant->id], $extra + ['ai_enabled' => true, 'features' => ['studio' => true],
        'allowed_model_ids' => $allowed ?? [$this->text->id, $this->image->id], 'default_ai_model_id' => $this->text->id, 'monthly_image_quota' => null, 'monthly_video_quota' => null, 'brand_voice' => null, 'forbidden_words' => []]), fn () => app(\App\Services\Ai\AiAccess::class)->forget());
    ($this->setup)();
    $this->owner = fn () => $this->actingAs(TenantAdmin::withoutGlobalScopes()->where('email', 'owner@egy.test')->first(), 'tenant');
    $this->base = 'http://egy.3alraf.test/admin/ai/studio';
    $this->product = function (Tenant $t, string $slug) {
        $pr = Product::create(['type' => 'simple', 'name' => ['en' => 'Gold Watch', 'ar' => 'ساعة'], 'slug' => $slug, 'sku' => strtoupper($slug), 'barcode' => app(\App\Services\BarcodeService::class)->generateUnique()]);
        TenantProduct::create(['tenant_id' => $t->id, 'product_id' => $pr->id, 'price' => '99.00', 'cost' => '20', 'is_active' => true]);

        return $pr;
    };
    $this->llm = fn (array $variants, array $schedule = []) => ['choices' => [['message' => ['content' => json_encode(['variants' => $variants, 'schedule' => $schedule])], 'finish_reason' => 'stop']], 'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 50]];
    $this->payload = fn (array $o = []) => $o + ['mode' => 'social', 'platform' => 'x', 'goal' => 'sales', 'tone' => 'friendly', 'languages' => ['en'], 'format' => 'text', 'brief' => 'New gold watch'];
    $this->v = fn (string $lang, string $caption, array $extra = []) => $extra + ['label' => 'A', 'language' => $lang, 'caption' => $caption, 'hashtags' => ['a', 'b', 'c', 'd'], 'cta' => 'Shop now', 'image_prompt' => 'A gold watch on marble', 'video_prompt' => 'Spin'];
});

it('has platform rules for every network', function () {
    expect(SocialPlatform::X->charLimit())->toBe(280)->and(SocialPlatform::X->maxHashtags())->toBe(2)->and(SocialPlatform::TIKTOK->aspect())->toBe('9:16')
        ->and(SocialPlatform::INSTAGRAM->aspectRatios())->toContain('4:5', '1:1')->and(SocialPlatform::WHATSAPP->maxHashtags())->toBe(0)
        ->and(SocialPlatform::cases())->toHaveCount(6);
});

it('enforces platform character and hashtag limits on the generated text', function () {
    Http::fake(['api.openai.com/v1/chat/completions' => Http::response(($this->llm)([($this->v)('en', str_repeat('Great watch. ', 60))]))]);
    ($this->owner)()->postJson($this->base.'/generate', ($this->payload)())->assertCreated();
    $g = AiGeneration::latest('id')->first();
    $v = $g->output['variants'][0];
    expect($g->status->value)->toBe('draft')->and(mb_strlen($v['text']))->toBeLessThanOrEqual(280)->and($v['hashtags'])->toHaveCount(2)->and($g->output['warnings'])->not->toBeEmpty()
        ->and($g->type->value)->toBe('social_post')->and($g->user_id)->not->toBeNull();
});

it('removes forbidden words from copy, CTA and hashtags (post-check) and reports it', function () {
    ($this->setup)(['forbidden_words' => ['cheap', 'rubbish']]);
    Http::fake(['api.openai.com/v1/chat/completions' => Http::response(($this->llm)([($this->v)('en', 'A Cheap but great watch, not cheaper.', ['hashtags' => ['cheap', 'gold'], 'cta' => 'Buy rubbish now'])]))]);
    ($this->owner)()->postJson($this->base.'/generate', ($this->payload)(['platform' => 'instagram']))->assertCreated();
    $o = AiGeneration::latest('id')->first()->output;
    expect(strtolower($o['variants'][0]['text']))->not->toContain('cheap ')->and($o['variants'][0]['text'])->toContain('cheaper')->and($o['variants'][0]['hashtags'])->toBe(['#gold'])
        ->and($o['variants'][0]['text'])->not->toContain('rubbish')->and(collect($o['warnings'])->pluck('code'))->toContain('forbidden_removed');
});

it('only accepts tenant locales and drops answers in other languages', function () {
    $this->actingAs(TenantAdmin::withoutGlobalScopes()->where('email', 'owner@egy.test')->first(), 'tenant')
        ->postJson($this->base.'/generate', ($this->payload)(['languages' => ['fr']]))->assertStatus(422)->assertJsonValidationErrors('languages.0');
    Http::fake(['api.openai.com/v1/chat/completions' => Http::response(($this->llm)([($this->v)('en', 'English text'), ($this->v)('de', 'Deutsch'), ($this->v)('ar', 'نص عربي')]))]);
    ($this->owner)()->postJson($this->base.'/generate', ($this->payload)(['languages' => ['en', 'ar'], 'platform' => 'facebook']))->assertCreated();
    $o = AiGeneration::latest('id')->first()->output;
    expect(collect($o['variants'])->pluck('language')->all())->toBe(['en', 'ar'])->and(collect($o['warnings'])->pluck('code'))->toContain('language_dropped');
});

it('requires a subject and rejects formats that need a missing model', function () {
    $c = ($this->owner)();
    $c->postJson($this->base.'/generate', ($this->payload)(['brief' => '']))->assertStatus(422)->assertJsonValidationErrors('brief');
    $c->postJson($this->base.'/generate', ($this->payload)(['format' => 'video']))->assertStatus(422)->assertJsonValidationErrors('format');   // no active video model
    ($this->setup)([], [$this->text->id]);
    $c->postJson($this->base.'/generate', ($this->payload)(['format' => 'image']))->assertStatus(422)->assertJsonValidationErrors('format');   // image model not allowed
});

it('generates an image saved with ImageUploadService and keeps the draft when the image fails', function () {
    Http::fake(['api.openai.com/v1/chat/completions' => Http::response(($this->llm)([($this->v)('en', 'Hi')])), 'api.openai.com/v1/images/generations' => Http::sequence()->push(['data' => [['b64_json' => PNG_B64]]])->push(['error' => ['message' => 'boom']], 500)]);
    ($this->owner)()->postJson($this->base.'/generate', ($this->payload)(['format' => 'image', 'platform' => 'instagram']))->assertCreated();
    $g = AiGeneration::latest('id')->first();
    $m = $g->media[0];
    expect($m['state'])->toBe('ready')->and($m['path'])->toStartWith('tenants/'.$this->tenant->id.'/ai-studio/')->and(\App\Services\ImageUploadService::disk()->exists($m['path']))->toBeTrue()
        ->and($m['aspect'])->toBe('4:5');
    \App\Services\ImageUploadService::disk()->delete([$m['path'], $m['thumb']]);

    ($this->owner)()->postJson($this->base.'/generate', ($this->payload)(['format' => 'image']))->assertCreated();
    $g = AiGeneration::latest('id')->first();
    expect($g->status->value)->toBe('draft')->and($g->media[0]['state'])->toBe('failed')->and($g->output['variants'])->toHaveCount(1);
});

it('enforces the monthly image quota up front and inside the job', function () {
    ($this->setup)(['monthly_image_quota' => 1]);
    $log = new AiUsageLog(['feature' => 'studio', 'ai_model_id' => $this->image->id, 'images' => 1, 'status' => 'ok']);
    $log->tenant_id = $this->tenant->id;
    $log->save();
    ($this->owner)()->postJson($this->base.'/generate', ($this->payload)(['format' => 'image']))->assertStatus(422)->assertJsonPath('reason', 'quota_images');
    expect(AiGeneration::count())->toBe(0);
    ($this->owner)()->postJson($this->base.'/generate', ($this->payload)())->assertStatus(201)->assertJsonPath('data.status', 'failed');   // no http fake -> provider error is user safe
});

it('runs video as an async stage with polling states and a hidden option without a video model', function () {
    $vm = AiModel::create(['ai_provider_id' => $this->text->ai_provider_id, 'model_id' => 'vid-st', 'label' => 'V', 'kind' => 'video', 'is_active' => true]);
    ($this->setup)([], [$this->text->id, $this->image->id, $vm->id]);
    Queue::fake();
    Http::fake(['api.openai.com/v1/chat/completions' => Http::response(($this->llm)([($this->v)('en', 'Hi')]))]);
    ($this->owner)()->postJson($this->base.'/generate', ($this->payload)(['format' => 'video', 'platform' => 'tiktok']))->assertCreated()->assertJsonPath('data.pending', true);
    Queue::assertPushed(RunAiGenerationJob::class, fn ($j) => $j->stage === 'main');
    $g = AiGeneration::latest('id')->first();
    app(RunAiGenerationJob::class, ['generationId' => $g->id, 'tenantId' => $this->tenant->id])->handle(app(StudioService::class));
    Queue::assertPushed(RunAiGenerationJob::class, fn ($j) => $j->stage === 'video');
    $g->refresh();
    expect($g->media[0]['state'])->toBe('pending');
    ($this->owner)()->getJson($this->base.'/generations/'.$g->id)->assertJsonPath('data.pending', true);

    $mock = Mockery::mock(AiGateway::class);
    $mock->shouldReceive('video')->once()->andReturn(new AiResponse(media: [['b64' => base64_encode('FAKEMP4')]], videoSecs: 8));
    app()->instance(AiGateway::class, $mock);
    $svc = app(StudioService::class);
    $svc->generateVideo($g->fresh());
    $m = $g->fresh()->media[0];
    expect($m['state'])->toBe('ready')->and(\App\Services\ImageUploadService::disk()->get($m['path']))->toBe('FAKEMP4');
    \App\Services\ImageUploadService::disk()->delete($m['path']);

    // vendor without video support => failed media state, text draft intact
    app()->forgetInstance(AiGateway::class);
    $g2 = $g->fresh();
    $g2->forceFill(['media' => [['type' => 'video', 'state' => 'pending']]])->save();
    app(StudioService::class)->generateVideo($g2);
    expect($g2->fresh()->media[0]['state'])->toBe('failed')->and($g2->fresh()->status->value)->toBe('draft');
});

it('marketing mode builds UTM links, A/B variants and country calendar events', function () {
    $p = ($this->product)($this->tenant, 'gold-watch');
    Http::fake(['api.openai.com/v1/chat/completions' => Http::response(($this->llm)([($this->v)('en', 'A'), ($this->v)('en', 'B', ['label' => 'B'])], [['day' => 'Fri', 'time' => '20:00', 'reason' => 'Evening']]))]);
    ($this->owner)()->postJson($this->base.'/generate', ($this->payload)(['mode' => 'marketing', 'platform' => 'facebook', 'brief' => null, 'product_ids' => [$p->id], 'campaign_type' => 'seasonal', 'event' => 'black_friday', 'variants' => 2]))->assertCreated();
    $g = AiGeneration::latest('id')->first();
    $vs = $g->output['variants'];
    expect($g->type->value)->toBe('marketing_post')->and($vs)->toHaveCount(2)->and($vs[0]['url'])->toContain('/product/gold-watch')->toContain('utm_source=facebook')->toContain('utm_medium=social')->toContain('utm_campaign=black-friday')
        ->and($vs[1]['label'])->toBe('B')->and($vs[0]['text'])->toContain('utm_content=a')->and($g->output['schedule'])->toHaveCount(1);
    $ev = collect(app(MarketingCalendar::class)->upcoming('EG', CarbonImmutable::parse('2026-11-01')))->pluck('key');
    expect($ev)->toContain('black_friday')->and(collect(app(MarketingCalendar::class)->upcoming('US', CarbonImmutable::parse('2026-12-20'), 30))->pluck('key'))->not->toContain('eid_fitr');
    expect(collect(app(MarketingCalendar::class)->upcoming('EG', CarbonImmutable::parse('2027-01-10'), 60))->pluck('key'))->toContain('ramadan');
});

it('marks the generation failed with a safe message when the model answers garbage or the provider errors', function () {
    Http::fake(['api.openai.com/v1/chat/completions' => Http::sequence()->push(['choices' => [['message' => ['content' => 'not json'], 'finish_reason' => 'stop']], 'usage' => []])->push(['error' => ['message' => 'sk-secret-key leaked']], 500)]);
    ($this->owner)()->postJson($this->base.'/generate', ($this->payload)())->assertCreated()->assertJsonPath('data.status', 'failed');
    $r = ($this->owner)()->postJson($this->base.'/generate', ($this->payload)())->assertCreated();
    expect(json_encode($r->json()))->not->toContain('sk-secret');
    expect(AiGeneration::where('status', 'failed')->count())->toBe(2);
});

it('regenerates from a previous generation, lists history and deletes', function () {
    Http::fake(['api.openai.com/v1/chat/completions' => Http::response(($this->llm)([($this->v)('en', 'Hi')]))]);
    $c = ($this->owner)();
    $id = $c->postJson($this->base.'/generate', ($this->payload)())->json('data.id');
    $new = $c->postJson($this->base.'/generations/'.$id.'/regenerate')->assertCreated()->json('data');
    expect($new['id'])->not->toBe($id)->and($new['inputs']['parent_id'])->toBe($id);
    expect($c->getJson($this->base.'/history')->assertOk()->json('data'))->toHaveCount(2);
    $c->deleteJson($this->base.'/generations/'.$id)->assertOk();
    expect(AiGeneration::count())->toBe(1);
});

it('isolates tenants: other tenant generations, products and models are unreachable', function () {
    $foreign = ($this->product)($this->other, 'foreign-prod');
    $g = AiGeneration::withoutGlobalScopes()->create(['tenant_id' => $this->other->id, 'type' => 'social_post', 'status' => 'draft', 'inputs' => ['platform' => 'x'], 'output' => ['variants' => []]]);
    $c = ($this->owner)();
    $c->getJson($this->base.'/generations/'.$g->id)->assertNotFound();
    $c->postJson($this->base.'/generations/'.$g->id.'/regenerate')->assertNotFound();
    $c->deleteJson($this->base.'/generations/'.$g->id)->assertNotFound();
    expect($g->fresh())->not->toBeNull();
    $c->postJson($this->base.'/generate', ($this->payload)(['product_ids' => [$foreign->id]]))->assertStatus(422)->assertJsonValidationErrors('product_ids.0');
    $otherModel = AiModel::create(['ai_provider_id' => $this->text->ai_provider_id, 'model_id' => 'not-allowed', 'label' => 'N', 'kind' => 'text', 'is_active' => true]);
    $c->postJson($this->base.'/generate', ($this->payload)(['ai_model_id' => $otherModel->id]))->assertStatus(422)->assertJsonValidationErrors('ai_model_id');
    $c->getJson($this->base.'/history')->assertJsonCount(0, 'data');
    // job under another tenant context can not see this tenant's generation
    $mine = AiGeneration::create(['type' => 'social_post', 'status' => 'queued', 'inputs' => ($this->payload)(['languages' => ['en'], 'platform' => 'x'])]);
    app(RunAiGenerationJob::class, ['generationId' => $mine->id, 'tenantId' => $this->other->id])->handle(app(StudioService::class));
    expect($mine->fresh()->status->value)->toBe('queued');
});

it('gates the studio behind the feature flag and the permission, and never exposes keys', function () {
    ($this->setup)(['features' => ['studio' => false]]);
    ($this->owner)()->getJson($this->base.'/history')->assertForbidden();
    ($this->setup)();
    $html = ($this->owner)()->get($this->base)->assertOk()->getContent();
    expect($html)->not->toContain('sk-test')->and($html)->toContain(__('ai.studio.title'));
    $this->app['auth']->forgetGuards();
    $this->getJson($this->base.'/history')->assertUnauthorized();
});
