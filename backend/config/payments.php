<?php

/*
 | Provider endpoints. We cannot call real APIs from dev/CI: tests use Http::fake against these hosts.
 | Sandbox/production switch = payment_methods.is_sandbox (per tenant method).
 |  - Paymob Accept (Egypt): the same host serves test and live; test mode is decided by the integration_id / api_key used.
 |    Docs: https://docs.paymob.com/docs/accept-standard-redirect  (auth/tokens, ecommerce/orders, acceptance/payment_keys,
 |    acceptance/transactions/{id}, acceptance/void_refund/refund, processed-callback HMAC SHA512)
 |  - FawryPay: sandbox https://atfawry.fawrystaging.com  (live https://www.atfawry.com)
 |    Docs: https://developer.fawrypay.com  (fawrypay-api/api/payments/init, ECommerceWeb/Fawry/payments/refund, status/v2, SHA-256 signatures)
 */
return [
    // Fallback for tenants without settings.cod_collect_shipping_upfront (owner decision: default true).
    'cod_upfront_default' => env('PAYMENTS_COD_UPFRONT_DEFAULT', true),
    'timeout' => (int) env('PAYMENTS_HTTP_TIMEOUT', 15),
    'paymob' => [
        'sandbox' => env('PAYMOB_BASE_URL', 'https://accept.paymob.com'),
        'live' => env('PAYMOB_BASE_URL', 'https://accept.paymob.com'),
    ],
    'fawrypay' => [
        'sandbox' => env('FAWRY_SANDBOX_URL', 'https://atfawry.fawrystaging.com'),
        'live' => env('FAWRY_LIVE_URL', 'https://www.atfawry.com'),
    ],
];
