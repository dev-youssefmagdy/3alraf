<?php

use App\Enums\AiDriverType;
use App\Exceptions\AiException;
use App\Models\AiModel;
use App\Models\AiProvider;
use App\Models\AiTenantSetting;
use App\Models\AiUsageLog;
use App\Models\CentralAdmin;
use App\Models\Tenant;
use App\Models\TenantAdmin;
use App\Services\Ai\AiAccess;
use App\Services\Ai\AiContextFactory;
use App\Services\Ai\AiGateway;
use App\Services\Ai\Contracts\AiTool;
use App\Services\Ai\AiContext;
use App\Services\Ai\Support\AiRequest;
use Database\Seeders\FoundationSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function () {
    $this->seed(FoundationSeeder::class);
    $this->tenant = Tenant::where('slug', 'egy')->first();
    $this->other = Tenant::firstOrCreate(['slug' => 'sa'], ['name' => 'KSA', 'country' => 'SA', 'currency_code' => 'SAR', 'currency_symbol' => 'SAR', 'currency_rate' => 1, 'default_locale' => 'ar', 'locales' => ['ar', 'en'], 'timezone' => 'Asia/Riyadh', 'is_active' => true]);
    app()->instance('currentTenant', $this->tenant);
    $this->c = 'http://3alraf.test/admin';
    $this->t = 'http://egy.3alraf.test/admin';
    $mk = function (AiDriverType $d, string $mid, string $key = 'sk-secret-123456') {
        $p = AiProvider::create(['driver' => $d, 'name' => $d->value, 'api_key' => $key, 'is_active' => true]);

        return AiModel::create(['ai_provider_id' => $p->id, 'model_id' => $mid, 'label' => $mid, 'kind' => 'text', 'capabilities' => ['tools' => true], 'input_cost' => 3, 'output_cost' => 15, 'is_active' => true]);
    };
    $this->mk = $mk;
    $this->enable = fn (array $o = [], ?Tenant $t = null) => AiTenantSetting::withoutGlobalScopes()->updateOrCreate(['tenant_id' => ($t ?? $this->tenant)->id],
        $o + ['ai_enabled' => true, 'features' => ['chat' => true, 'trends' => true, 'studio' => true, 'editor' => true, 'generators' => true]]);
    $this->central = fn () => $this->actingAs(CentralAdmin::where('email', 'admin@3alraf.test')->first(), 'central');
    $this->owner = fn () => $this->actingAs(TenantAdmin::withoutGlobalScopes()->where('email', 'owner@egy.test')->first(), 'tenant');
});

it('maps the Anthropic request and response incl. tool_use round trip', function () {
    $m = ($this->mk)(AiDriverType::ANTHROPIC, 'claude-x');
    Http::fake(['api.anthropic.com/*' => Http::sequence()
        ->push(['content' => [['type' => 'text', 'text' => 'checking'], ['type' => 'tool_use', 'id' => 'tu_1', 'name' => 'echo', 'input' => ['q' => 'hi']]], 'usage' => ['input_tokens' => 10, 'output_tokens' => 5], 'stop_reason' => 'tool_use'])
        ->push(['content' => [['type' => 'text', 'text' => 'done']], 'usage' => ['input_tokens' => 20, 'output_tokens' => 7], 'stop_reason' => 'end_turn'])]);
    $tool = new class implements AiTool {
        public function name(): string { return 'echo'; }
        public function description(): string { return 'echo'; }
        public function schema(): array { return ['type' => 'object', 'properties' => ['q' => ['type' => 'string']]]; }
        public function execute(array $a, AiContext $c): array { return ['echo' => $a['q']]; }
    };
    ($this->enable)();
    $res = app(AiGateway::class)->chat($m, new AiRequest('', [['role' => 'user', 'content' => 'hello']], 'be nice', maxTokens: 100), 'chat', [$tool], trace: $trace);
    expect($res->text)->toBe('done')->and($res->tokensIn)->toBe(30)->and($res->tokensOut)->toBe(12)->and($trace)->toBe(['echo']);
    $reqs = Http::recorded();
    [$first] = $reqs[0];
    expect($first->url())->toBe('https://api.anthropic.com/v1/messages')->and($first->header('x-api-key')[0])->toBe('sk-secret-123456')->and($first->header('anthropic-version'))->not->toBeEmpty();
    $b = $first->data();
    expect($b['model'])->toBe('claude-x')->and($b['max_tokens'])->toBe(100)->and($b['system'])->toContain('Egypt')->and($b['system'])->toContain('EGP')->and($b['tools'][0]['input_schema']['type'])->toBe('object');
    $second = $reqs[1][0]->data();
    expect($second['messages'][1]['content'][1])->toMatchArray(['type' => 'tool_use', 'id' => 'tu_1', 'name' => 'echo'])
        ->and($second['messages'][2]['content'][0]['type'])->toBe('tool_result')->and($second['messages'][2]['content'][0]['tool_use_id'])->toBe('tu_1');
    $log = AiUsageLog::withoutGlobalScopes()->latest('id')->first();
    expect($log->tenant_id)->toBe($this->tenant->id)->and($log->tokens_in)->toBe(30)->and($log->country)->toBe('EG')->and((float) $log->est_cost)->toBeGreaterThan(0);
});

