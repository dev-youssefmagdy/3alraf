<?php

use App\Models\CentralAdmin;
use App\Models\Tenant;
use App\Models\TenantAdmin;
use App\Models\WhatsappSession;
use App\Services\Auth\AuthSettings;
use App\Services\Auth\OtpService;
use App\Services\Whatsapp\WhatsappService;
use Database\Seeders\FoundationSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function () {
    $this->seed(FoundationSeeder::class);
    $this->central = 'http://3alraf.test';
    $this->egy = 'http://egy.3alraf.test';
    config(['services.whatsapp' => ['url' => 'http://wa.test', 'token' => 'wa-secret-token-xyz', 'callback_secret' => 'sekret']]);
    Http::fake(['wa.test/*' => Http::response(['status' => 'connected'])]);
    $this->tenant = Tenant::where('slug', 'egy')->first();
});

/** Mark an owner's WhatsApp as connected (what a Node "connected" event does). */
function waConnected(?int $tenantId): void
{
    app(WhatsappService::class)->session($tenantId)->update(['status' => 'connected', 'phone' => '201111111111']);
}

function regularCentral(string $phone = '201000000002'): CentralAdmin
{
    return CentralAdmin::create(['name' => 'Staff', 'phone' => $phone, 'password' => 'password', 'is_active' => true]);
}

function regularTenant(Tenant $t, string $phone = '201000000003'): TenantAdmin
{
    return TenantAdmin::withoutGlobalScopes()->create(['tenant_id' => $t->id, 'name' => 'Staff', 'phone' => $phone, 'password' => 'password', 'is_active' => true]);
}

function sentMessages(): array
{
    return Http::recorded(fn (Request $r) => str_ends_with($r->url(), '/messages'))->map(fn ($p) => $p[0])->values()->all();
}

function lastOtp(): string
{
    $m = collect(sentMessages())->last();
    preg_match('/\d{4,10}/', $m->data()['text'], $x);

    return $x[0];
}

function loginAs(string $host, string $national): Illuminate\Testing\TestResponse
{
    return test()->postJson("$host/admin/login", ['country' => 'EG', 'phone' => $national, 'password' => 'password']);
}

it('logs the central super admin in with phone + password and no OTP', function () {
    loginAs($this->central, '01000000001')->assertOk()->assertJson(['success' => true]);
    $this->get("{$this->central}/admin")->assertOk();
    expect(sentMessages())->toBeEmpty();
});

it('requires an OTP from the central WhatsApp account for regular central admins', function () {
    waConnected(null);
    regularCentral();
    loginAs($this->central, '01000000002')->assertOk()->assertJsonPath('redirect', "{$this->central}/admin/login/otp");
    $this->get("{$this->central}/admin")->assertRedirect("{$this->central}/admin/login"); // not authenticated yet

    $sent = sentMessages();
    expect($sent)->toHaveCount(1)->and($sent[0]->url())->toContain('/sessions/central/messages')->and($sent[0]->data()['to'])->toBe('201000000002');

    $this->postJson("{$this->central}/admin/login/otp", ['code' => '000000'])->assertStatus(422);
    $this->postJson("{$this->central}/admin/login/otp", ['code' => lastOtp()])->assertOk();
    $this->get("{$this->central}/admin")->assertOk();
});

it('logs the tenant super admin in without OTP', function () {
    $phone = TenantAdmin::withoutGlobalScopes()->where('is_super_admin', true)->value('phone');
    loginAs($this->egy, '0'.substr($phone, 2))->assertOk();
    $this->get("{$this->egy}/admin")->assertOk();
    expect(sentMessages())->toBeEmpty();
});

