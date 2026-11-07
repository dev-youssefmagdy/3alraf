<?php

/*
 | Defaults for the site-wide `settings` table (App\Models\Setting, helper setting()), written once when the table is first filled
 | (conversion migration on an empty install). Edited afterwards on Settings → General; these values are not read at runtime.
 */
return [
    'defaults' => [
        'name' => env('SETTINGS_NAME', env('APP_NAME', 'Store')),
        'country' => env('SETTINGS_COUNTRY', 'EG'),
        'currency_code' => env('SETTINGS_CURRENCY', 'EGP'),
        'currency_symbol' => env('SETTINGS_CURRENCY_SYMBOL', 'EGP'),
        'default_locale' => env('SETTINGS_LOCALE', 'ar'),
        'locales' => array_values(array_filter(explode(',', (string) env('SETTINGS_LOCALES', 'ar,en')))),
        'timezone' => env('SETTINGS_TIMEZONE', 'Africa/Cairo'),
    ],
];
