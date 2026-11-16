<?php

// Marketer panel /marketer as a standalone app (ADMIN_REDESIGN_MARKETER_SPLIT_PLAN.md B1–B3): own accounts (`marketers`), guard `marketer`,
// own phone + password + WhatsApp OTP login, own roles (marketer / marketer-owner), no link and no shared session with /admin.

use App\Models\Admin;
use App\Models\Marketer;
use App\Models\MarketingApiToken;
use App\Services\Whatsapp\WhatsappService;
use App\Support\Marketing\MarketingPermissions;
use Database\Seeders\FoundationSeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

const MARKETER_PAGES = ['/marketer', '/marketer/analytics', '/marketer/gift-finder', '/marketer/gift-finder/tags', '/marketer/integration-clients', '/marketer/api-tokens', '/marketer/api-log'];

beforeEach(function () {
    $this->seed(FoundationSeeder::class);
    Cache::flush();
    $this->owner = marketerOwner();
    $this->admin = Admin::query()->where('email', 'owner@egy.test')->first();
});

it('seeds the marketing-only permissions on guard marketer only (shared tools on both), a full marketer-owner role and a preset marketer role (idempotent)', function () {
    foreach (MarketingPermissions::PANEL as $n) {
        expect(Permission::where('name', $n)->where('guard_name', 'marketer')->exists())->toBeTrue($n);
        // marketing-only permissions are gone from admins; the shared tools (Q1) exist on both guards
        $adminNames = collect(config('permissions.admin'))->flatMap(fn ($a, $mod) => array_map(fn ($x) => "$mod.$x", $a))->all();
        expect(Permission::where('name', $n)->where('guard_name', 'admin')->exists())->toBe(in_array($n, $adminNames, true), $n);
    }
    expect(Role::findByName('marketer-owner', 'marketer')->hasAllPermissions(MarketingPermissions::PANEL))->toBeTrue();
    $role = Role::findByName('marketer', 'marketer');
    expect($role->permissions->pluck('name')->sort()->values()->all())->toBe(collect(MarketingPermissions::MARKETER)->sort()->values()->all());
    // owner decision Q2: the preset role never approves, applies, publishes, issues credentials or manages accounts
    foreach (['ai_suggestions.approve', 'ai_suggestions.apply', 'ai_suggestions.reject', 'ai_media.approve', 'meta_ads.approve', 'meta_ads.publish', 'marketing.tokens.manage', 'marketers.manage'] as $p) {
        expect($role->hasPermissionTo($p))->toBeFalse($p);
    }
    $role->revokePermissionTo('gift_finder.manage');
    (new PermissionSeeder)->run();
    expect(Role::findByName('marketer', 'marketer')->hasPermissionTo('gift_finder.manage'))->toBeFalse()
        ->and(Role::where('name', 'marketer')->count())->toBe(1)
        ->and(Role::where('guard_name', 'admin')->where('name', 'marketer')->exists())->toBeFalse();
});

it('renders every page for the marketer-owner in Arabic and English with no link into the admin panel', function () {
    $this->actingAs($this->owner, 'marketer');
    foreach (MARKETER_PAGES as $url) {
        $this->get($url)->assertOk()->assertSee('لوحة التسويق')->assertSee('data-panel="marketer"', false)
            ->assertDontSee('data-admin-panel-link', false)->assertDontSee('/admin/', false)->assertSee(route('marketer.logout'), false);
    }
    $this->get('/marketer')->assertSee('dir="rtl"', false)->assertSee(route('marketer.tokens.index'), false)->assertSee(route('marketer.suggestions.index'), false);
    $this->withSession(['marketer_locale' => 'en']);
    foreach (MARKETER_PAGES as $url) {
        $this->get($url)->assertOk()->assertSee('Marketing panel');
    }
    $this->get('/marketer?from=garbage')->assertOk();
});

it('keeps the two panels apart: an admin session never opens /marketer and a marketer session never opens /admin', function () {
    $this->actingAs($this->admin, 'admin');
    $this->get('/marketer')->assertRedirect(route('marketer.login'));
    $this->get('/admin/welcome')->assertOk()->assertDontSee('/marketer', false);
    auth('admin')->logout();

    $this->actingAs($this->owner, 'marketer');
    $this->get('/admin/welcome')->assertRedirect(route('admin.login'));
    $this->get('/admin/products')->assertRedirect(route('admin.login'));
    auth('marketer')->logout();

    $this->get('/marketer')->assertRedirect(route('marketer.login'));
    $this->get('/marketer/api-tokens')->assertRedirect(route('marketer.login'));
    expect(Route::has('admin.moved.gift-finder'))->toBeFalse();
    $this->actingAs($this->admin, 'admin')->get('/admin/gift-finder')->assertNotFound();
});

