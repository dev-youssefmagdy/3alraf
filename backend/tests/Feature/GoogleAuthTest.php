<?php

use App\Models\Cart;
use App\Models\Customer;
use App\Models\Tenant;
use Database\Seeders\FoundationSeeder;
use Illuminate\Support\Facades\Cache;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialUser;

beforeEach(function () {
    $this->seed(FoundationSeeder::class);
    config(['services.google.client_id' => 'cid.apps.googleusercontent.com', 'services.google.client_secret' => 'secret']);
    $this->tenant = Tenant::where('slug', 'egy')->first();
    app()->instance('currentTenant', $this->tenant);
    $this->host = 'http://egy.3alraf.test';
    $this->central = 'http://3alraf.test';
    $this->base = Customer::count();
    $this->svc = app(\App\Services\Auth\GoogleAuthService::class);

    $this->guser = fn (array $o = []) => (new SocialUser)->setRaw(['email_verified' => $o['verified'] ?? true])->map([
        'id' => $o['sub'] ?? 'g-1', 'name' => $o['name'] ?? 'Gina Google', 'email' => $o['email'] ?? 'gina@example.com', 'avatar' => 'https://lh3.example/a.png',
    ]);
    $this->mockGoogle = function (?SocialUser $u, bool $throw = false) {
        $p = Mockery::mock();
        $p->shouldReceive('stateless')->andReturnSelf();
        $p->shouldReceive('redirectUrl')->andReturnSelf();
        $p->shouldReceive('with')->andReturnUsing(function ($a) use ($p) { $p->q = $a; return $p; });
        $p->shouldReceive('redirect')->andReturnUsing(fn () => redirect()->away('https://accounts.google.com/o?'.http_build_query($p->q)));
        $throw ? $p->shouldReceive('user')->andThrow(new RuntimeException('boom')) : $p->shouldReceive('user')->andReturn($u);
        Socialite::shouldReceive('driver')->with('google')->andReturn($p);
    };
    // hop 1: tenant start -> [state token, nonce]
    $this->start = function (string $host = null, string $query = '') {
        auth('web')->logout();
        $r = $this->get(($host ?? $this->host).'/ar/auth/google'.$query)->assertRedirect();
        parse_str(parse_url($r->headers->get('Location'), PHP_URL_QUERY), $q);

        return [$q['state'], json_decode(base64_decode(strtr(explode('.', $q['state'])[0], '-_', '+/')), true)['n'], $r];
    };
    // hop 2: central callback -> handoff token (or the response when no tenant redirect)
    $this->callback = function (string $state, array $q = ['code' => 'abc']) {
        $r = $this->get($this->central.'/auth/google/callback?'.http_build_query(['state' => $state] + $q));
        $loc = $r->headers->get('Location');
        parse_str((string) parse_url((string) $loc, PHP_URL_QUERY), $o);

        return [$o['token'] ?? null, $r, $loc];
    };
    // full dance
    $this->dance = function (?SocialUser $u, string $query = '') {
        [$state, $nonce] = ($this->start)(null, $query);
        ($this->mockGoogle)($u);
        [$token] = ($this->callback)($state);

        return $this->withSession(['google_nonce' => $nonce])->get($this->host.'/ar/auth/google/complete?token='.$token);
    };
});

it('hides the button and 404s the routes when Google is not configured', function () {
    config(['services.google.client_id' => null]);
    $this->get($this->host.'/ar/login')->assertOk()->assertDontSee('data-google-auth', false);
    $this->get($this->host.'/ar/register')->assertOk()->assertDontSee('data-google-auth', false);
    $this->get($this->host.'/ar/auth/google')->assertNotFound();
    $this->get($this->host.'/ar/auth/google/complete?token=x')->assertNotFound();
    $this->get($this->central.'/auth/google/callback?state=x')->assertNotFound();
    $this->postJson($this->host.'/api/v1/auth/google', ['id_token' => 'x'])->assertNotFound();
});

it('shows the button on login and register when configured', function () {
    $this->get($this->host.'/ar/login')->assertOk()->assertSee('data-google-auth', false)->assertSee(__('store.google.continue', [], 'ar'));
    $this->get($this->host.'/ar/register')->assertOk()->assertSee('/ar/auth/google', false);
});

