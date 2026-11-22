<?php

// POST /api/internal/marketing/media/upload (scope media:write): locally generated files land in the PRIVATE library as pending_review, idempotent, never public.

use App\DTOs\Marketing\MarketingTokenData;
use App\Models\Product;
use App\Models\SocialMediaAsset;
use App\Services\BarcodeService;
use App\Services\Marketing\MarketingTokenService;
use Database\Seeders\FoundationSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(FoundationSeeder::class);
    Cache::flush();
    Storage::fake(config('media.private_disk'));
    Storage::fake(config('media.disk'));
    $this->product = listProduct(Product::create(['type' => 'simple', 'name' => ['en' => 'Up item', 'ar' => 'صنف'], 'description' => ['en' => 'x'], 'slug' => 'up-item', 'sku' => 'UP-1',
        'barcode' => app(BarcodeService::class)->generateUnique()]), ['price' => '100', 'cost' => '60', 'is_active' => true]);
    $tok = fn (array $scopes) => ['Authorization' => 'Bearer '.app(MarketingTokenService::class)->create(new MarketingTokenData('t', $scopes, 30, null))[1]];
    $this->writer = $tok(['media:write']);
    $this->reader = $tok(['marketing:read']);
    $this->url = '/api/internal/marketing/media/upload';
    $this->mp4 = function () {
        file_put_contents($t = tempnam(sys_get_temp_dir(), 'mp4'), "\x00\x00\x00\x18ftypisom\x00\x00\x02\x00isomiso2mp41".str_repeat("\x00", 4096));

        return new UploadedFile($t, 'clip.mp4', 'video/mp4', null, true);
    };
});

it('needs the media:write scope', function () {
    $f = UploadedFile::fake()->image('a.png', 600, 600);
    $this->post($this->url, ['file' => $f, 'product_id' => $this->product->id, 'client_ref' => 'casting-1'], ['Accept' => 'application/json'])->assertUnauthorized();
    $this->post($this->url, ['file' => $f, 'product_id' => $this->product->id, 'client_ref' => 'casting-1'], $this->reader + ['Accept' => 'application/json'])->assertForbidden();
    expect(SocialMediaAsset::count())->toBe(0);
});

it('stores an image privately as pending_review and is idempotent by client_ref', function () {
    $h = $this->writer + ['Accept' => 'application/json'];
    $body = fn () => ['file' => UploadedFile::fake()->image('a.png', 600, 600), 'product_id' => $this->product->id, 'client_ref' => 'casting-img-1', 'title' => 'AI ad',
        'generation_meta' => json_encode(['provider' => 'comfyui', 'seed' => 5, 'evil' => 'dropped'])];
    $r = $this->post($this->url, $body(), $h)->assertCreated()->assertJsonPath('data.status', 'pending_review')->assertJsonPath('data.kind', 'image');
    $a = SocialMediaAsset::findOrFail($r->json('data.id'));
    expect($a->generation_meta)->toMatchArray(['client_ref' => 'casting-img-1', 'origin' => 'ai_casting_local', 'provider' => 'comfyui'])->and($a->generation_meta)->not->toHaveKey('evil')
        ->and($r->json('data.review_url'))->toContain('/marketer/media/'.$a->id);
    Storage::disk(config('media.private_disk'))->assertExists($a->makeVisible('path')->path);
    $again = $this->post($this->url, $body(), $h)->assertOk()->assertJsonPath('data.id', $a->id);
    expect(SocialMediaAsset::count())->toBe(1)->and($again->json('warnings.0.code'))->toBe('duplicate');
});

it('accepts a video, refuses non-media, unknown products and bad refs', function () {
    $h = $this->writer + ['Accept' => 'application/json'];
    $this->post($this->url, ['file' => ($this->mp4)(), 'product_id' => $this->product->id, 'client_ref' => 'casting-vid-1'], $h)->assertCreated()->assertJsonPath('data.kind', 'video');
    $this->post($this->url, ['file' => UploadedFile::fake()->create('x.svg', 3, 'image/svg+xml'), 'product_id' => $this->product->id, 'client_ref' => 'casting-bad-1'], $h)->assertStatus(422);
    file_put_contents($php = tempnam(sys_get_temp_dir(), 'x'), '<?php echo 1;');
    $this->post($this->url, ['file' => new UploadedFile($php, 'a.png', 'image/png', null, true), 'product_id' => $this->product->id, 'client_ref' => 'casting-bad-2'], $h)->assertStatus(422);
    $this->post($this->url, ['file' => UploadedFile::fake()->image('a.png', 600, 600), 'product_id' => 999999, 'client_ref' => 'casting-np-1'], $h)->assertNotFound();
    $this->post($this->url, ['file' => UploadedFile::fake()->image('a.png', 600, 600), 'product_id' => $this->product->id, 'client_ref' => 'bad ref!'], $h)->assertStatus(422);
    expect(SocialMediaAsset::count())->toBe(1);
});