it('gates each page by its own permission; the preset role sees marketing work but no credentials', function () {
    $nobody = makeMarketer([], null, 'n@egy.test');
    foreach (MARKETER_PAGES as $url) {
        $this->actingAs($nobody, 'marketer')->get($url)->assertForbidden();
    }
    $gift = makeMarketer(['gift_finder.view'], null, 'g@egy.test');
    $this->actingAs($gift, 'marketer')->get('/marketer')->assertRedirect(route('marketer.gift-finder.index'));
    foreach (['/marketer/api-tokens', '/marketer/api-log', '/marketer/integration-clients'] as $url) {
        $this->actingAs($gift, 'marketer')->get($url)->assertForbidden();
    }

    $m = makeMarketer([], 'marketer', 'm@egy.test');
    $this->actingAs($m, 'marketer');
    $this->get('/marketer')->assertOk()->assertSee('data-kpi="gross_profit"', false)->assertDontSee(route('marketer.tokens.index'), false);
    $this->get('/marketer/integration-clients')->assertOk()->assertDontSee('action="'.route('marketer.integration-clients.store').'"', false);
    $this->get('/marketer/api-tokens')->assertForbidden();
    $this->get('/marketer/api-log')->assertForbidden();
    $this->postJson('/marketer/integration-clients', ['name' => 'x', 'scopes' => ['read']])->assertForbidden();
});

it('logs a marketer in with phone + password + WhatsApp OTP on its own routes; admin credentials are refused there', function () {
    config(['services.whatsapp' => ['url' => 'http://wa.test', 'token' => 'wa-secret-token-xyz', 'callback_secret' => 'sekret']]);
    Http::fake(['wa.test/*' => Http::response(['status' => 'connected'])]);
    app(WhatsappService::class)->session()->update(['status' => 'connected', 'phone' => '201111111111']);
    $m = makeMarketer([], 'marketer', 'otp@egy.test');
    $national = substr($m->phone, 2);

    // the admin owner's phone + password do not exist in `marketers`
    $this->postJson('/marketer/login', ['country' => 'EG', 'phone' => substr($this->admin->phone, 2), 'password' => 'password'])->assertStatus(422)->assertJsonValidationErrors('phone');
    $this->postJson('/marketer/login', ['country' => 'EG', 'phone' => $national, 'password' => 'wrong'])->assertStatus(422);

    $this->postJson('/marketer/login', ['country' => 'EG', 'phone' => $national, 'password' => 'password'])->assertOk()->assertJsonPath('redirect', url('/marketer/login/otp'));
    expect(auth('marketer')->check())->toBeFalse();
    $this->get('/marketer/login/otp')->assertOk()->assertSee(route('marketer.login.otp.verify'), false);
    $text = collect(Http::recorded(fn (HttpRequest $r) => str_ends_with($r->url(), '/messages')))->map(fn ($p) => $p[0])->last()->data()['text'];
    preg_match('/\d{4,10}/', $text, $code);
    $this->postJson('/marketer/login/otp', ['code' => $code[0]])->assertOk()->assertJsonPath('redirect', route('marketer.dashboard'));
    expect(auth('marketer')->id())->toBe($m->id)->and(auth('admin')->check())->toBeFalse()
        ->and($m->fresh()->last_login_at)->not->toBeNull();
    $this->get('/marketer/login')->assertRedirect(route('marketer.dashboard'));

    $this->post('/marketer/logout')->assertRedirect(route('marketer.login'));
    expect(auth('marketer')->check())->toBeFalse();
    // an inactive marketer is logged out on the next request
    $this->actingAs($m, 'marketer');
    $m->update(['is_active' => false]);
    $this->get('/marketer')->assertRedirect(route('marketer.login'));
});

it('creates marketers from the console, owner or not', function () {
    $this->artisan('marketer:create', ['--name' => 'Nour', '--phone' => '01055555555', '--owner' => true])
        ->expectsQuestion('Password (min 8 chars)', 'secret-pass')->assertSuccessful();
    $n = Marketer::where('phone', '201055555555')->firstOrFail();
    expect($n->isOwner())->toBeTrue()->and(Hash::check('secret-pass', $n->password))->toBeTrue();
    $this->artisan('marketer:create', ['--phone' => '01055555556'])->expectsQuestion('Password (min 8 chars)', 'short')->assertFailed();
});

it('creates a token once (hash stored), lists it and revokes it from the panel; the API follows', function () {
    $this->actingAs($this->owner, 'marketer');
    $this->postJson('/marketer/api-tokens/validate', ['name' => '', 'scopes' => []])->assertStatus(422)->assertJsonValidationErrors(['name', 'scopes']);
    $this->postJson('/marketer/api-tokens', ['name' => 'x', 'scopes' => ['admin:all']])->assertStatus(422);
    $this->postJson('/marketer/api-tokens', ['name' => 'x', 'scopes' => ['marketing:read'], 'expires_in_days' => '7'])->assertStatus(422);
    $r = $this->postJson('/marketer/api-tokens', ['name' => 'MCP prod', 'scopes' => ['marketing:read'], 'expires_in_days' => '90'])->assertCreated()->json('data');
    $row = MarketingApiToken::findOrFail($r['id']);
    expect($row->token_hash)->toBe(hash('sha256', $r['token']))->and($row->created_by)->toBe($this->owner->id)
        ->and((int) round(now()->diffInDays($row->expires_at)))->toBe(90);
    $this->getJson('/marketer/api-tokens/datatable?draw=1&start=0&length=10')->assertOk()->assertJsonPath('recordsTotal', 1)->assertDontSee($r['token'], false);

    $api = fn () => $this->flushHeaders()->withHeaders(['Authorization' => 'Bearer '.$r['token'], 'Accept' => 'application/json'])->getJson('/api/internal/marketing/overview');
    $api()->assertOk();
    $this->actingAs($this->owner, 'marketer')->getJson('/marketer/api-log/datatable?draw=1&start=0&length=10')->assertOk()->assertJsonPath('recordsTotal', 1)->assertJsonPath('data.0.route', 'marketing-api.overview');

    $this->flushHeaders()->actingAs($this->owner, 'marketer')->postJson('/marketer/api-tokens/'.$r['id'].'/revoke')->assertOk();
    expect($row->fresh()->isRevoked())->toBeTrue();
    $api()->assertUnauthorized();
});

