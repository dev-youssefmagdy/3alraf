<?php

return [
    // Logo drawn in the centre of every QR. PLACEHOLDER asset: replace the file (or point QR_LOGO_PATH elsewhere), no code change needed.
    'logo_path' => env('QR_LOGO_PATH', public_path('logo.png')),
    // Output PNG edge in px (white quiet zone included).
    'size' => (int) env('QR_SIZE', 600),
    'margin' => (int) env('QR_MARGIN', 36),
    // Logo width as a fraction of the image width. Error correction is High (30%), keep this <= 0.22 so codes still scan.
    'logo_ratio' => (float) env('QR_LOGO_RATIO', 0.20),
    // Directory (under tenants/{id}/) on the media disk.
    'dir' => 'qr/products',
];
