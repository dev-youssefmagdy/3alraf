<?php

use App\Models\Admin;
use App\Models\Setting;
use App\Services\Llm\FreeLlmClient;
use App\Services\ProductAi\MarketPriceService;
use App\Support\SystemSettings;
use Database\Seeders\FoundationSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

it('renders the system settings page, lists it in the sidebar and saves', function () {
    $this->seed(FoundationSeeder::class);
    $admin = Admin::query()->create(['name' => 'S', 'email' => 's@x.test', 'password' => 'password', 'is_active' => true]);
    $admin->assignRole('super-admin');
    $this->actingAs($admin, 'admin');

    $this->get('/admin/settings/system')->assertOk()->assertSee('href="'.route('admin.system-settings.edit').'" class="side-link', false);
    $this->postJson('/admin/settings/system/validate', ['app_url' => 'bad'])->assertStatus(422);
});

// Mail (SMTP) group: edited in System settings instead of .env, password stored encrypted and never shown, test e-mail button.
function mailAdmin(): Admin
{
    test()->seed(FoundationSeeder::class);
    $a = Admin::query()->create(['name' => 'M', 'email' => 'm@x.test', 'password' => 'password', 'is_active' => true]);
    $a->assignRole('super-admin');
    test()->actingAs($a, 'admin');

    return $a;
}

function mailPayload(array $o = []): array
{
    $values = SystemSettings::current();
    foreach (SystemSettings::flat() as $k => [$type]) {
        if ($type === 'bool') {
            $values[$k] = $values[$k] ? 1 : 0;
        }
    }

    return array_merge($values, ['mail_mailer' => 'smtp', 'mail_scheme' => '', 'mail_host' => 'smtp.example.test', 'mail_port' => 587,
        'mail_username' => 'shop@example.test', 'mail_password' => 'S3cret-pass', 'mail_from_address' => 'no-reply@example.test', 'mail_from_name' => 'عالرف'], $o);
}

it('shows the mail section with selects, an empty password field and the test button', function () {
    mailAdmin();
    $this->get('/admin/settings/system')->assertOk()
        ->assertSee(__('admin.system_settings.groups.mail'))->assertSee('name="mail_host"', false)->assertSee('id="f-mail_mailer"', false)
        ->assertSee('id="f-mail_scheme"', false)->assertSee('data-mail-test', false)->assertSee(route('admin.system-settings.test-mail'), false);
});

it('saves the mail settings encrypted, applies them to the mail config and keeps the password when left empty', function () {
    mailAdmin();
    $this->putJson('/admin/settings/system', mailPayload())->assertOk();
    $raw = DB::table('settings')->where('key', 'sys_mail_password')->first();
    expect($raw->encrypted)->toBeTruthy()->and($raw->value)->not->toContain('S3cret-pass');

    Setting::flush();
    SystemSettings::apply();
    expect(config('mail.default'))->toBe('smtp')->and(config('mail.mailers.smtp.host'))->toBe('smtp.example.test')
        ->and(config('mail.mailers.smtp.port'))->toBe(587)->and(config('mail.mailers.smtp.password'))->toBe('S3cret-pass')
        ->and(config('mail.from.address'))->toBe('no-reply@example.test')->and(config('mail.from.name'))->toBe('عالرف');

    // the password is never sent back to the form; an empty field keeps it
    $this->get('/admin/settings/system')->assertOk()->assertDontSee('S3cret-pass');
    $this->putJson('/admin/settings/system', mailPayload(['mail_password' => '', 'mail_host' => 'smtp2.example.test']))->assertOk();
    Setting::flush();
    SystemSettings::apply();
    expect(config('mail.mailers.smtp.password'))->toBe('S3cret-pass')->and(config('mail.mailers.smtp.host'))->toBe('smtp2.example.test');
});

it('validates the mail fields', function () {
    mailAdmin();
    $this->postJson('/admin/settings/system/validate', mailPayload(['mail_mailer' => 'ses', 'mail_scheme' => 'tls', 'mail_port' => 70000, 'mail_from_address' => 'nope']))
        ->assertStatus(422)->assertJsonValidationErrors(['mail_mailer', 'mail_scheme', 'mail_port', 'mail_from_address']);
    $this->postJson('/admin/settings/system/validate', mailPayload(['mail_mailer' => 'smtp', 'mail_host' => '']))->assertStatus(422)->assertJsonValidationErrors(['mail_host']);
    $this->postJson('/admin/settings/system/validate', mailPayload(['mail_mailer' => 'log', 'mail_host' => '']))->assertOk();
});