it('maps OpenAI chat + tool calls + image, and Moonshot reuses it on its own base url without image/video', function () {
    $m = ($this->mk)(AiDriverType::OPENAI, 'gpt-x');
    ($this->enable)();
    Http::fake(['api.openai.com/v1/chat/completions' => Http::response(['choices' => [['message' => ['content' => null, 'tool_calls' => [['id' => 'c1', 'type' => 'function', 'function' => ['name' => 'echo', 'arguments' => '{"q":"z"}']]]], 'finish_reason' => 'tool_calls']], 'usage' => ['prompt_tokens' => 4, 'completion_tokens' => 2]]),
        'api.openai.com/v1/images/generations' => Http::response(['data' => [['b64_json' => 'AAA']]]),
        'api.moonshot.ai/*' => Http::response(['choices' => [['message' => ['content' => 'kimi'], 'finish_reason' => 'stop']], 'usage' => ['prompt_tokens' => 1, 'completion_tokens' => 1]])]);
    $res = app(AiGateway::class)->chat($m, new AiRequest('', [['role' => 'user', 'content' => 'x']], tools: [['name' => 'echo', 'description' => 'd', 'schema' => []]]), 'chat');
    expect($res->toolCalls[0])->toBe(['id' => 'c1', 'name' => 'echo', 'arguments' => ['q' => 'z']]);
    $sent = Http::recorded()[0][0];
    expect($sent->header('Authorization')[0])->toBe('Bearer sk-secret-123456')->and($sent->data()['tools'][0]['type'])->toBe('function')->and($sent->data()['messages'][0]['role'])->toBe('system');
    $img = app(AiGateway::class)->image($m, 'a cat', 'studio');
    expect($img->media)->toBe([['b64' => 'AAA']])->and($img->images)->toBe(1);

    $k = ($this->mk)(AiDriverType::MOONSHOT, 'kimi-x', 'sk-kimi');
    expect(app(AiGateway::class)->chat($k, new AiRequest('', [['role' => 'user', 'content' => 'x']]), 'chat')->text)->toBe('kimi');
    expect(Http::recorded()->last()[0]->url())->toBe('https://api.moonshot.ai/v1/chat/completions');
    expect(fn () => app(AiGateway::class)->video($m, 'p', 'studio'))->toThrow(AiException::class);
    expect(fn () => app(AiGateway::class)->image($k, 'p', 'studio'))->toThrow(AiException::class);
});

it('maps provider failures to a user-safe AiException (no vendor body)', function () {
    $m = ($this->mk)(AiDriverType::OPENAI, 'gpt-x');
    ($this->enable)();
    Http::fake(['*' => Http::response(['error' => ['message' => 'bad key sk-secret-123456']], 401)]);
    try {
        app(AiGateway::class)->chat($m, new AiRequest('', [['role' => 'user', 'content' => 'x']]), 'chat');
        $this->fail('expected exception');
    } catch (AiException $e) {
        expect($e->getMessage())->not->toContain('sk-secret')->and($e->reason)->toBe('http_401');
    }
    expect(AiUsageLog::withoutGlobalScopes()->where('status', 'error')->count())->toBe(1);
});

