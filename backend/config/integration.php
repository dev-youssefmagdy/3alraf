<?php

/**
 * Integration API for n8n (/api/integration/v1, routes/api_integration.php). Read-only since P2 (MARKETER_PLAN.md): ping, health and
 * gift-finder reads. Clients are managed in the admin (Integration clients); docs/api/integration.md describes the calls and the signing.
 */
return [
    // Scopes a client may hold. Every endpoint needs `read`; the gift-finder reads also accept `gift_finder:read` alone.
    'scopes' => [
        'read' => 'Read everything the integration API exposes (aggregates, no personal data)',
        'gift_finder:read' => 'Gift Finder reads only (stats, untagged products / bundles, upcoming occasions)',
    ],

    // Bearer token + HMAC-SHA256(secret, "{X-Timestamp}.{raw body}") within this window (seconds, both directions).
    'hmac' => ['replay_window_seconds' => 300, 'header' => 'X-Signature', 'timestamp_header' => 'X-Timestamp'],

    // Per-client limit (requests per minute) after authentication; the prefix also has a per-IP limit of 300/min (AppServiceProvider).
    'throttle_per_minute' => 120,

    // Optional allowlist (IPs or CIDRs, comma separated in env) for the whole /api/integration/v1 prefix. Empty = no restriction.
    'ip_allowlist' => array_values(array_filter(array_map('trim', explode(',', (string) env('INTEGRATION_IPS', ''))))),

    // integration_requests (one row per authenticated call: client, method, path, status; no body, no IP) are pruned after N days (integration:prune).
    'request_log_days' => 30,
];
