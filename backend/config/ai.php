<?php

/** Non-secret defaults only. Providers, API keys, models, quotas live in the database (docs/plans/ai-features.md). */
return [
    'timeout' => 60,
    'connect_timeout' => 10,
    'retries' => 2,
    'retry_max_wait' => 8,            // seconds; cap on honoring a provider Retry-After
    'rate_per_minute' => 30,          // per tenant
    'tool_result_chars' => 12000,
    'video_poll_interval' => 5,       // seconds between OpenRouter video job polls
    'video_poll_timeout' => 280,      // seconds to wait for a video job before giving up
    'anthropic_version' => '2023-06-01',
];
