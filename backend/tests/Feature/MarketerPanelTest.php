<?php

// P4 (MARKETER_PLAN.md): marketer panel /marketer — pages render (ar + en) for the owner, 403 without permission, preset role `marketer`
// sees only marketing, token management (create once / revoke), old /admin URLs 301 to the panel, admin nav link, permissions seeded.

use App\Models\Admin;
use App\Models\MarketingApiToken;
use App\Support\Marketing\MarketingPermissions;
use Database\Seeders\FoundationSeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

const MARKETER_PAGES = ['/marketer', '/marketer/analytics', '/marketer/gift-finder', '/marketer/gift-finder/tags', '/marketer/integration-clients', '/marketer/api-tokens', '/marketer/api-log'];

beforeEach(function () {
    $this->seed(FoundationSeeder::class);
    Cache::flush();
    $this->owner = Admin::query()->where('email', 'owner@egy.test')->first();
    $this->staff = function (array $perms = [], ?string $role = null, string $email = 's@egy.test') {
        $a = Admin::create(['name' => 'S', 'email' => $email, 'password' => 'password', 'is_active' => true]);
        $perms && $a->givePermissionTo($perms);
        $role && $a->assignRole($role);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $a->fresh();
    };
});

it('seeds the marketing permissions on guard admin, gives them all to owner, and creates the marketer role once (idempotent)', function () {
    $names = ['marketing.view', 'marketing.analytics', 'marketing.tokens.manage', 'marketing.audit.view', 'ai_suggestions.approve', 'ai_suggestions.apply', 'ai_media.upload',
        'ai_seo.approve', 'ai_blog.approve', 'ai_theme.approve', 'meta_ads.publish', 'gift_finder.manage', 'integration_clients.manage'];
    foreach ($names as $n) {
        expect(Permission::where('name', $n)->where('guard_name', 'admin')->exists())->toBeTrue($n);
    }
    expect(Role::findByName('owner', 'admin')->hasAllPermissions(MarketingPermissions::PANEL))->toBeTrue();
    $role = Role::findByName('marketer', 'admin');
    expect($role->permissions->pluck('name')->sort()->values()->all())->toBe(collect(MarketingPermissions::MARKETER)->sort()->values()->all())
        ->and($role->hasPermissionTo('marketing.tokens.manage'))->toBeFalse()->and($role->hasPermissionTo('orders.view'))->toBeFalse();

    // owner edits the preset role: re-seeding keeps the edit
    $role->revokePermissionTo('gift_finder.manage');
    (new PermissionSeeder)->run();
    expect(Role::findByName('marketer', 'admin')->hasPermissionTo('gift_finder.manage'))->toBeFalse()
        ->and(Role::where('name', 'marketer')->count())->toBe(1);
});

it('renders every marketer page for the owner in Arabic and English, with the panel title, nav and the admin link', function () {
    $this->actingAs($this->owner, 'admin');
    foreach (MARKETER_PAGES as $url) {
        $this->get($url)->assertOk()->assertSee('لوحة التسويق')->assertSee('data-panel="marketer"', false)->assertSee('data-admin-panel-link', false);
    }
    $this->get('/marketer')->assertSee('dir="rtl"', false)->assertSee(route('admin.banners.index'), false)->assertSee(route('marketer.tokens.index'), false)
        ->assertSee('data-pending-suggestions', false)->assertSee(route('marketer.suggestions.index'), false);   // P5: Suggestions Center live
    $this->withSession(['admin_locale' => 'en']);
    foreach (MARKETER_PAGES as $url) {
        $this->get($url)->assertOk()->assertSee('Marketing panel');
    }
    $this->get('/marketer?from=2026-01-01&to=2026-01-31')->assertOk()->assertSee('dir="ltr"', false)->assertSee('2026-01-31', false);
    $this->get('/marketer?from=garbage')->assertOk();
    // admin nav links to the panel; the moved items are gone from the admin nav
    $this->get('/admin/welcome')->assertOk()->assertSee(route('marketer.dashboard'), false)->assertDontSee('/admin/gift-finder', false)->assertDontSee('/admin/integration-clients', false);
});

it('denies the panel without a marketing permission and gates each page by its own permission', function () {
    $nobody = ($this->staff)(['orders.view']);
    foreach (MARKETER_PAGES as $url) {
        $this->actingAs($nobody, 'admin')->get($url)->assertForbidden();
    }
    $this->actingAs($nobody, 'admin')->get('/admin/welcome')->assertOk()->assertDontSee(route('marketer.dashboard'), false);

    // only gift_finder.view: dashboard sends to the gift finder, tokens / log / clients are forbidden
    $gift = ($this->staff)(['gift_finder.view'], null, 'g@egy.test');
    $this->actingAs($gift, 'admin')->get('/marketer')->assertRedirect(route('marketer.gift-finder.index'));
    $this->actingAs($gift, 'admin')->get('/marketer/gift-finder')->assertOk()->assertDontSee('data-admin-panel-link', false);
    foreach (['/marketer/api-tokens', '/marketer/api-log', '/marketer/integration-clients'] as $url) {
        $this->actingAs($gift, 'admin')->get($url)->assertForbidden();
    }
    auth('admin')->logout();
    $this->get('/marketer')->assertRedirect(route('admin.login'));
    $this->get('/marketer/api-tokens')->assertRedirect(route('admin.login'));
});

