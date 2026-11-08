<?php

return [
    // Disk for public images (product/brand/category/banner). Served from the CDN host in `url`.
    'disk' => env('MEDIA_DISK', 'media'),
    // Private files (payment proofs, expense receipts, invoices, exports): never public, signed temporary URLs only.
    'private_disk' => env('MEDIA_PRIVATE_DISK', 'private'),
    // Public base URL of the media disk (CDN), no trailing slash.
    'url' => rtrim(env('MEDIA_URL', rtrim(env('APP_URL', 'http://localhost'), '/').'/media'), '/'),
    'signed_ttl_minutes' => (int) env('MEDIA_SIGNED_TTL', 10),
    // Public product gallery videos (P8). The effective upload limit is this value capped by PHP's upload_max_filesize / post_max_size.
    'product_video' => [
        'max_mb' => (int) env('PRODUCT_VIDEO_MAX_MB', 50),
        'max_per_product' => (int) env('PRODUCT_VIDEO_MAX_PER_PRODUCT', 5),
        'mimes' => ['video/mp4' => 'mp4', 'video/webm' => 'webm'],
    ],
];
