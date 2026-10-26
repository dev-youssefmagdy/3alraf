<?php

use App\Enums\AiDriverType;
use App\Models\AiConversation;
use App\Models\AiGeneration;
use App\Models\AiModel;
use App\Models\AiProvider;
use App\Models\AiTenantSetting;
use App\Models\Tenant;
use App\Models\TenantAdmin;
use App\Services\Ai\AiGateway;
use App\Services\Ai\Support\AiRequest;
use Database\Seeders\FoundationSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function () {
    $this->seed(FoundationSeeder::class);
    RateLimiter::clear('ai:1');
    $this->tenant = Tenant::where('slug', 'egy')->first();
    $this->other = Tenant::firstOrCreate(['slug' => 'sa'], ['name' => 'KSA', 'country' => 'SA', 'currency_code' => 'SAR', 'currency_symbol' => 'SAR', 'currency_rate' => 1, 'default_locale' => 'ar', 'locales' => ['ar', 'en'], 'timezone' => 'Asia/Riyadh', 'is_active' => true]);
    app()->instance('currentTenant', $this->tenant);
    $this->provider = AiProvider::create(['driver' => AiDriverType::OPENAI, 'name' => 'rv', 'api_key' => 'sk-secret-ABCDEFGH12345', 'is_active' => true]);
    $this->model = AiModel::create(['ai_provider_id' => $this->provider->id, 'model_id' => 'gpt-rv', 'label' => 'RV', 'kind' => 'text', 'capabilities' => ['tools' => true], 'is_active' => true]);
    AiTenantSetting::withoutGlobalScopes()->updateOrCreate(['tenant_id' => $this->tenant->id], ['ai_enabled' => true, 'features' => ['chat' => true, 'studio' => true, 'editor' => true, 'generators' => true, 'trends' => true],
        'allowed_model_ids' => null, 'default_ai_model_id' => $this->model->id, 'monthly_token_quota' => null]);
    app(\App\Services\Ai\AiAccess::class)->forget();
    $this->owner = fn () => $this->actingAs(TenantAdmin::withoutGlobalScopes()->where('email', 'owner@egy.test')->first(), 'tenant');
    $this->base = 'http://egy.3alraf.test/admin';
});

it('gateway refuses a deactivated model or provider without calling the vendor', function () {
    Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => 'hi'], 'finish_reason' => 'stop']]])]);
    $gw = app(AiGateway::class);
    $req = fn () => new AiRequest('', [['role' => 'user', 'content' => 'x']], '');
    $this->model->update(['is_active' => false]);
    expect(fn () => $gw->chat($this->model->fresh(), $req(), 'chat'))->toThrow(\App\Exceptions\AiException::class);
    $this->model->update(['is_active' => true]);
    $this->provider->update(['is_active' => false]);
    expect(fn () => $gw->chat($this->model->fresh(), $req(), 'chat'))->toThrow(\App\Exceptions\AiException::class);
    Http::assertNothingSent();
});

it('provider error bodies echoing the api key are redacted in the log', function () {
    Http::fake(['*' => Http::response(['error' => ['message' => 'Incorrect API key provided: sk-secret-ABCDEFGH12345. Also key_9876543210abcdef']], 401)]);
    Log::spy();
    try {
        app(AiGateway::class)->chat($this->model, new AiRequest('', [['role' => 'user', 'content' => 'x']], ''), 'chat');
    } catch (\App\Exceptions\AiException $e) {
        expect($e->getMessage())->not->toContain('sk-secret');
    }
    Log::shouldHaveReceived('warning')->withArgs(fn ($msg, $ctx = []) => ! str_contains(json_encode($ctx), 'ABCDEFGH12345') && ! str_contains(json_encode($ctx), '9876543210abcdef'))->once();
});

it('chat stream ends with a clean error event when something non-AI crashes', function () {
    $this->mock(AiGateway::class, fn ($m) => $m->shouldReceive('chat')->andThrow(new \RuntimeException('SQLSTATE[HY000] secret internals'))->shouldReceive('usage')->andReturn(['tokens' => 0, 'images' => 0, 'video' => 0]));
    $res = ($this->owner)()->post($this->base.'/ai/chat/stream', ['message' => 'hello']);
    $res->assertOk();
    $body = $res->streamedContent();
    expect($body)->toContain('event: error')->not->toContain('SQLSTATE')->not->toContain('event: done');
    $c = AiConversation::first();
    expect($c->messages()->where('role', 'assistant')->where('status', 'error')->count())->toBe(1);
});

