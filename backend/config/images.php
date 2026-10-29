<?php

return [
    // Hard cap per optimized file (bytes). 102400 = 100 KB. Per-profile `max_bytes` overrides it.
    'max_bytes' => (int) env('IMAGE_MAX_BYTES', 102400),

    // Input safety: bytes and pixels accepted before decoding (bounds memory: ~4 bytes/px in GD).
    'max_source_bytes' => (int) env('IMAGE_MAX_SOURCE_BYTES', 20 * 1024 * 1024),
    'max_source_pixels' => (int) env('IMAGE_MAX_SOURCE_PIXELS', 40_000_000),

    // Real content types accepted (checked from the bytes, never from the extension).
    'allowed_mimes' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif', 'image/bmp'],

    // New uploads also get a WebP sibling (name.webp) used as the <picture> fallback for browsers without AVIF.
    'webp_fallback' => (bool) env('IMAGE_WEBP_FALLBACK', true),

    // Quality ladder tried (highest first) at each scale step; the first encode within the limit wins.
    // 65 → 40 keeps product labels readable; below the floor the image is flagged instead of degraded further.
    'qualities' => [65, 60, 55, 50, 45, 40],
    'scale_steps' => [1.0, 0.9, 0.8, 0.7, 0.6],

    // `fit`: scaleDown inside max_w × max_h (never upscales, never crops); `cover`: crop to exactly w × h (never upscales).
    'profiles' => [
        'product' => ['max_w' => 1000, 'max_h' => 1000, 'fit' => 'scale', 'max_bytes' => 102400],
        'thumb' => ['max_w' => 400, 'max_h' => 400, 'fit' => 'cover', 'max_bytes' => 40960],
        'category' => ['max_w' => 700, 'max_h' => 700, 'fit' => 'scale', 'max_bytes' => 81920],
        'banner' => ['max_w' => 1600, 'max_h' => 700, 'fit' => 'scale', 'max_bytes' => 102400],
        'logo' => ['max_w' => 800, 'max_h' => 800, 'fit' => 'scale', 'max_bytes' => 102400],
        'editorial' => ['max_w' => 1200, 'max_h' => 1200, 'fit' => 'scale', 'max_bytes' => 102400],
        'default' => ['max_w' => 1200, 'max_h' => 1200, 'fit' => 'scale', 'max_bytes' => 102400],
    ],

    // Upload directory (first path segment after tenants/{id}/ or catalog/) → profile.
    'dirs' => [
        'products' => 'product', 'variants' => 'product', 'bundles' => 'product', 'packaging' => 'product',
        'categories' => 'category',
        'banners' => 'banner',
        'brands' => 'logo',
    ],
];
