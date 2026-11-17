<?php

// category suggestions (MCP create_category_suggestion): name / description per locale + image by URL (SSRF-safe, staged by hash),
// approve → apply updates the category through CategoryService, the old image file is kept, rollback restores it.

use App\DTOs\Marketing\MarketingTokenData;
use App\Models\Category;
use App\Models\MarketingSuggestion;
use App\Services\Marketing\MarketingTokenService;
use App\Services\Marketing\Suggestions\SuggestionEngine;
use App\Support\Http\SafeImageFetcher;
use App\Support\Marketing\SuggestionScopes;
use Database\Seeders\FoundationSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

function categoryPng(int $w = 60, int $h = 60): string
{
    $im = imagecreatetruecolor($w, $h);
    imagefill($im, 0, 0, imagecolorallocate($im, 20, 160, 90));
    ob_start();
    imagepng($im);

    return (string) ob_get_clean();
}

beforeEach(function () {
    $this->seed(FoundationSeeder::class);
    Cache::flush();
    Storage::fake(config('media.private_disk'));
    Storage::fake(config('media.disk'));
    config(['app.url' => 'http://3alraf.test', 'app.host' => '3alraf.test']);
    $this->app->instance(SafeImageFetcher::class, new SafeImageFetcher(fn (string $host) => $host === 'intranet.example' ? ['10.0.0.5'] : ['93.184.216.34']));
    Http::fake([
        'https://shop.example/cat.png' => Http::response(categoryPng(), 200, ['Content-Type' => 'image/png']),
        '*' => Http::response('', 404),
    ]);
    $this->owner = marketerOwner();
    $this->engine = app(SuggestionEngine::class);
    $this->tok = app(MarketingTokenService::class)->create(new MarketingTokenData('mcp', ['marketing:read', SuggestionScopes::for('category')], null, null))[1];
    $this->call = fn (string $method, string $path, array $body = []) => $this->flushHeaders()
        ->withHeaders(['Accept' => 'application/json', 'Authorization' => 'Bearer '.$this->tok])->json($method, '/api/internal/marketing/'.$path, $body);

    Storage::disk(config('media.disk'))->put('categories/old.avif', 'old-bytes');
    $this->cat = Category::create(['name' => ['en' => 'Watches', 'ar' => 'ساعات'], 'description' => ['en' => 'All watches'], 'slug' => 'watches', 'path' => '', 'depth' => 0,
        'is_active' => true, 'image' => 'categories/old.avif']);
    $this->cat->update(['path' => (string) $this->cat->id]);
    $this->create = fn (array $payload, array $extra = []) => ($this->call)('POST', 'suggestions/category', ['title' => 'Refresh Watches category', 'target_entity_type' => 'category',
        'target_entity_id' => $this->cat->id, 'payload' => $payload] + $extra);
});

it('updates the name and image after approval, keeps other locales, and rolls back to the old image', function () {
    ($this->create)(['name' => ['ar' => 'ساعات فاخرة'], 'image' => ['url' => 'https://shop.example/cat.png']])->assertCreated()->assertJsonPath('data.type', 'category');

    $s = MarketingSuggestion::query()->firstOrFail();
    expect($s->payload['image'])->toMatchArray(['url' => 'https://shop.example/cat.png', 'mime' => 'image/png', 'width' => 60])
        ->and($s->payload['image']['sha256'])->toBe(hash('sha256', categoryPng()));

    // review page: current vs proposed image (marketer-only preview route)
    $this->actingAs($this->owner, 'marketer');
    $this->get("/marketer/suggestions/{$s->id}")->assertOk()->assertSee('data-images', false)->assertSee('/marketer/suggestions/image/'.$s->payload['image']['sha256'], false);

    $this->engine->approve($s->fresh(), $this->owner);
    $exec = $this->engine->apply($s->fresh(), $this->owner);
    $cat = $this->cat->fresh();
    expect($exec->status->value)->toBe('succeeded')
        ->and($cat->getTranslation('name', 'ar'))->toBe('ساعات فاخرة')
        ->and($cat->getTranslation('name', 'en'))->toBe('Watches')                 // untouched locale
        ->and($cat->getTranslation('description', 'en'))->toBe('All watches')      // untouched field
        ->and($cat->image)->not->toBe('categories/old.avif')->toContain('categories/');
    $new = $cat->image;
    Storage::disk(config('media.disk'))->assertExists([$new, 'categories/old.avif']);   // old file kept for rollback

    $this->engine->rollback($s->fresh(), $this->owner);
    $cat = $this->cat->fresh();
    expect($cat->image)->toBe('categories/old.avif')->and($cat->getTranslation('name', 'ar'))->toBe('ساعات');
    Storage::disk(config('media.disk'))->assertMissing($new);
});

it('refuses an internal image URL, an empty change and a missing target', function () {
    ($this->create)(['image' => ['url' => 'https://intranet.example/x.png']])->assertStatus(422)->assertJsonValidationErrors(['payload.image.url']);
    ($this->create)([])->assertStatus(422);
    ($this->call)('POST', 'suggestions/category', ['title' => 'No target', 'payload' => ['name' => ['en' => 'X']]])->assertStatus(422);
    expect(MarketingSuggestion::count())->toBe(0);
});

it('needs the category write scope', function () {
    $tok = app(MarketingTokenService::class)->create(new MarketingTokenData('other', ['marketing:read', SuggestionScopes::for('product')], null, null))[1];
    $this->flushHeaders()->withHeaders(['Accept' => 'application/json', 'Authorization' => 'Bearer '.$tok])
        ->postJson('/api/internal/marketing/suggestions/category', ['title' => 'x', 'target_entity_type' => 'category', 'target_entity_id' => $this->cat->id, 'payload' => ['name' => ['en' => 'X']]])
        ->assertForbidden();
});

it('grants the category scope to existing product-writing tokens only', function () {
    $svc = app(MarketingTokenService::class);
    $writer = \App\Models\MarketingApiToken::query()->findOrFail($svc->create(new MarketingTokenData('writer', ['marketing:read', SuggestionScopes::for('product')], null, null))[0]->id);
    $reader = \App\Models\MarketingApiToken::query()->findOrFail($svc->create(new MarketingTokenData('reader', ['marketing:read'], null, null))[0]->id);
    $revoked = \App\Models\MarketingApiToken::query()->findOrFail($svc->create(new MarketingTokenData('old', ['marketing:read', SuggestionScopes::for('product')], null, null))[0]->id);
    $revoked->update(['revoked_at' => now()]);

    $m = require database_path('migrations/2027_01_17_000000_grant_category_suggestion_scope.php');
    $m->up();
    $m->up();   // idempotent

    expect($writer->fresh()->scopes)->toBe(['marketing:read', SuggestionScopes::for('product'), SuggestionScopes::for('category')])
        ->and($reader->fresh()->scopes)->toBe(['marketing:read'])
        ->and($revoked->fresh()->hasScope(SuggestionScopes::for('category')))->toBeFalse();

    $m->down();
    expect($writer->fresh()->scopes)->toBe(['marketing:read', SuggestionScopes::for('product')]);
});
