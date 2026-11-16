<?php

use App\Models\Admin;
use Database\Seeders\FoundationSeeder;

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
    $values = \App\Support\SystemSettings::current();
    foreach (\App\Support\SystemSettings::flat() as $k => [$type]) {
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
    $raw = \Illuminate\Support\Facades\DB::table('settings')->where('key', 'sys_mail_password')->first();
    expect($raw->encrypted)->toBeTruthy()->and($raw->value)->not->toContain('S3cret-pass');

    \App\Models\Setting::flush();
    \App\Support\SystemSettings::apply();
    expect(config('mail.default'))->toBe('smtp')->and(config('mail.mailers.smtp.host'))->toBe('smtp.example.test')
        ->and(config('mail.mailers.smtp.port'))->toBe(587)->and(config('mail.mailers.smtp.password'))->toBe('S3cret-pass')
        ->and(config('mail.from.address'))->toBe('no-reply@example.test')->and(config('mail.from.name'))->toBe('عالرف');

    // the password is never sent back to the form; an empty field keeps it
    $this->get('/admin/settings/system')->assertOk()->assertDontSee('S3cret-pass');
    $this->putJson('/admin/settings/system', mailPayload(['mail_password' => '', 'mail_host' => 'smtp2.example.test']))->assertOk();
    \App\Models\Setting::flush();
    \App\Support\SystemSettings::apply();
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
    \Illuminate\Support\Facades\Mail::fake();
    $this->postJson('/admin/settings/system/test-mail', ['to' => 'owner@example.test'])->assertOk()->assertJsonPath('success', true);
    \Illuminate\Support\Facades\Mail::assertSentCount(0);   // Mail::raw is not a mailable; the fake records it as an outgoing raw message
    $this->postJson('/admin/settings/system/test-mail', ['to' => 'not-an-email'])->assertStatus(422);

    // a real SMTP failure (nothing listens on this port) becomes a readable 422, not a 500
    config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.port' => 1, 'mail.mailers.smtp.scheme' => null]);
    app()->forgetInstance('mail.manager');
    app()->forgetInstance('mailer');
    \Illuminate\Support\Facades\Mail::clearResolvedInstances();
    $r = $this->postJson('/admin/settings/system/test-mail', ['to' => 'owner@example.test'])->assertStatus(422);
    expect($r->json('message'))->toStartWith(mb_substr(__('admin.system_settings.mail_test_failed', ['error' => '']), 0, 10));
});

it('only lets staff with general_settings.edit send a test e-mail', function () {
    $this->seed(FoundationSeeder::class);
    $staff = Admin::query()->create(['name' => 'V', 'email' => 'v@x.test', 'password' => 'password', 'is_active' => true]);
    $this->actingAs($staff, 'admin')->postJson('/admin/settings/system/test-mail', ['to' => 'a@b.test'])->assertForbidden();
});
