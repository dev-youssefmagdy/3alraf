<?php

use App\Models\Tenant;
use App\Models\TenantAdmin;
use App\Services\ImageUploadService;
use Database\Seeders\FoundationSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(FoundationSeeder::class);
    Storage::fake('media');
    Storage::fake('private');
    Storage::fake('public');
    config(['media.url' => 'https://cdn.example.test']);
    $this->tenant = Tenant::where('slug', 'egy')->first();
});

it('builds every media url from the single helper', function () {
    expect(media_url('a/b.webp'))->toBe('https://cdn.example.test/a/b.webp')
        ->and(media_url('/a/b.webp'))->toBe('https://cdn.example.test/a/b.webp')
        ->and(media_url(null))->toBeNull()
        ->and(media_url('https://x.test/i.png'))->toBe('https://x.test/i.png');
});

it('stores tenant uploads under tenants/{id} with content-hash names and catalog uploads under catalog/', function () {
    app()->instance('currentTenant', $this->tenant);
    $svc = new ImageUploadService;
    $file = UploadedFile::fake()->image('a.jpg', 900, 900);
    $r = $svc->store($file, 'banners');
    expect($r['path'])->toStartWith("tenants/{$this->tenant->id}/banners/")->and($r['path'])->toMatch('#/[0-9a-f]{32}-[a-z0-9]{8}\.webp$#')
        ->and($r['thumb_path'])->toEndWith('_thumb.webp');
    Storage::disk('media')->assertExists([$r['path'], $r['thumb_path']]);
    Storage::disk('public')->assertMissing($r['path']);
    expect($svc->store($file, 'banners')['path'])->not->toBe($r['path']); // unique per upload: two owners never share (and delete) one file

    app()->forgetInstance('currentTenant');
    app()->offsetUnset('currentTenant');
    expect($svc->store($file, 'products')['path'])->toStartWith('catalog/products/');

    $svc->deletePath($r['path']);
    Storage::disk('media')->assertMissing([$r['path'], $r['thumb_path']]);
});

it('serves private files only with a valid signature and inside the tenant namespace', function () {
    app()->instance('currentTenant', $this->tenant);
    $id = $this->tenant->id;
    Storage::disk('private')->put("tenants/$id/expenses/r.pdf", 'pdf');
    Storage::disk('private')->put('tenants/999/expenses/x.pdf', 'other');
    $this->actingAs(TenantAdmin::withoutGlobalScopes()->where('email', 'owner@egy.test')->first(), 'tenant');

    $url = private_url("tenants/$id/expenses/r.pdf");
    expect($url)->toContain('signature=');
    $this->get($url)->assertOk()->assertHeader('Cache-Control', 'no-store, private');
    $this->get(preg_replace('/signature=[^&]+/', 'signature=bad', $url))->assertForbidden();
    $this->get(private_url('tenants/999/expenses/x.pdf'))->assertForbidden();
    $this->get(private_url("tenants/$id/expenses/missing.pdf"))->assertNotFound();
});

it('migrates files from the public disk to media (receipts to private)', function () {
    Storage::disk('public')->put('products/2026/10/a.webp', 'x');
    $this->artisan('media:migrate-public-to-media', ['--dry-run' => true])->assertSuccessful();
    Storage::disk('media')->assertMissing('products/2026/10/a.webp');
    $this->artisan('media:migrate-public-to-media', ['--move' => true])->assertSuccessful();
    Storage::disk('media')->assertExists('products/2026/10/a.webp');
    Storage::disk('public')->assertMissing('products/2026/10/a.webp');
});
