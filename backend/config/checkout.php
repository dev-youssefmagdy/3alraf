<?php

/*
 | Offline (WALLET) checkout: mandatory transfer proof + anti-abuse limits. Every value is env-overridable.
 | Windows are in minutes. All counters are keyed by resolved tenant + (client IP | normalised phone | normalised e-mail).
 */
return [
    'proof' => [
        'max_kb' => (int) env('CHECKOUT_PROOF_MAX_KB', 5120),
        'max_dimension' => (int) env('CHECKOUT_PROOF_MAX_DIMENSION', 8000), // px, each side
        'mimes' => ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'],
    ],
    'offline' => [
        'ip_attempts' => (int) env('CHECKOUT_OFFLINE_IP_ATTEMPTS', 5),
        'ip_window' => (int) env('CHECKOUT_OFFLINE_IP_WINDOW', 10),
        'ip_daily' => (int) env('CHECKOUT_OFFLINE_IP_DAILY', 20),
        'contact_attempts' => (int) env('CHECKOUT_OFFLINE_CONTACT_ATTEMPTS', 3), // per phone and per e-mail
        'contact_window' => (int) env('CHECKOUT_OFFLINE_CONTACT_WINDOW', 60),
        'open_cap' => (int) env('CHECKOUT_OFFLINE_OPEN_CAP', 3), // open (proof pending review) offline orders per phone/customer
        'open_cap_retry' => (int) env('CHECKOUT_OFFLINE_OPEN_CAP_RETRY', 3600), // Retry-After seconds sent with the cap 429
    ],
    'validate' => [ // pre-validate with an offline method + a file (decodes the image, reveals reused hashes): looser than placing, still bounded
        'attempts' => (int) env('CHECKOUT_VALIDATE_ATTEMPTS', 30),
        'window' => (int) env('CHECKOUT_VALIDATE_WINDOW', 10),
    ],
    'reupload' => [
        'attempts' => (int) env('CHECKOUT_REUPLOAD_ATTEMPTS', 5),
        'window' => (int) env('CHECKOUT_REUPLOAD_WINDOW', 10),
    ],
];