it('blocks over-quota, disabled feature, disallowed model and throttled tenants', function () {
    $m = ($this->mk)(AiDriverType::OPENAI, 'gpt-x');
    Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => 'ok'], 'finish_reason' => 'stop']], 'usage' => ['prompt_tokens' => 60, 'completion_tokens' => 50]])]);
    $req = fn () => new AiRequest('', [['role' => 'user', 'content' => 'x']]);
    $g = app(AiGateway::class);

    expect(fn () => $g->chat($m, $req(), 'chat'))->toThrow(AiException::class);          // no settings row = off
    ($this->enable)(['monthly_token_quota' => 100]);
    app(AiAccess::class)->forget();
    expect($g->chat($m, $req(), 'chat')->text)->toBe('ok');                               // 110 tokens used
    expect(fn () => $g->chat($m, $req(), 'chat'))->toThrow(AiException::class, __('ai.errors.quota'));
    Http::assertSentCount(1);                                                              // blocked before any HTTP

    ($this->enable)(['monthly_token_quota' => null, 'features' => ['chat' => false]]);
    app(AiAccess::class)->forget();
    expect(fn () => $g->chat($m, $req(), 'chat'))->toThrow(AiException::class, __('ai.errors.disabled'));

    ($this->enable)(['features' => ['chat' => true], 'allowed_model_ids' => [999999]]);
    app(AiAccess::class)->forget();
    expect(fn () => $g->chat($m, $req(), 'chat'))->toThrow(AiException::class);

    ($this->enable)(['allowed_model_ids' => null]);
    app(AiAccess::class)->forget();
    config(['ai.rate_per_minute' => 1]);
    RateLimiter::clear('ai:'.$this->tenant->id);
    $g->chat($m, $req(), 'chat');
    expect(fn () => $g->chat($m, $req(), 'chat'))->toThrow(AiException::class, __('ai.errors.throttled'));

    // quota is per tenant: the other tenant has its own counter
    ($this->enable)(['monthly_token_quota' => 100], $this->other);
    expect($g->usage($this->other->id)['tokens'])->toBe(0);
});

it('builds a country aware context from the tenant', function () {
    ($this->enable)(['brand_voice' => 'warm', 'forbidden_words' => ['cheap']]);
    $ctx = app(AiContextFactory::class)->forTenant();
    expect($ctx->country)->toBe('EG')->and($ctx->currency)->toBe('EGP')->and($ctx->systemPreamble())->toContain('EGP')->toContain('warm')->toContain('cheap')->toContain('untrusted');
});

it('never exposes or audits the api key', function () {
    $m = ($this->mk)(AiDriverType::OPENAI, 'gpt-x', 'sk-TOPSECRETKEY9999');
    $p = $m->provider;
    expect(json_encode($p->toArray()))->not->toContain('TOPSECRET')->and(\DB::table('ai_providers')->where('id', $p->id)->value('api_key'))->not->toContain('TOPSECRET');
    $p->update(['api_key' => 'sk-ROTATED-KEY-0000', 'name' => 'renamed']);
    $logs = \Spatie\Activitylog\Models\Activity::where('subject_type', AiProvider::class)->get();
    expect($logs)->not->toBeEmpty()->and(json_encode($logs->toArray()))->not->toContain('TOPSECRET')->not->toContain('ROTATED');
    ($this->central)();
    $this->get("{$this->c}/ai/providers/{$p->id}/edit")->assertOk()->assertDontSee('ROTATED')->assertSee('0000');
    $this->getJson("{$this->c}/ai/providers/datatable")->assertOk();
    expect($this->getJson("{$this->c}/ai/providers/datatable")->getContent())->not->toContain('ROTATED');
});

it('central CRUD: provider, model defaults, test connection, tenant AI, usage', function () {
    ($this->central)();
    $this->postJson("{$this->c}/ai/providers/validate", [])->assertStatus(422)->assertJsonValidationErrors(['driver', 'name', 'api_key']);
    $this->postJson("{$this->c}/ai/providers", ['driver' => 'anthropic', 'name' => 'Claude', 'api_key' => 'sk-ant-1', 'is_active' => 1])->assertSuccessful();
    $p = AiProvider::where('name', 'Claude')->firstOrFail();
    expect($p->api_key)->toBe('sk-ant-1');
    $this->putJson("{$this->c}/ai/providers/{$p->id}", ['driver' => 'anthropic', 'name' => 'Claude 2', 'api_key' => '', 'is_active' => 1])->assertSuccessful();
    expect($p->fresh()->api_key)->toBe('sk-ant-1')->and($p->fresh()->name)->toBe('Claude 2');

    Http::fake(['api.anthropic.com/v1/models' => Http::sequence()->push(['data' => []])->push([], 401)]);
    $this->postJson("{$this->c}/ai/providers/{$p->id}/test")->assertOk();
    expect($p->fresh()->last_test_ok)->toBeTrue();
    $this->postJson("{$this->c}/ai/providers/{$p->id}/test")->assertStatus(422);
    expect($p->fresh()->last_test_ok)->toBeFalse();

    foreach (['a', 'b'] as $x) {
        $this->postJson("{$this->c}/ai/models", ['ai_provider_id' => $p->id, 'model_id' => $x, 'label' => $x, 'kind' => 'text', 'capabilities' => ['tools'], 'is_active' => 1, 'is_default' => 1])->assertSuccessful();
    }
    expect(AiModel::where('ai_provider_id', $p->id)->where('is_default', true)->pluck('model_id')->all())->toBe(['b'])->and(AiModel::where('model_id', 'a')->first()->can('tools'))->toBeTrue();

    $this->putJson("{$this->c}/ai/tenants/{$this->tenant->id}", ['ai_enabled' => 1, 'monthly_token_quota' => 5000, 'features' => ['chat', 'trends'], 'allowed_model_ids' => [AiModel::where('model_id', 'a')->first()->id]])->assertSuccessful();
    $s = AiTenantSetting::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->first();
    expect($s->ai_enabled)->toBeTrue()->and($s->monthly_token_quota)->toBe(5000)->and($s->features)->toMatchArray(['chat' => true, 'trends' => true, 'studio' => false]);
    $this->getJson("{$this->c}/ai/tenants/datatable")->assertOk();
    $this->get("{$this->c}/ai/tenants/{$this->tenant->id}/edit")->assertOk();
    $this->get("{$this->c}/ai/usage")->assertOk();
    $this->getJson("{$this->c}/ai/usage/datatable")->assertOk();
    $this->get("{$this->c}/ai/providers")->assertOk()->assertSee(__('ai.menu.group'));
});