it('starts the flow with a signed state bound to the tenant and sends the callback to the central host', function () {
    [$state, $nonce, $r] = ($this->start)();
    $loc = $r->headers->get('Location');
    expect($loc)->toStartWith('https://accounts.google.com/')->and($loc)->toContain(urlencode('http://3alraf.test/auth/google/callback'));
    $r->assertSessionHas('google_nonce', $nonce);
    $d = $this->svc->verify('state', $state);
    expect($d['t'])->toBe('egy')->and($d['tid'])->toBe($this->tenant->id)->and($d['l'])->toBe('ar');
});

it('registers a new customer with a verified Google account and logs them in', function () {
    $r = ($this->dance)(($this->guser)());
    $r->assertRedirect(route('storefront.home', ['tenant' => 'egy', 'locale' => 'ar']));
    $c = Customer::where('email', 'gina@example.com')->first();
    expect($c)->not->toBeNull()->and($c->google_id)->toBe('g-1')->and($c->is_active)->toBeTrue()->and($c->email_verified_at)->not->toBeNull()->and($c->getRawOriginal('password'))->toBeNull()
        ->and($c->tenant_id)->toBe($this->tenant->id);
    expect(auth('web')->id())->toBe($c->id);
});

it('links an existing customer by a verified e-mail and keeps their password', function () {
    $existing = Customer::create(['name' => 'Old', 'email' => 'gina@example.com', 'password' => 'secret-pass-1']);
    $existing->forceFill(['email_verified_at' => now()])->save();
    ($this->dance)(($this->guser)())->assertRedirect();
    $existing->refresh();
    expect($existing->google_id)->toBe('g-1')->and(Customer::count())->toBe($this->base + 1)->and(\Hash::check('secret-pass-1', $existing->password))->toBeTrue();
    expect(auth('web')->id())->toBe($existing->id);
});

it('kills the password of a never-verified account when it gets linked (pre-registration takeover)', function () {
    $existing = Customer::create(['name' => 'Old', 'email' => 'gina@example.com', 'password' => 'attacker-pass']);
    ($this->dance)(($this->guser)())->assertRedirect();
    $existing->refresh();
    expect($existing->google_id)->toBe('g-1')->and($existing->email_verified_at)->not->toBeNull()->and($existing->getRawOriginal('password'))->toBeNull();
});

it('refuses an unverified Google e-mail: no link, no account, not logged in', function () {
    $existing = Customer::create(['name' => 'Old', 'email' => 'gina@example.com', 'password' => 'secret-pass-1']);
    ($this->dance)(($this->guser)(['verified' => false]))->assertRedirect(route('storefront.login', ['tenant' => 'egy', 'locale' => 'ar']))
        ->assertSessionHas('google_error', __('store.google.unverified', [], 'ar'));
    expect($existing->refresh()->google_id)->toBeNull()->and(Customer::count())->toBe($this->base + 1);
    ($this->dance)(($this->guser)(['verified' => false, 'sub' => 'g-2', 'email' => 'new@example.com']))->assertSessionHas('google_error');
    expect(Customer::where('email', 'new@example.com')->exists())->toBeFalse()->and(auth('web')->check())->toBeFalse();
});

it('logs a returning google_id in even after the e-mail changed on the Google side', function () {
    $c = Customer::create(['name' => 'G', 'email' => 'first@example.com', 'password' => 'x-pass-1234']);
    $c->forceFill(['google_id' => 'g-1'])->save();
    ($this->dance)(($this->guser)(['email' => 'renamed@example.com']))->assertRedirect(route('storefront.home', ['tenant' => 'egy', 'locale' => 'ar']));
    expect(auth('web')->id())->toBe($c->id)->and(Customer::count())->toBe($this->base + 1);
});

it('refuses inactive customers (by google_id and by e-mail) and anonymised accounts', function () {
    $c = Customer::create(['name' => 'G', 'email' => 'gina@example.com', 'password' => 'x-pass-1234', 'is_active' => false]);
    ($this->dance)(($this->guser)())->assertSessionHas('google_error', __('store.google.inactive', [], 'ar'));
    $c->forceFill(['google_id' => 'g-1'])->save();
    ($this->dance)(($this->guser)())->assertSessionHas('google_error', __('store.google.inactive', [], 'ar'));
    expect(auth('web')->check())->toBeFalse();
});