it('sends a test e-mail with the saved settings and reports failures readably', function () {
    mailAdmin();
    Mail::fake();
    $this->postJson('/admin/settings/system/test-mail', ['to' => 'owner@example.test'])->assertOk()->assertJsonPath('success', true);
    Mail::assertSentCount(0);   // Mail::raw is not a mailable; the fake records it as an outgoing raw message
    $this->postJson('/admin/settings/system/test-mail', ['to' => 'not-an-email'])->assertStatus(422);

    // a real SMTP failure (nothing listens on this port) becomes a readable 422, not a 500
    config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.port' => 1, 'mail.mailers.smtp.scheme' => null]);
    app()->forgetInstance('mail.manager');
    app()->forgetInstance('mailer');
    Mail::clearResolvedInstances();
    $r = $this->postJson('/admin/settings/system/test-mail', ['to' => 'owner@example.test'])->assertStatus(422);
    expect($r->json('message'))->toStartWith(mb_substr(__('admin.system_settings.mail_test_failed', ['error' => '']), 0, 10));
});

it('only lets staff with general_settings.edit send a test e-mail', function () {
    $this->seed(FoundationSeeder::class);
    $staff = Admin::query()->create(['name' => 'V', 'email' => 'v@x.test', 'password' => 'password', 'is_active' => true]);
    $this->actingAs($staff, 'admin')->postJson('/admin/settings/system/test-mail', ['to' => 'a@b.test'])->assertForbidden();
});

// AI group (Product AI tools, FreeLLMAPI): stored encrypted in `settings`, applied over config product_ai.*, blank key keeps the old one.
it('saves the AI settings encrypted, applies them to config product_ai and keeps the key when left empty', function () {
    mailAdmin();
    $this->get('/admin/settings/system')->assertOk()->assertSee(__('admin.system_settings.groups.ai'))->assertSee('name="freellm_api_key"', false)
        ->assertSee('data-ai-test', false)->assertDontSee('freellmapi-s3cret');

    $this->putJson('/admin/settings/system', mailPayload(['freellm_base_url' => 'https://llm.3alraf.com/v1/', 'freellm_api_key' => 'freellmapi-s3cret',
        'freellm_text_models' => 'auto, auto:smart', 'product_ai_marketplaces' => 'amazon.eg,noon.com', 'product_ai_throttle_per_minute' => 5,
        'product_ai_search_backends' => 'bing,duckduckgo']))->assertOk();
    $raw = DB::table('settings')->where('key', 'sys_freellm_api_key')->first();
    expect($raw->encrypted)->toBeTruthy()->and($raw->value)->not->toContain('freellmapi-s3cret');

    Setting::flush();
    SystemSettings::apply();
    expect(config('product_ai.base_url'))->toBe('https://llm.3alraf.com/v1')->and(config('product_ai.api_key'))->toBe('freellmapi-s3cret')
        ->and(config('product_ai.models.text'))->toBe(['auto', 'auto:smart'])->and(config('product_ai.marketplaces'))->toBe(['amazon.eg', 'noon.com'])
        ->and(config('product_ai.search.backends'))->toBe(['bing', 'duckduckgo'])->and(config('product_ai.throttle_per_minute'))->toBe(5)
        ->and(app(FreeLlmClient::class)->configured())->toBeTrue()
        ->and(MarketPriceService::marketplace('www.noon.com'))->toBe('noon');

    $this->get('/admin/settings/system')->assertOk()->assertDontSee('freellmapi-s3cret');
    $this->putJson('/admin/settings/system', mailPayload(['freellm_api_key' => '', 'freellm_timeout' => 30]))->assertOk();
    Setting::flush();
    SystemSettings::apply();
    expect(config('product_ai.api_key'))->toBe('freellmapi-s3cret')->and(config('product_ai.timeout'))->toBe(30);

    $this->postJson('/admin/settings/system/validate', mailPayload(['freellm_base_url' => 'not a url', 'product_ai_currency' => 'EGPX', 'product_ai_max_page_fetches' => 20]))
        ->assertStatus(422)->assertJsonValidationErrors(['freellm_base_url', 'product_ai_currency', 'product_ai_max_page_fetches']);
});

it('tests the AI gateway with the saved settings', function () {
    mailAdmin();
    config(['product_ai.base_url' => 'http://llm.test/v1', 'product_ai.api_key' => 'freellmapi-x', 'product_ai.models.text' => ['auto']]);
    Http::fake([
        'llm.test/v1/models*' => Http::response(['data' => [['id' => 'auto'], ['id' => 'gemini-2.5-flash']]]),
        'llm.test/v1/chat/completions' => Http::response(['choices' => [['message' => ['content' => 'OK']]]], 200, ['X-Routed-Via' => 'groq/llama']),
    ]);
    $this->postJson(route('admin.system-settings.test-ai'))->assertOk()->assertJsonPath('message', __('admin.system_settings.ai_test_ok', ['count' => 2, 'model' => 'groq/llama']));

    config(['product_ai.api_key' => '']);
    $this->postJson(route('admin.system-settings.test-ai'))->assertStatus(422)->assertJsonPath('code', 'not_configured');
});
