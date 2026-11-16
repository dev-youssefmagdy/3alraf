<?php

// Admin → Settings → System health: page, one JSON per check, permission gate, no external side effects.

use App\Models\Admin;
use App\Services\Health\HealthService;
use Database\Seeders\FoundationSeeder;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->seed(FoundationSeeder::class);
    $this->admin = Admin::query()->where('email', 'owner@egy.test')->first();
});

it('renders the page with every check card and the nav link', function () {
    $this->actingAs($this->admin, 'admin')->get('/admin/health')->assertOk()->assertSee('data-health', false)->assertSee(route('admin.health.index'), false);
    foreach (HealthService::CHECKS as $k) {
        $this->get('/admin/health')->assertSee('data-check="'.$k.'"', false);
    }
});

it('runs each check without throwing and returns status, message and ms', function () {
    Http::fake();
    $this->actingAs($this->admin, 'admin');
    foreach (HealthService::CHECKS as $k) {
        $r = $this->getJson("/admin/health/$k")->assertOk()->json();
        expect($r['key'])->toBe($k)->and($r['status'])->toBeIn(['ok', 'warn', 'fail', 'off'])->and($r['message'])->toBeString()->and($r['ms'])->toBeInt();
    }
    expect($this->getJson('/admin/health/database')->json('status'))->toBe('ok');
    $this->getJson('/admin/health/unknown')->assertNotFound();
});

it('reports not-configured services as off and a failing service as fail, never as an exception', function () {
    config(['meta.access_token' => null, 'broadcasting.default' => 'log', 'mail.default' => 'log']);
    $this->actingAs($this->admin, 'admin');
    expect($this->getJson('/admin/health/meta')->json('status'))->toBe('off')
        ->and($this->getJson('/admin/health/reverb')->json('status'))->toBe('off')
        ->and($this->getJson('/admin/health/mail')->json('status'))->toBe('warn');
    Http::fake(['*' => Http::response('', 500)]);
    config(['meta.access_token' => 'x', 'meta.ad_account_id' => '1']);
    expect($this->getJson('/admin/health/meta')->json('status'))->toBe('fail');
});

it('is closed to guests and to staff without settings.view', function () {
    $this->get('/admin/health')->assertRedirect(route('admin.login'));
    $staff = Admin::create(['name' => 'S', 'email' => 's@egy.test', 'phone' => '201077777771', 'password' => 'password', 'is_active' => true]);
    $this->actingAs($staff, 'admin')->get('/admin/health')->assertForbidden();
    $this->getJson('/admin/health/database')->assertForbidden();
});