it('manages marketer accounts inside the panel: owner only, one role each, never locks the last owner out', function () {
    $this->actingAs($this->owner, 'marketer');
    $this->get('/marketer/accounts')->assertOk()->assertSee('data-marketers', false)->assertSee('Marketing Owner');
    $this->postJson('/marketer/accounts/validate', ['name' => '', 'country' => 'EG', 'phone' => '', 'role' => 'admin'])->assertStatus(422)->assertJsonValidationErrors(['name', 'phone', 'role', 'password']);
    $this->postJson('/marketer/accounts', ['name' => 'Nour', 'country' => 'EG', 'phone' => '01066666666', 'password' => 'password1', 'role' => 'marketer', 'is_active' => 1])->assertOk();
    $nour = Marketer::where('phone', '201066666666')->firstOrFail();
    expect($nour->hasRole('marketer'))->toBeTrue()->and($nour->isOwner())->toBeFalse();
    // the same phone may exist as an admin: the tables are separate
    $this->postJson('/marketer/accounts', ['name' => 'Dup', 'country' => 'EG', 'phone' => '01066666666', 'password' => 'password1', 'role' => 'marketer'])->assertStatus(422);

    // cannot demote / deactivate / delete yourself; the last active owner stays
    $this->putJson('/marketer/accounts/'.$this->owner->id, ['name' => 'X', 'country' => 'EG', 'phone' => '01000000201', 'role' => 'marketer', 'is_active' => 1])->assertStatus(422);
    $this->deleteJson('/marketer/accounts/'.$this->owner->id)->assertStatus(422);
    $this->putJson('/marketer/accounts/'.$nour->id, ['name' => 'Nour', 'country' => 'EG', 'phone' => '01066666666', 'role' => 'marketer-owner', 'is_active' => 1])->assertOk();
    $this->putJson('/marketer/accounts/'.$this->owner->id, ['name' => 'X', 'country' => 'EG', 'phone' => '01000000201', 'role' => 'marketer-owner', 'is_active' => 1])->assertOk();
    $this->deleteJson('/marketer/accounts/'.$nour->id)->assertOk();
    expect(Marketer::find($nour->id))->toBeNull();

    // the preset role cannot see or manage accounts
    $this->actingAs(makeMarketer([], 'marketer', 'p@egy.test'), 'marketer');
    $this->get('/marketer/accounts')->assertForbidden();
    $this->postJson('/marketer/accounts', ['name' => 'Z', 'country' => 'EG', 'phone' => '01077777777', 'password' => 'password1', 'role' => 'marketer-owner'])->assertForbidden();
});

it('serves the shared marketing tools inside the marketer panel with its own routes, layout and guard', function () {
    $this->actingAs($this->owner, 'marketer');
    foreach (['banners', 'discount-codes', 'flash-sales', 'bundles', 'blog-posts', 'static-pages', 'custom-pages'] as $uri) {
        $this->get("/marketer/$uri")->assertOk()->assertSee('data-panel="marketer"', false)->assertSee(route("marketer.$uri.datatable"), false)->assertDontSee('/admin/', false);
        $this->get("/marketer/$uri/create")->assertOk()->assertSee(route("marketer.$uri.store"), false)->assertDontSee('/admin/', false);
        $this->getJson("/marketer/$uri/datatable?draw=1&start=0&length=10")->assertOk();
    }
    foreach (['/marketer/seo', '/marketer/affiliates', '/marketer/product-lists', '/marketer/reviews'] as $url) {
        $this->get($url)->assertOk()->assertSee('data-panel="marketer"', false)->assertDontSee('/admin/', false);
    }
    $this->getJson('/marketer/affiliates/datatable?draw=1&start=0&length=10')->assertOk();
    $this->getJson('/marketer/reviews/datatable?draw=1&start=0&length=10')->assertOk();
    // the same pages stay in /admin for admins
    $this->actingAs($this->admin, 'admin')->get('/admin/banners')->assertOk()->assertSee(route('admin.banners.datatable'), false);
    // a marketer without the tool permission is refused
    $this->actingAs(makeMarketer(['marketing.view'], null, 'mv@egy.test'), 'marketer')->get('/marketer/banners')->assertForbidden();
});
