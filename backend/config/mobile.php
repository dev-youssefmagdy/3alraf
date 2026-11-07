<?php

/** Mobile app (Flutter) runtime config served by GET /api/v1/app-config and the `store` endpoint. All values overridable from .env. */
return [
    // Semantic versions (x.y.z). The app compares its own version: < min_version = forced update, < latest_version = soft prompt.
    'min_version' => env('MOBILE_MIN_VERSION', '1.0.0'),
    'latest_version' => env('MOBILE_LATEST_VERSION', '1.0.0'),

    'store_urls' => [
        'ios' => env('MOBILE_STORE_URL_IOS', ''),
        'android' => env('MOBILE_STORE_URL_ANDROID', ''),
    ],

    // Global kill switch (maintenance screen in the app). Message per locale; falls back to `en`.
    'maintenance' => (bool) env('MOBILE_MAINTENANCE', false),
    'maintenance_message' => [
        'ar' => env('MOBILE_MAINTENANCE_MESSAGE_AR', 'التطبيق تحت الصيانة حاليا، يرجى المحاولة لاحقا.'),
        'en' => env('MOBILE_MAINTENANCE_MESSAGE_EN', 'The app is under maintenance. Please try again later.'),
    ],

    // Fallback brand colours (the website theme, resources/css/theme.css). Overridable via setting `branding.colors`.
    'brand_colors' => [
        'primary' => '#A8456C', 'primary_dark' => '#8F3A5C', 'primary_soft' => '#C96F91', 'primary_tint' => '#FBEAF0',
        'accent' => '#D7B987', 'accent_tint' => '#F4E8D5', 'text' => '#45313B', 'heading' => '#2F2129', 'muted' => '#7A6672',
        'border' => '#F0DDE4', 'surface' => '#FFF9F7', 'card' => '#FFFFFF',
        'success' => '#2F806A', 'warning' => '#D99A36', 'danger' => '#C8384F',
    ],

    // Shop id returned as `slug` by GET /api/v1/store and the deprecated GET /api/v1/tenants. Old app builds saved the slug of the shop
    // they picked ("egy") and namespace their tokens/cart by it: returning the same value keeps them logged in with no country picker.
    'legacy_store_slug' => env('MOBILE_LEGACY_STORE_SLUG', 'egy'),

    // Push notifications: only token storage ships now; the dispatcher is a log-only no-op until FCM credentials exist.
    'push' => ['driver' => env('MOBILE_PUSH_DRIVER', 'log')],
];
