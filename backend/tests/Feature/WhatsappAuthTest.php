<?php

use App\Models\Admin;
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
    config(['services.whatsapp' => ['url' => 'http://wa.test', 'token' => 'wa-secret-token-xyz', 'callback_secret' => 'sekret']]);
    Http::fake(['wa.test/*' => Http::response(['status' => 'connected'])]);
});

/** Mark the store's WhatsApp as connected (what a Node "connected" event does). */
function waConnected(): void
{
    app(WhatsappService::class)->session()->update(['status' => 'connected', 'phone' => '201111111111']);
}

function regularAdmin(string $phone = '201000000002'): Admin
{
    return Admin::create(['name' => 'Staff', 'phone' => $phone, 'password' => 'password', 'is_active' => true]);
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

it('logs an otp_exempt admin (the demo super admin, like the old central one) in with phone + password and no OTP', function () {
    loginAs('', '01000000001')->assertOk()->assertJson(['success' => true]);
    $this->get("/admin")->assertOk();
    expect(sentMessages())->toBeEmpty();
});

it('requires an OTP through the single WhatsApp session (id central) for admins without otp_exempt', function () {
    waConnected();
    regularAdmin();
    loginAs('', '01000000002')->assertOk()->assertJsonPath('redirect', url('/admin/login/otp'));
    $this->get("/admin")->assertRedirect("/admin/login"); // not authenticated yet

    $sent = sentMessages();
    expect($sent)->toHaveCount(1)->and($sent[0]->url())->toContain('/sessions/central/messages')->and($sent[0]->data()['to'])->toBe('201000000002');

    $this->postJson("/admin/login/otp", ['code' => '000000'])->assertStatus(422);
    $this->postJson("/admin/login/otp", ['code' => lastOtp()])->assertOk();
    $this->get("/admin")->assertOk();
});

it('otp_exempt is per account: switching it on lets a regular admin skip the OTP, switching it off brings it back', function () {
    waConnected();
    $a = regularAdmin();
    $a->update(['otp_exempt' => true]);
    loginAs('', '01000000002')->assertOk()->assertJsonPath('redirect', url('/admin/welcome'));
    expect(sentMessages())->toBeEmpty();
    $this->post('/admin/logout');
    $a->update(['otp_exempt' => false]);
    loginAs('', '01000000002')->assertOk()->assertJsonPath('redirect', url('/admin/login/otp'));
    expect(sentMessages())->toHaveCount(1);
});

it('fails (no session) when the WhatsApp account is not connected', function () {
    regularAdmin('201000000003');
    loginAs('', '01000000003')->assertStatus(422)->assertJsonValidationErrors('phone');
    expect(sentMessages())->toBeEmpty();
});

it('does not reveal whether a phone exists', function () {
    $wrongPass = $this->postJson("/admin/login", ['country' => 'EG', 'phone' => '01000000001', 'password' => 'nope']);
    $unknown = $this->postJson("/admin/login", ['country' => 'EG', 'phone' => '01099999999', 'password' => 'nope']);
    expect($wrongPass->status())->toBe(422)->and($unknown->json())->toEqual($wrongPass->json());
});

it('locks out after too many failed passwords using the DB setting', function () {
    AuthSettings::for()->set('login.max_failures', 3);
    RateLimiter::clear('x');
    foreach (range(1, 3) as $i) {
        $this->postJson("/admin/login", ['country' => 'EG', 'phone' => '01000000001', 'password' => 'bad'])->assertStatus(422);
    }
    loginAs('', '01000000001')->assertStatus(422); // correct password but locked
});

it('expires OTPs and enforces single use', function () {
    waConnected();
    $admin = regularAdmin();
    $otp = app(OtpService::class);
    $otp->issue($admin, '127.0.0.1');
    $code = lastOtp();
    expect($otp->verify($admin, $code))->toBe('ok')->and($otp->verify($admin, $code))->toBe('expired'); // consumed

    $this->travel(1)->minutes();
    $otp->issue($admin, null);
    $code = lastOtp();
    $this->travel(AuthSettings::for()->get('otp.ttl_seconds') + 1)->seconds();
    expect($otp->verify($admin, $code))->toBe('expired');
});

it('locks an OTP after the maximum attempts, even for the right code, and resends do not reset attempts', function () {
    waConnected();
    $admin = regularAdmin();
    AuthSettings::for()->set('otp.max_attempts', 3);
    AuthSettings::for()->set('otp.resend_cooldown_seconds', 10);
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
    waConnected();
    $admin = regularAdmin();
    AuthSettings::for()->set('otp.max_resends', 1);
    AuthSettings::for()->set('otp.resend_cooldown_seconds', 30);
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
    waConnected();
    AuthSettings::for()->set('otp.length', 8);
    app(OtpService::class)->issue(regularAdmin(), null);
    expect(strlen(lastOtp()))->toBe(8);
    expect(DB::table('auth_settings')->where('key', 'otp.length')->exists())->toBeTrue()
        ->and(\Illuminate\Support\Facades\Schema::hasColumn('auth_settings', 'tenant_id'))->toBeFalse();
});

it('allows only one super admin at the database level and through the provisioner', function () {
    expect(fn () => Admin::create(['name' => 'x', 'phone' => '201000000009', 'password' => 'password'])->forceFill(['is_super_admin' => true])->save())
        ->toThrow(Illuminate\Database\QueryException::class);
    expect(fn () => app(App\Services\Auth\SuperAdminProvisioner::class)->provision('Intruder', '201000000066', 'password'))->toThrow(Illuminate\Validation\ValidationException::class);
    expect(Admin::query()->where('is_super_admin', true)->count())->toBe(1);
    // re-provisioning the same phone is idempotent
    app(App\Services\Auth\SuperAdminProvisioner::class)->provision('Super Admin', '201000000001', 'password');
    expect(Admin::query()->where('is_super_admin', true)->count())->toBe(1);
    // like the former platform super admin: signs in without a WhatsApp OTP (QC Phase 10)
    expect(Admin::query()->where('is_super_admin', true)->value('otp_exempt'))->toBeTruthy();
});

it('cannot make a super admin through mass assignment', function () {
    $a = Admin::create(['name' => 'x', 'phone' => '201000000008', 'password' => 'password', 'is_super_admin' => true]);
    expect($a->fresh()->is_super_admin)->toBeFalse();
});

it('keeps WhatsApp management behind login and permission', function () {
    $this->get("/admin/whatsapp")->assertRedirect("/admin/login");
    $this->postJson("/admin/whatsapp/connect")->assertUnauthorized();
    $staff = regularAdmin();
    $this->actingAs($staff, 'admin')->get("/admin/whatsapp")->assertForbidden();
    $this->actingAs(Admin::where('is_super_admin', true)->first(), 'admin')->get("/admin/whatsapp")->assertOk()->assertSee('QR');
});

it('manages the single connection (session id central) and never leaks credentials', function () {
    $owner = Admin::query()->where('is_super_admin', true)->first();
    app()->forgetInstance(Illuminate\Http\Client\Factory::class);
    Http::clearResolvedInstances();
    Http::fake(['wa.test/*' => Http::response(['status' => 'connecting', 'session_id' => 'x'], 202)]);
    $this->actingAs($owner, 'admin')->postJson("/admin/whatsapp/connect")->assertOk()->assertJsonPath('data.status', 'connecting');
    Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/sessions/central/connect') && $r->hasHeader('Authorization', 'Bearer wa-secret-token-xyz'));
    Http::assertNotSent(fn (Request $r) => str_contains($r->url(), '/sessions/tenant-'));
    $page = $this->actingAs($owner, 'admin')->get("/admin/whatsapp")->assertOk()->getContent();
    expect($page)->not->toContain('wa-secret-token-xyz')->not->toContain('storage_ref');
});

it('applies signed Node events to the single session, rejects forgeries and ignores retired/unknown ids', function () {
    $post = function (array $body, ?string $sig = null, ?int $ts = null) {
        $raw = json_encode($body);
        $ts ??= time();
        $sig ??= hash_hmac('sha256', "$ts.$raw", 'sekret');

        return test()->call('POST', "/internal/whatsapp/events", [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_WA_TIMESTAMP' => (string) $ts, 'HTTP_X_WA_SIGNATURE' => $sig, 'HTTP_ACCEPT' => 'application/json'], $raw);
    };
    $ev = fn ($id, $status, $extra = []) => ['session_id' => $id, 'type' => 'status', 'data' => ['status' => $status] + $extra];

    $post($ev('central', 'connected'), 'bad')->assertUnauthorized();
    $post($ev('central', 'connected'), null, time() - 3600)->assertUnauthorized();

    $post($ev('central', 'qr', ['qr' => 'data:image/png;base64,AAAA']))->assertOk();
    $post($ev('central', 'connected', ['phone' => '201222222222']))->assertOk();
    $s = WhatsappSession::where('session_id', 'central')->first();
    expect($s->status)->toBe('connected')->and($s->phone)->toBe('201222222222')->and($s->last_connected_at)->not->toBeNull();

    $post($ev('tenant-1', 'disconnected'))->assertOk();   // retired per-tenant id: ignored
    $post($ev('../etc', 'connected'))->assertOk();
    expect(WhatsappSession::count())->toBe(1)->and($s->fresh()->status)->toBe('connected');

    $post($ev('central', 'disconnected', ['error' => 'closed']))->assertOk();
    expect($s->fresh()->last_error)->toBe('closed')->and($s->fresh()->status)->not->toBe('connected');
});

it('reports a Node outage as a failed connection instead of crashing', function () {
    app()->forgetInstance(Illuminate\Http\Client\Factory::class);
    Http::clearResolvedInstances();
    Http::fake(['wa.test/*' => fn () => throw new Illuminate\Http\Client\ConnectionException('down')]);
    $owner = Admin::query()->where('is_super_admin', true)->first();
    $this->actingAs($owner, 'admin')->postJson("/admin/whatsapp/connect")->assertStatus(422);
    expect(WhatsappSession::current()->status)->toBe('error');
});

it('registers the single whatsapp / whatsapp-chat broadcast channels (guard admin)', function () {
    $a = Admin::query()->where('is_super_admin', true)->first();
    $chan = collect(Illuminate\Support\Facades\Broadcast::getChannels())->keys()->all();
    expect($chan)->toContain('whatsapp', 'whatsapp-chat', 'App.Models.Admin.{id}')->not->toContain('whatsapp.central')->not->toContain('whatsapp.tenant.{tenantId}');
    expect($a->can('whatsapp.view'))->toBeTrue();
});

it('paces throttled sends and enforces the hourly limit, while login codes stay unlimited', function () {
    waConnected();
    AuthSettings::for()->set('whatsapp.hourly_limit', 1);
    AuthSettings::for()->set('whatsapp.min_gap', 2);
    $wa = app(WhatsappService::class);

    $wa->sendText('201111111111', 'hello', throttled: true);
    expect(sentMessages()[0]->data())->toMatchArray(['humanize' => true, 'min_gap_ms' => 2000]);
    expect(fn () => $wa->sendText('201111111111', 'again', throttled: true))->toThrow(App\Services\Whatsapp\WhatsappUnavailable::class);

    $wa->sendText('201111111111', 'otp'); // instant path ignores the quota
    expect(sentMessages()[1]->data())->not->toHaveKey('humanize');
});