it('the marketer role sees only marketing: panel pages, no admin modules, no credential pages, profit block shown', function () {
    $m = ($this->staff)([], 'marketer', 'm@egy.test');
    $this->actingAs($m, 'admin');
    $this->get('/marketer')->assertOk()->assertSee('data-kpi="gross_profit"', false)->assertDontSee('data-admin-panel-link', false)
        ->assertDontSee(route('admin.banners.index'), false)->assertDontSee(route('marketer.tokens.index'), false);
    $this->get('/marketer/gift-finder')->assertOk();
    $this->get('/marketer/integration-clients')->assertOk()->assertDontSee('action="'.route('marketer.integration-clients.store').'"', false);
    $this->get('/marketer/api-tokens')->assertForbidden();
    $this->get('/marketer/api-log')->assertForbidden();
    $this->postJson('/marketer/integration-clients', ['name' => 'x', 'scopes' => ['read']])->assertForbidden();
    foreach (['/admin/orders', '/admin/products', '/admin/banners', '/admin/settings/general', '/admin/reports'] as $url) {
        $this->get($url)->assertForbidden();
    }
    // the admin shell offers only the way to the marketing panel
    $this->get('/admin/welcome')->assertOk()->assertSee(route('marketer.dashboard'), false)->assertDontSee(route('admin.orders.index'), false);
});

it('creates a token once (hash stored), lists it and revokes it from the panel; the API follows', function () {
    $this->actingAs($this->owner, 'admin');
    $this->postJson('/marketer/api-tokens/validate', ['name' => '', 'scopes' => []])->assertStatus(422)->assertJsonValidationErrors(['name', 'scopes']);
    $this->postJson('/marketer/api-tokens', ['name' => 'x', 'scopes' => ['admin:all']])->assertStatus(422);   // unknown scope (the per-type suggestions:write:* scopes are grantable)
    $this->postJson('/marketer/api-tokens', ['name' => 'x', 'scopes' => ['marketing:read'], 'expires_in_days' => '7'])->assertStatus(422);
    $r = $this->postJson('/marketer/api-tokens', ['name' => 'MCP prod', 'scopes' => ['marketing:read'], 'expires_in_days' => '90'])->assertCreated()->json('data');
    $row = MarketingApiToken::findOrFail($r['id']);
    expect($row->token_hash)->toBe(hash('sha256', $r['token']))->and($row->created_by)->toBe($this->owner->id)
        ->and((int) round(now()->diffInDays($row->expires_at)))->toBe(90);
    $this->getJson('/marketer/api-tokens/datatable?draw=1&start=0&length=10')->assertOk()->assertJsonPath('recordsTotal', 1)->assertDontSee($r['token'], false);

    $api = fn () => $this->flushHeaders()->withHeaders(['Authorization' => 'Bearer '.$r['token'], 'Accept' => 'application/json'])->getJson('/api/internal/marketing/overview');
    $api()->assertOk();   // the token middleware forgets the guards: act as the owner again for panel calls
    $this->actingAs($this->owner, 'admin')->getJson('/marketer/api-log/datatable?draw=1&start=0&length=10')->assertOk()->assertJsonPath('recordsTotal', 1)->assertJsonPath('data.0.route', 'marketing-api.overview');

    $this->flushHeaders()->actingAs($this->owner, 'admin')->postJson('/marketer/api-tokens/'.$r['id'].'/revoke')->assertOk();
    expect($row->fresh()->isRevoked())->toBeTrue();
    $api()->assertUnauthorized();
    $this->flushHeaders()->actingAs($this->owner, 'admin')->postJson('/marketer/api-tokens', ['name' => 'never', 'scopes' => ['marketing:read'], 'expires_in_days' => 'never'])->assertCreated();
    expect(MarketingApiToken::where('name', 'never')->value('expires_at'))->toBeNull();
});

it('301-redirects the old admin Gift Finder and integration-client URLs to the marketer panel', function () {
    $this->actingAs($this->owner, 'admin');
    $this->get('/admin/gift-finder')->assertStatus(301)->assertRedirect('/marketer/gift-finder');
    $this->get('/admin/gift-finder/tags?x=1')->assertStatus(301)->assertRedirect('/marketer/gift-finder/tags?x=1');
    $this->get('/admin/integration-clients')->assertStatus(301)->assertRedirect('/marketer/integration-clients');
    expect(Route::has('admin.gift-finder.index'))->toBeFalse()->and(Route::has('admin.integration-clients.index'))->toBeFalse()
        ->and(Route::has('marketer.gift-finder.index'))->toBeTrue()->and(Route::has('marketer.integration-clients.index'))->toBeTrue();
});