it('sends tenant OTPs only through that tenant\'s own WhatsApp session, never the central one', function () {
    $other = Tenant::create(['slug' => 'sa', 'name' => 'SA', 'locales' => ['ar'], 'default_locale' => 'ar', 'country' => 'EG']);
    waConnected($this->tenant->id);
    waConnected($other->id);
    regularTenant($this->tenant);

    loginAs($this->egy, '01000000003')->assertOk()->assertJsonPath('redirect', "{$this->egy}/admin/login/otp");
    $sent = sentMessages();
    expect($sent)->toHaveCount(1)->and($sent[0]->url())->toContain("/sessions/tenant-{$this->tenant->id}/messages")
        ->and($sent[0]->url())->not->toContain('central')->not->toContain("tenant-{$other->id}");

    $this->postJson("{$this->egy}/admin/login/otp", ['code' => lastOtp()])->assertOk();
    $this->get("{$this->egy}/admin")->assertOk();
});

it('does not deliver a tenant OTP when only the central account is connected', function () {
    waConnected(null);
    regularTenant($this->tenant);
    loginAs($this->egy, '01000000003')->assertStatus(422)->assertJsonValidationErrors('phone');
    expect(sentMessages())->toBeEmpty();
});

it('cannot log a tenant admin in on another tenant\'s host', function () {
    $other = Tenant::create(['slug' => 'sa', 'name' => 'SA', 'locales' => ['ar'], 'default_locale' => 'ar', 'country' => 'EG']);
    waConnected($other->id);
    regularTenant($this->tenant);
    loginAs('http://sa.3alraf.test', '01000000003')->assertStatus(422);
});

it('does not reveal whether a phone exists', function () {
    $wrongPass = $this->postJson("{$this->central}/admin/login", ['country' => 'EG', 'phone' => '01000000001', 'password' => 'nope']);
    $unknown = $this->postJson("{$this->central}/admin/login", ['country' => 'EG', 'phone' => '01099999999', 'password' => 'nope']);
    expect($wrongPass->status())->toBe(422)->and($unknown->json())->toEqual($wrongPass->json());
});

it('locks out after too many failed passwords using the DB setting', function () {
    AuthSettings::for(null)->set('login.max_failures', 3);
    RateLimiter::clear('x');
    foreach (range(1, 3) as $i) {
        $this->postJson("{$this->central}/admin/login", ['country' => 'EG', 'phone' => '01000000001', 'password' => 'bad'])->assertStatus(422);
    }
    loginAs($this->central, '01000000001')->assertStatus(422); // correct password but locked
});

it('expires OTPs and enforces single use', function () {
    waConnected(null);
    $admin = regularCentral();
    $otp = app(OtpService::class);
    $otp->issue($admin, '127.0.0.1');
    $code = lastOtp();
    expect($otp->verify($admin, $code))->toBe('ok')->and($otp->verify($admin, $code))->toBe('expired'); // consumed

    $this->travel(1)->minutes();
    $otp->issue($admin, null);
    $code = lastOtp();
    $this->travel(AuthSettings::for(null)->get('otp.ttl_seconds') + 1)->seconds();
    expect($otp->verify($admin, $code))->toBe('expired');
});

it('locks an OTP after the maximum attempts, even for the right code, and resends do not reset attempts', function () {
    waConnected(null);
    $admin = regularCentral();
    AuthSettings::for(null)->set('otp.max_attempts', 3);
    AuthSettings::for(null)->set('otp.resend_cooldown_seconds', 10);
    $otp = app(OtpService::class);
    $otp->issue($admin, null);
    $code = lastOtp();
    expect($otp->verify($admin, '111111'))->toBe('invalid')->and($otp->verify($admin, '222222'))->toBe('invalid');
    $this->travel(11)->seconds();
    $otp->issue($admin, null); // resend: attempts carry over
    $code = lastOtp();
    expect($otp->verify($admin, '333333'))->toBe('locked')->and($otp->verify($admin, $code))->toBe('locked');
});