it('conversations and generations of another tenant or user are invisible (404)', function () {
    $mine = TenantAdmin::withoutGlobalScopes()->where('email', 'owner@egy.test')->first();
    $c = new AiConversation(['user_id' => $mine->id + 999, 'title' => 'not yours']);
    $c->tenant_id = $this->tenant->id;
    $c->save();
    $o = new AiConversation(['user_id' => $mine->id, 'title' => 'other tenant']);
    $o->tenant_id = $this->other->id;
    $o->save();
    foreach ([$c->id, $o->id] as $id) {
        ($this->owner)()->getJson($this->base."/ai/chat/conversations/$id")->assertNotFound();
        ($this->owner)()->deleteJson($this->base."/ai/chat/conversations/$id")->assertNotFound();
    }
    foreach (['social_post' => 'studio/generations', 'banner' => 'generators', 'product_edit' => 'editor/generations'] as $type => $path) {
        $g = new AiGeneration(['type' => $type, 'status' => 'draft', 'inputs' => [], 'output' => []]);
        $g->tenant_id = $this->other->id;
        $g->save();
        ($this->owner)()->getJson($this->base."/ai/$path/{$g->id}".($type === 'banner' ? '/json' : ''))->assertNotFound();
    }
    // right tenant, wrong feature: a studio draft is not reachable through the generators or editor routes
    $s = new AiGeneration(['type' => 'social_post', 'status' => 'draft', 'inputs' => [], 'output' => []]);
    $s->tenant_id = $this->tenant->id;
    $s->save();
    ($this->owner)()->getJson($this->base."/ai/generators/{$s->id}/json")->assertNotFound();
    ($this->owner)()->getJson($this->base."/ai/editor/generations/{$s->id}")->assertNotFound();
});

it('feature switched off: every AI endpoint answers 403 JSON, pages render a disabled state', function () {
    AiTenantSetting::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->update(['ai_enabled' => false]);
    app(\App\Services\Ai\AiAccess::class)->forget();
    ($this->owner)()->getJson($this->base.'/ai/generators/datatable')->assertForbidden();
    ($this->owner)()->get($this->base.'/ai/chat')->assertForbidden();
    ($this->owner)()->get($this->base.'/ai/studio')->assertForbidden()->assertDontSee('Illuminate\\');
});

it('the AI diff does not break core tenant admin pages (en + ar, AI on and off)', function () {
    $pages = ['', 'orders', 'catalog', 'stock', 'customers', 'reports', 'roles', 'admins', 'expenses', 'banners', 'bundles', 'flash-sales', 'discount-codes', 'branches', 'notification-settings'];
    foreach ([true, false] as $on) {
        AiTenantSetting::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->update(['ai_enabled' => $on]);
        app(\App\Services\Ai\AiAccess::class)->forget();
        foreach (['en', 'ar'] as $loc) {
            $this->tenant->update(['default_locale' => $loc]);
            foreach ($pages as $p) {
                $r = ($this->owner)()->get(rtrim($this->base.'/'.$p, '/'));
                expect($r->status())->toBeIn([200, 302], "$p [$loc, ai=".(int) $on.'] -> '.$r->status());
                if ($r->status() === 200) {
                    expect($r->getContent())->not->toContain('ai.menu.')->not->toContain('admin.nav.');
                    $on ? $r->assertSee('/admin/ai/', false) : $r->assertDontSee('/admin/ai/chat', false);
                }
            }
        }
    }
    $this->tenant->update(['default_locale' => 'en']);
});

it('api keys never appear on tenant or central AI screens', function () {
    ($this->owner)()->get($this->base.'/ai/settings')->assertOk()->assertDontSee('sk-secret-ABCDEFGH12345')->assertDontSee('ABCDEFGH12345');
    $this->actingAs(\App\Models\CentralAdmin::where('email', 'admin@3alraf.test')->first(), 'central');
    foreach (['providers', 'models', 'tenants', 'usage'] as $p) {
        $this->get("http://3alraf.test/admin/ai/$p")->assertOk()->assertDontSee('sk-secret-ABCDEFGH12345');
    }
    expect(json_encode($this->provider->fresh()->toArray()))->not->toContain('sk-secret');
});

it('ai lang files keep ar/en parity', function () {
    $flat = function ($x, $p = '') use (&$flat) {
        $o = [];
        foreach ($x as $k => $v) {
            $o = is_array($v) ? array_merge($o, $flat($v, "$p$k.")) : array_merge($o, ["$p$k"]);
        }

        return $o;
    };
    foreach (['ai', 'admin'] as $f) {
        expect(array_diff($flat(require lang_path("en/$f.php")), $flat(require lang_path("ar/$f.php"))))->toBe([])
            ->and(array_diff($flat(require lang_path("ar/$f.php")), $flat(require lang_path("en/$f.php"))))->toBe([]);
    }
});