it('never matches a soft-deleted (tombstoned) customer and frees its google_id', function () {
    $c = Customer::create(['name' => 'Gone', 'email' => 'gina@example.com', 'password' => 'x-pass-1234']);
    $c->forceFill(['google_id' => 'g-1'])->save();
    $c->delete();
    expect($c->getRawOriginal('email'))->toContain('#del')->and($c->getRawOriginal('google_id'))->toContain('#del');
    ($this->dance)(($this->guser)())->assertRedirect(route('storefront.home', ['tenant' => 'egy', 'locale' => 'ar']));
    $new = Customer::where('email', 'gina@example.com')->first();
    expect($new->id)->not->toBe($c->id)->and($new->google_id)->toBe('g-1');
});

it('refuses a tampered, foreign-signed, expired and replayed state', function () {
    [$state, $nonce] = ($this->start)();
    ($this->mockGoogle)(($this->guser)());
    // tamper: swap tenant in payload keeping the old signature
    [$p, $s] = explode('.', $state);
    $forged = rtrim(strtr(base64_encode(str_replace('"t":"egy"', '"t":"evil"', base64_decode(strtr($p, '-_', '+/')))), '+/', '-_'), '=').'.'.$s;
    $this->get($this->central.'/auth/google/callback?state='.$forged.'&code=a')->assertStatus(400);
    $this->get($this->central.'/auth/google/callback?state=garbage&code=a')->assertStatus(400);
    $this->get($this->central.'/auth/google/callback?code=a')->assertStatus(400);
    // a handoff-purpose token is not a state
    $h = $this->svc->sign('handoff', ['t' => 'egy', 'tid' => $this->tenant->id, 'l' => 'ar', 'i' => null, 'n' => $nonce], 60);
    $this->get($this->central.'/auth/google/callback?state='.$h.'&code=a')->assertStatus(400);
    // replay: first use ok, second refused
    [$token, $r1] = ($this->callback)($state);
    expect($token)->not->toBeNull();
    $this->get($this->central.'/auth/google/callback?state='.$state.'&code=a')->assertStatus(400);
    // expiry
    [$state2] = ($this->start)();
    $this->travel(6)->minutes();
    $this->get($this->central.'/auth/google/callback?state='.$state2.'&code=a')->assertStatus(400);
});

it('refuses a replayed handoff, a handoff from another browser, an expired one and one for another tenant', function () {
    [$state, $nonce] = ($this->start)();
    ($this->mockGoogle)(($this->guser)());
    [$token] = ($this->callback)($state);
    $url = $this->host.'/ar/auth/google/complete?token='.$token;
    // other browser (no / wrong nonce)
    $this->withSession(['google_nonce' => 'x'.$nonce])->get($url)->assertRedirect(route('storefront.login', ['tenant' => 'egy', 'locale' => 'ar']))->assertSessionHas('google_error');
    expect(Customer::count())->toBe($this->base);
    // genuine use, then replay
    $this->withSession(['google_nonce' => $nonce])->get($url)->assertSessionMissing('google_error');
    auth('web')->logout();
    $this->withSession(['google_nonce' => $nonce])->get($url)->assertSessionHas('google_error');
    expect(Customer::count())->toBe($this->base + 1);

    // expired (> 60 s)
    [$state] = ($this->start)();
    [$token, , ] = ($this->callback)($state);
    [, $nonce2] = [null, json_decode(base64_decode(strtr(explode('.', $state)[0], '-_', '+/')), true)['n']];
    $this->travel(61)->seconds();
    $this->withSession(['google_nonce' => $nonce2])->get($this->host.'/ar/auth/google/complete?token='.$token)->assertSessionHas('google_error');
    expect(Customer::count())->toBe($this->base + 1);
    $this->travelBack();

    // wrong tenant: a token minted for egy used on another tenant
    $other = Tenant::create(['slug' => 'ksa', 'name' => 'KSA', 'country' => 'SA', 'currency_code' => 'SAR', 'currency_symbol' => 'SAR', 'currency_rate' => 1, 'default_locale' => 'ar', 'locales' => ['ar', 'en'], 'timezone' => 'Asia/Riyadh', 'is_active' => true]);
    $tok = $this->svc->sign('handoff', ['t' => 'egy', 'tid' => $this->tenant->id, 'l' => 'ar', 'i' => null, 'n' => 'nn', 'sub' => 'g-9', 'email' => 'x@example.com', 'ev' => true, 'name' => 'X'], 60);
    app()->instance('currentTenant', $other);
    $this->withSession(['google_nonce' => 'nn'])->get('http://ksa.3alraf.test/ar/auth/google/complete?token='.$tok)->assertSessionHas('google_error');
    app()->instance('currentTenant', $this->tenant);
    expect(Customer::withoutGlobalScopes()->where('email', 'x@example.com')->exists())->toBeFalse();
});

