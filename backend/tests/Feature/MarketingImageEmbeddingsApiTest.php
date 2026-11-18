<?php

// Marketing API for the MCP image-embedding tools: GET image-embeddings/status (marketing:read), POST image-embeddings/fill-missing (embeddings:write,
// queues only missing embeddings, idempotent) and the migration that grants embeddings:write to product-suggestion tokens.

use App\DTOs\Marketing\MarketingTokenData;
use App\Jobs\EmbedImageJob;
use App\Models\Bundle;
use App\Models\MarketingApiToken;
use App\Models\Product;
use App\Services\Marketing\MarketingTokenService;
use Database\Seeders\FoundationSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(FoundationSeeder::class);
    Cache::flush();
    Storage::fake(config('media.disk'));
    config(['image_search.embedder.url' => 'http://embedder.test', 'image_search.model' => 'test-model@pad224']);
    Http::fake(['http://embedder.test/health' => Http::response(['status' => 'ok', 'model' => 'test-model@pad224', 'dim' => 768])]);

    $this->mkToken = fn (array $scopes) => app(MarketingTokenService::class)->create(new MarketingTokenData('mcp '.uniqid(), $scopes, null, null))[1];
    $this->call = fn (string $method, string $path, array $body, string $token) => $this->flushHeaders()
        ->withHeaders(['Accept' => 'application/json', 'Authorization' => 'Bearer '.$token])->json($method, '/api/internal/marketing/'.$path, $body);

    foreach (['p1', 'p2', 'p3'] as $i => $slug) {
        $p = Product::create(['type' => 'simple', 'name' => ['ar' => $slug], 'slug' => $slug, 'sku' => strtoupper($slug)]);
        Storage::disk(config('media.disk'))->put("catalog/$slug.avif", "bytes-$slug");
        $p->images()->create(['path' => "catalog/$slug.avif", 'thumb_path' => "catalog/{$slug}_thumb.avif"]);
    }
    Storage::disk(config('media.disk'))->put('catalog/k.avif', 'bundle-bytes');
    Bundle::create(['name' => ['ar' => 'باقة'], 'slug' => 'k', 'price' => 50, 'is_active' => true, 'image' => 'catalog/k.avif']);
});

it('reports coverage with marketing:read', function () {
    $tok = ($this->mkToken)(['marketing:read']);
    ($this->call)('GET', 'image-embeddings/status', [], $tok)->assertOk()
        ->assertJsonPath('data.total', 4)->assertJsonPath('data.indexed', 0)->assertJsonPath('data.missing', 4)->assertJsonPath('data.images.total', 3)
        ->assertJsonPath('data.bundles.total', 1)->assertJsonPath('data.model', 'test-model@pad224')->assertJsonPath('data.embedder_reachable', true);
    ($this->call)('GET', 'image-embeddings/status', [], ($this->mkToken)(['meta:read']))->assertForbidden()->assertJsonPath('error', 'forbidden_scope');
});

it('queues only missing embeddings with embeddings:write and queues 0 on the second call', function () {
    Queue::fake();
    $read = ($this->mkToken)(['marketing:read', 'suggestions:write:product']);
    ($this->call)('POST', 'image-embeddings/fill-missing', [], $read)->assertForbidden()->assertJsonPath('error', 'forbidden_scope');
    Queue::assertNothingPushed();

    $tok = ($this->mkToken)(['marketing:read', 'embeddings:write']);
    ($this->call)('POST', 'image-embeddings/fill-missing', ['limit' => 3], $tok)->assertOk()
        ->assertJsonPath('data.queued', 3)->assertJsonPath('data.remaining', 1)->assertJsonPath('data.status.pending', 3);
    ($this->call)('POST', 'image-embeddings/fill-missing', [], $tok)->assertOk()->assertJsonPath('data.queued', 1)->assertJsonPath('data.remaining', 0);
    ($this->call)('POST', 'image-embeddings/fill-missing', [], $tok)->assertOk()->assertJsonPath('data.queued', 0)->assertJsonPath('data.status.pending', 4);
    Queue::assertPushed(EmbedImageJob::class, 4);

    ($this->call)('POST', 'image-embeddings/fill-missing', ['limit' => 0], $tok)->assertStatus(422)->assertJsonPath('error', 'validation_failed');
    ($this->call)('POST', 'image-embeddings/fill-missing', ['force' => true], $tok)->assertStatus(422);
    ($this->call)('GET', 'image-embeddings/fill-missing', [], $tok)->assertNotFound(); // POST only
});

it('offers embeddings:write as a grantable scope', function () {
    expect(config('marketing.scopes.embeddings:write.enabled'))->toBeTrue()
        ->and(__('marketing.tokens.scope_desc.embeddings_write'))->not->toBe('marketing.tokens.scope_desc.embeddings_write');
});

it('grants embeddings:write to live product-suggestion tokens only, idempotently, and removes it on rollback', function () {
    $migration = require database_path('migrations/2027_01_18_000100_grant_embeddings_write_scope.php');
    $make = fn (array $scopes, bool $revoked = false) => MarketingApiToken::create(['name' => 't', 'token_prefix' => 'mkt_'.uniqid(), 'token_hash' => hash('sha256', uniqid()),
        'scopes' => $scopes, 'revoked_at' => $revoked ? now() : null]);
    $mcp = $make(['marketing:read', 'suggestions:write:product']);
    $readOnly = $make(['marketing:read']);
    $revoked = $make(['marketing:read', 'suggestions:write:product'], true);

    $migration->up();
    $migration->up();
    expect($mcp->fresh()->scopes)->toBe(['marketing:read', 'suggestions:write:product', 'embeddings:write'])
        ->and($readOnly->fresh()->scopes)->toBe(['marketing:read'])
        ->and($revoked->fresh()->scopes)->toBe(['marketing:read', 'suggestions:write:product']);

    $migration->down();
    expect($mcp->fresh()->scopes)->toBe(['marketing:read', 'suggestions:write:product']);
});
