<?php

use App\Models\Setting;
use App\Services\SystemSettingsService;
use App\Support\SystemSettings;

it('applies stored values over config and keeps secrets when left blank', function () {
    Setting::put('sys_meta_max_daily_budget', 123.0, true);
    Setting::put('sys_meta_access_token', 'tok', true);
    Setting::flush();
    SystemSettings::apply();
    expect(config('meta.max_daily_budget'))->toBe(123.0)->and(config('meta.access_token'))->toBe('tok');

    app(SystemSettingsService::class)->update(['meta_access_token' => '', 'meta_page_id' => '42'], null);
    SystemSettings::apply();
    expect(config('meta.access_token'))->toBe('tok')->and(config('meta.page_id'))->toBe('42');
})->uses(Tests\TestCase::class, Illuminate\Foundation\Testing\RefreshDatabase::class);