it('is open-redirect safe: only same-host relative paths survive', function () {
    foreach (['https://evil.com/x', '//evil.com', '/\\evil.com', 'javascript:alert(1)', 'http://egy.3alraf.test.evil.com/', "/ok\r\nLocation: x", 'http://user@evil.com/'] as $bad) {
        expect($this->svc->safePath($bad, 'egy.3alraf.test'))->toBeNull($bad);
    }
    expect($this->svc->safePath('/ar/checkout?a=1', 'egy.3alraf.test'))->toBe('/ar/checkout?a=1')
        ->and($this->svc->safePath('http://egy.3alraf.test/ar/checkout', 'egy.3alraf.test'))->toBe('/ar/checkout');

    // a forged handoff carrying an evil intended URL (cannot be signed, but a signed one with a bad value is still neutralised)
    $r = ($this->dance)(($this->guser)(), '?intended='.urlencode('https://evil.com/phish'));
    $r->assertRedirect(route('storefront.home', ['tenant' => 'egy', 'locale' => 'ar']));
});

it('returns to the intended page (checkout redirect for guests) after login', function () {
    $r = ($this->dance)(($this->guser)(), '?intended='.urlencode('/ar/checkout'));
    $r->assertRedirect($this->host.'/ar/checkout');
});

it('uses the session url.intended set by the auth redirect', function () {
    $r = $this->withSession(['url.intended' => $this->host.'/ar/checkout'])->get($this->host.'/ar/auth/google')->assertRedirect();
    parse_str(parse_url($r->headers->get('Location'), PHP_URL_QUERY), $q);
    expect($this->svc->verify('state', $q['state'])['i'])->toBe('/ar/checkout');
});

it('handles a denied or failed Google callback gracefully (no 500)', function () {
    [$state, $nonce] = ($this->start)();
    [$token, , $loc] = ($this->callback)($state, ['error' => 'access_denied']);
    expect($loc)->toStartWith($this->host.'/ar/auth/google/complete');
    $this->withSession(['google_nonce' => $nonce])->get($loc)->assertRedirect(route('storefront.login', ['tenant' => 'egy', 'locale' => 'ar']))
        ->assertSessionHas('google_error', __('store.google.denied', [], 'ar'));

    [$state, $nonce] = ($this->start)();
    ($this->mockGoogle)(null, true);
    [, , $loc] = ($this->callback)($state);
    $this->withSession(['google_nonce' => $nonce])->get($loc)->assertSessionHas('google_error', __('store.google.failed', [], 'ar'));
});

it('rejects unknown or inactive tenants in the state at the central callback', function () {
    $s = $this->svc->sign('state', ['t' => 'nope', 'tid' => 999, 'l' => 'ar', 'i' => null, 'n' => 'n'], 300);
    ($this->mockGoogle)(($this->guser)());
    $this->get($this->central.'/auth/google/callback?state='.$s.'&code=a')->assertNotFound();
    $this->tenant->update(['is_active' => false]);
    $s = $this->svc->sign('state', ['t' => 'egy', 'tid' => $this->tenant->id, 'l' => 'ar', 'i' => null, 'n' => 'n'], 300);
    $this->get($this->central.'/auth/google/callback?state='.$s.'&code=a')->assertNotFound();
});