it('tenant admins cannot reach central AI routes', function () {
    ($this->owner)();
    foreach (['providers', 'models', 'tenants', 'usage'] as $r) {
        $this->get("{$this->t}/ai/$r")->assertStatus(404);                       // not registered on the tenant host
    }
    $this->get("http://3alraf.test/admin/ai/providers")->assertRedirect();       // guard isolation on the central host
});

it('gate middleware: AI off -> 403 disabled state, sidebar hidden; on -> settings page + save', function () {
    ($this->owner)();
    $this->get("{$this->t}/ai/settings")->assertStatus(403)->assertSee(__('ai.errors.disabled'));
    $this->getJson("{$this->t}/ai/settings")->assertStatus(403);
    $this->get("{$this->t}")->assertOk()->assertDontSee(__('ai.menu.settings'));

    $m = ($this->mk)(AiDriverType::OPENAI, 'gpt-x');
    $hidden = ($this->mk)(AiDriverType::OPENAI, 'gpt-hidden');
    ($this->enable)(['allowed_model_ids' => [$m->id]]);
    app(AiAccess::class)->forget();
    $this->get("{$this->t}")->assertOk()->assertSee(__('ai.menu.settings'));
    $this->get("{$this->t}/ai/settings")->assertOk()->assertSee('gpt-x')->assertDontSee('gpt-hidden');

    $this->postJson("{$this->t}/ai/settings/validate", ['default_ai_model_id' => $hidden->id])->assertStatus(422)->assertJsonValidationErrors('default_ai_model_id');
    $this->putJson("{$this->t}/ai/settings", ['default_ai_model_id' => $m->id, 'brand_voice' => 'Friendly', 'forbidden_words' => 'cheap, free,cheap', 'default_platforms' => ['tiktok']])->assertSuccessful();
    $s = AiTenantSetting::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->first();
    expect($s->default_ai_model_id)->toBe($m->id)->and($s->forbidden_words)->toBe(['cheap', 'free'])->and($s->default_platforms)->toBe(['tiktok'])->and($s->ai_enabled)->toBeTrue();
});

it('feature gate and tenant isolation of settings', function () {
    ($this->enable)(['features' => ['chat' => false]]);
    ($this->enable)(['monthly_token_quota' => 1], $this->other);
    app(AiAccess::class)->forget();
    expect(app(AiAccess::class)->feature('chat'))->toBeFalse()->and(AiTenantSetting::count())->toBe(1)->and(AiTenantSetting::first()->tenant_id)->toBe($this->tenant->id);
    \Illuminate\Support\Facades\Route::middleware(['web', 'tenant.resolve', 'ai.enabled:chat'])->domain('{tenant}.'.config('app.central_domain'))->get('/_ai_probe', fn () => 'ok');
    ($this->owner)();
    $this->get('http://egy.3alraf.test/_ai_probe')->assertStatus(403);
    ($this->enable)(['features' => ['chat' => true]]);
    app(AiAccess::class)->forget();
    $this->get('http://egy.3alraf.test/_ai_probe')->assertOk();
});
