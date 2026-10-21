<?php

return [
    // Disk for public images (product/brand/category/banner). Served from the CDN host in `url`.
    'disk' => env('MEDIA_DISK', 'media'),
    // Private files (payment proofs, expense receipts, invoices, exports): never public, signed temporary URLs only.
    'private_disk' => env('MEDIA_PRIVATE_DISK', 'private'),
    // Public base URL of the media disk (CDN), no trailing slash.
    'url' => rtrim(env('MEDIA_URL', rtrim(env('APP_URL', 'http://localhost'), '/').'/media'), '/'),
    'signed_ttl_minutes' => (int) env('MEDIA_SIGNED_TTL', 10),
];