it('keeps the guest cart token across the Google login so the cart merges', function () {
    $guest = Cart::create(['token' => 'tok-guest']);
    [$state, $nonce] = ($this->start)();
    ($this->mockGoogle)(($this->guser)());
    [$token] = ($this->callback)($state);
    $this->withSession(['google_nonce' => $nonce, 'cart_token' => 'tok-guest'])->get($this->host.'/ar/auth/google/complete?token='.$token)
        ->assertSessionHas('cart_token', 'tok-guest');
    $c = Customer::where('email', 'gina@example.com')->first();
    app(\App\Services\Orders\CartService::class)->cart(false); // same code path as the password login: adopts the guest cart for the signed-in customer
    expect($guest->refresh()->customer_id)->toBe($c->id);
});

it('keeps tenants isolated: one Google account = two customers on two tenants', function () {
    ($this->dance)(($this->guser)())->assertRedirect();
    $a = Customer::where('email', 'gina@example.com')->first();
    auth('web')->logout();

    $other = Tenant::create(['slug' => 'ksa', 'name' => 'KSA', 'country' => 'SA', 'currency_code' => 'SAR', 'currency_symbol' => 'SAR', 'currency_rate' => 1, 'default_locale' => 'ar', 'locales' => ['ar', 'en'], 'timezone' => 'Asia/Riyadh', 'is_active' => true]);
    app()->instance('currentTenant', $other);
    [$state, $nonce] = ($this->start)('http://ksa.3alraf.test');
    ($this->mockGoogle)(($this->guser)());
    [$token] = ($this->callback)($state);
    $this->withSession(['google_nonce' => $nonce])->get('http://ksa.3alraf.test/ar/auth/google/complete?token='.$token)->assertRedirect();
    $b = Customer::withoutGlobalScopes()->where('tenant_id', $other->id)->where('google_id', 'g-1')->first();
    expect($b)->not->toBeNull()->and($b->id)->not->toBe($a->id)->and(Customer::withoutGlobalScopes()->where('google_id', 'g-1')->count())->toBe(2);
});

it('cannot log in with a password on a Google-only account and the profile can set one', function () {
    ($this->dance)(($this->guser)())->assertRedirect();
    auth('web')->logout();
    $this->postJson($this->host.'/ar/login', ['email' => 'gina@example.com', 'password' => 'anything-123'])->assertStatus(422);
    $this->postJson($this->host.'/api/v1/auth/login', ['email' => 'gina@example.com', 'password' => 'anything-123'])->assertStatus(422);
    $c = Customer::where('email', 'gina@example.com')->first();
    $this->actingAs($c, 'web')->putJson($this->host.'/ar/account/profile', ['name' => 'Gina', 'email' => 'gina@example.com', 'password' => 'new-pass-1234', 'password_confirmation' => 'new-pass-1234'])->assertOk();
    expect(\Hash::check('new-pass-1234', $c->refresh()->password))->toBeTrue();
});

it('signs in on the mobile API with a verified Google ID token', function () {
    \Http::fake(['oauth2.googleapis.com/*' => \Http::sequence()
        ->push(['aud' => 'cid.apps.googleusercontent.com', 'iss' => 'https://accounts.google.com', 'exp' => time() + 600, 'sub' => 'g-7', 'email' => 'mob@example.com', 'email_verified' => 'true', 'name' => 'Mob'])
        ->push(['aud' => 'someone-else', 'iss' => 'accounts.google.com', 'exp' => time() + 600, 'sub' => 'g-8', 'email' => 'x@example.com', 'email_verified' => 'true'])
        ->push(['error' => 'invalid'], 400)]);
    $this->postJson($this->host.'/api/v1/auth/google', ['id_token' => 'tok'])->assertOk()->assertJsonPath('data.customer.email', 'mob@example.com')->assertJsonStructure(['data' => ['token']]);
    expect(Customer::where('google_id', 'g-7')->exists())->toBeTrue();

    $this->postJson($this->host.'/api/v1/auth/google', ['id_token' => 'tok'])->assertStatus(422);
    $this->postJson($this->host.'/api/v1/auth/google', ['id_token' => 'tok'])->assertStatus(422);
});