it('enforces the resend cooldown and the max resends', function () {
    waConnected(null);
    $admin = regularCentral();
    AuthSettings::for(null)->set('otp.max_resends', 1);
    AuthSettings::for(null)->set('otp.resend_cooldown_seconds', 30);
    $otp = app(OtpService::class);
    $otp->issue($admin, null);
    expect(fn () => $otp->issue($admin, null))->toThrow(App\Services\Auth\OtpRefused::class);
    $this->travel(31)->seconds();
    $otp->issue($admin, null); // resend 1
    $this->travel(31)->seconds();
    expect(fn () => $otp->issue($admin, null))->toThrow(App\Services\Auth\OtpRefused::class);
    expect(sentMessages())->toHaveCount(2);
});

it('reads OTP rules from the database, not from config', function () {
    waConnected(null);
    AuthSettings::for(null)->set('otp.length', 8);
    app(OtpService::class)->issue(regularCentral(), null);
    expect(strlen(lastOtp()))->toBe(8);
    expect(DB::table('auth_settings')->where('key', 'otp.length')->whereNull('tenant_id')->exists())->toBeTrue();
    // tenants keep independent settings
    expect(AuthSettings::for($this->tenant->id)->get('otp.length'))->toBe(6);
});

it('allows only one super admin per scope at the database level', function () {
    expect(fn () => CentralAdmin::create(['name' => 'x', 'phone' => '201000000009', 'password' => 'password'])->forceFill(['is_super_admin' => true])->save())
        ->toThrow(Illuminate\Database\QueryException::class);
    $t = TenantAdmin::withoutGlobalScopes();
    expect(fn () => $t->create(['tenant_id' => $this->tenant->id, 'name' => 'x', 'phone' => '201000000009', 'password' => 'password'])->forceFill(['is_super_admin' => true])->save())
        ->toThrow(Illuminate\Database\QueryException::class);
    // another tenant may have its own
    $other = Tenant::create(['slug' => 'sa', 'name' => 'SA', 'locales' => ['ar'], 'default_locale' => 'ar']);
    app(App\Services\Auth\SuperAdminProvisioner::class)->provision($other->id, 'Owner', '201000000077', 'password');
    expect(TenantAdmin::withoutGlobalScopes()->where('is_super_admin', true)->count())->toBe(2);
    expect(fn () => app(App\Services\Auth\SuperAdminProvisioner::class)->provision(null, 'Intruder', '201000000066', 'password'))->toThrow(Illuminate\Validation\ValidationException::class);
});

it('cannot make a super admin through mass assignment', function () {
    $a = CentralAdmin::create(['name' => 'x', 'phone' => '201000000008', 'password' => 'password', 'is_super_admin' => true]);
    expect($a->fresh()->is_super_admin)->toBeFalse();
});

it('keeps WhatsApp management behind login and permission', function () {
    $this->get("{$this->central}/admin/whatsapp")->assertRedirect("{$this->central}/admin/login");
    $this->postJson("{$this->central}/admin/whatsapp/connect")->assertUnauthorized();
    $staff = regularCentral();
    $this->actingAs($staff, 'central')->get("{$this->central}/admin/whatsapp")->assertForbidden();
    $this->actingAs(CentralAdmin::where('is_super_admin', true)->first(), 'central')->get("{$this->central}/admin/whatsapp")->assertOk()->assertSee('QR');
});

it('manages the tenant connection only for the current tenant and never leaks credentials', function () {
    $owner = TenantAdmin::withoutGlobalScopes()->where('is_super_admin', true)->first();
    app()->forgetInstance(Illuminate\Http\Client\Factory::class);
    Http::clearResolvedInstances();
    Http::fake(['wa.test/*' => Http::response(['status' => 'connecting', 'session_id' => 'x'], 202)]);
    $this->actingAs($owner, 'tenant')->postJson("{$this->egy}/admin/whatsapp/connect")->assertOk()->assertJsonPath('data.status', 'connecting');
    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), "/sessions/tenant-{$this->tenant->id}/connect") && $r->hasHeader('Authorization', 'Bearer wa-secret-token-xyz'));
    Http::assertNotSent(fn (Request $r) => str_contains($r->url(), '/sessions/central'));
    $page = $this->actingAs($owner, 'tenant')->get("{$this->egy}/admin/whatsapp")->assertOk()->getContent();
    expect($page)->not->toContain('wa-secret-token-xyz')->not->toContain('storage_ref');
});

