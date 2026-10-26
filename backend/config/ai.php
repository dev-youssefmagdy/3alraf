<?php

/** Non-secret defaults only. Providers, API keys, models, quotas live in the database (docs/plans/ai-features.md). */
return [
    'timeout' => 60,
    'connect_timeout' => 10,
    'retries' => 2,
    'rate_per_minute' => 30,          // per tenant
    'tool_result_chars' => 12000,
    'anthropic_version' => '2023-06-01',
];