it('applies signed Node events, rejects forgeries, and keeps owners separate', function () {
    $post = function (array $body, ?string $sig = null, ?int $ts = null) {
        $raw = json_encode($body);
        $ts ??= time();
        $sig ??= hash_hmac('sha256', "$ts.$raw", 'sekret');

        return test()->call('POST', "{$this->central}/internal/whatsapp/events", [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_WA_TIMESTAMP' => (string) $ts, 'HTTP_X_WA_SIGNATURE' => $sig, 'HTTP_ACCEPT' => 'application/json'], $raw);
    };
    $ev = fn ($id, $status, $extra = []) => ['session_id' => $id, 'type' => 'status', 'data' => ['status' => $status] + $extra];

    $post($ev('central', 'connected'), 'bad')->assertUnauthorized();
    $post($ev('central', 'connected'), null, time() - 3600)->assertUnauthorized();

    $post($ev("tenant-{$this->tenant->id}", 'qr', ['qr' => 'data:image/png;base64,AAAA']))->assertOk();
    $post($ev("tenant-{$this->tenant->id}", 'connected', ['phone' => '201222222222']))->assertOk();
    $s = WhatsappSession::where('tenant_id', $this->tenant->id)->first();
    expect($s->status)->toBe('connected')->and($s->phone)->toBe('201222222222')->and($s->last_connected_at)->not->toBeNull();
    expect(WhatsappSession::whereNull('tenant_id')->exists())->toBeFalse(); // central untouched

    $post($ev('tenant-99999', 'connected'))->assertOk(); // unknown tenant ignored
    $post($ev('../etc', 'connected'))->assertOk();
    expect(WhatsappSession::count())->toBe(1);

    $post($ev('central', 'disconnected', ['error' => 'closed']))->assertOk();
    expect(WhatsappSession::whereNull('tenant_id')->first()->last_error)->toBe('closed')->and($s->fresh()->status)->toBe('connected');
});

it('reports a Node outage as a failed connection instead of crashing', function () {
    app()->forgetInstance(Illuminate\Http\Client\Factory::class);
    Http::clearResolvedInstances();
    Http::fake(['wa.test/*' => fn () => throw new Illuminate\Http\Client\ConnectionException('down')]);
    $owner = TenantAdmin::withoutGlobalScopes()->where('is_super_admin', true)->first();
    $this->actingAs($owner, 'tenant')->postJson("{$this->egy}/admin/whatsapp/connect")->assertStatus(422);
    expect(WhatsappSession::where('tenant_id', $this->tenant->id)->first()->status)->toBe('error');
});

it('authorises broadcast channels per owner', function () {
    $a = TenantAdmin::withoutGlobalScopes()->where('is_super_admin', true)->first();
    $chan = fn ($name) => collect(Illuminate\Support\Facades\Broadcast::getChannels())->keys();
    expect($chan('x')->all())->toContain('whatsapp.central', 'whatsapp.tenant.{tenantId}');
    expect($a->can('whatsapp.view'))->toBeTrue();
});

it('paces throttled sends and enforces the hourly limit, while login codes stay unlimited', function () {
    waConnected(null);
    AuthSettings::for(null)->set('whatsapp.hourly_limit', 1);
    AuthSettings::for(null)->set('whatsapp.min_gap', 2);
    $wa = app(WhatsappService::class);

    $wa->sendText(null, '201111111111', 'hello', throttled: true);
    expect(sentMessages()[0]->data())->toMatchArray(['humanize' => true, 'min_gap_ms' => 2000]);
    expect(fn () => $wa->sendText(null, '201111111111', 'again', throttled: true))->toThrow(App\Services\Whatsapp\WhatsappUnavailable::class);

    $wa->sendText(null, '201111111111', 'otp'); // instant path ignores the quota
    expect(sentMessages()[1]->data())->not->toHaveKey('humanize');
});
