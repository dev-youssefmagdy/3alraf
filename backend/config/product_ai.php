<?php

/**
 * Product edit → AI tools (PRODUCT_AI_FREELLMAPI_PLAN.md): price list by image, marketplace prices and title ideas, through FreeLLMAPI,
 * a self-hosted OpenAI-compatible gateway in front of free LLM tiers (docs/FREELLMAPI_SETUP.md). Best effort: every action fails soft.
 * "Configured" = base_url AND api_key are set; otherwise the admin card shows "not configured" and the endpoints answer 503 not_configured.
 * The main values are edited in Admin → System settings → AI (App\Support\SystemSettings, encrypted `settings` rows applied over this
 * config at boot); the env() values below are only the defaults / first import. Code reads config('product_ai.*'), never env().
 */
$list = fn (string $csv): array => array_values(array_filter(array_map('trim', explode(',', $csv)), 'strlen'));

return [
    // Same VPS: http://127.0.0.1:3001/v1 (no TLS hop). Laravel on another server: https://llm.3alraf.com/v1
    'base_url' => rtrim((string) env('FREELLM_BASE_URL', 'http://127.0.0.1:3001/v1'), '/'),
    'api_key' => (string) env('FREELLM_API_KEY', ''),   // the unified key from the dashboard (Keys page), freellmapi-…

    // Automatic model switching: tried in order; on 429 / 5xx / timeout / 422 no_vision_model the next one is used, and the failed one
    // cools down for Retry-After seconds (default cooldown_seconds). "auto" = the dashboard fallback chain, auto:reliable|fast|smart = rankings.
    'models' => [
        'text' => $list((string) env('FREELLM_TEXT_MODELS', 'auto,auto:reliable,auto:fast')),
        'vision' => $list((string) env('FREELLM_VISION_MODELS', 'auto,gemini-2.5-flash')),
    ],
    'cooldown_seconds' => (int) env('FREELLM_COOLDOWN', 60),
    'timeout' => (int) env('FREELLM_TIMEOUT', 40),             // seconds per model call (the whole request stays under ~60 s)
    'connect_timeout' => (int) env('FREELLM_CONNECT_TIMEOUT', 3),
    'temperature' => 0.2,

    'market' => ['country' => env('PRODUCT_AI_MARKET_COUNTRY', 'EG'), 'currency' => env('PRODUCT_AI_CURRENCY', 'EGP'), 'language' => 'ar',
        'region' => env('PRODUCT_AI_SEARCH_REGION', 'eg-ar')],
    'locales' => ['ar', 'en'],

    // Marketplace allow-list (host suffixes; Admin → System settings → AI). Listings on other domains are kept, ranked lower and marked "other site".
    'marketplaces' => $list((string) env('PRODUCT_AI_MARKETPLACES',
        'amazon.eg,noon.com,jumia.com.eg,btech.com,2b.com.eg,rayashop.com,carrefouregypt.com,dream2000.com,elarabygroup.com,cairosales.com,kemitt.com')),
    // Display names (domain => name); a domain without one is shown as the domain
    'marketplace_names' => [
        'amazon.eg' => 'Amazon.eg', 'noon.com' => 'noon', 'jumia.com.eg' => 'Jumia', 'btech.com' => 'B.TECH', '2b.com.eg' => '2B',
        'rayashop.com' => 'Raya Shop', 'carrefouregypt.com' => 'Carrefour Egypt', 'dream2000.com' => 'Dream 2000', 'elarabygroup.com' => 'Elaraby',
        'cairosales.com' => 'Cairo Sales', 'kemitt.com' => 'Kemitt',
    ],

    'search' => [
        // ddgs text search (tools/image-picker, the same venv/script as Find images): free, no key
        'backends' => $list((string) env('PRODUCT_AI_SEARCH_BACKENDS', 'google,bing,duckduckgo')),
        'results' => (int) env('PRODUCT_AI_SEARCH_RESULTS', 15),
        'timeout' => (int) env('PRODUCT_AI_SEARCH_TIMEOUT', 20),
        'suffix' => env('PRODUCT_AI_SEARCH_SUFFIX', 'price Egypt'),  // appended to the product text for marketplace searches
        // Optional second provider: FreeLLMAPI POST /v1/search (only works when an engine key, e.g. Tavily or Brave, is set in its dashboard)
        'freellm' => (bool) env('PRODUCT_AI_FREELLM_SEARCH', false),
    ],

    'pages' => [
        'max_per_run' => (int) env('PRODUCT_AI_MAX_PAGE_FETCHES', 8),   // listing pages fetched per price search
        'max_bytes' => 2 * 1024 * 1024,
        'timeout' => 8,                      // seconds per page
        'budget_seconds' => 15,              // all page fetches of one run; the rest of the ~55 s goes to search and the LLM
        'text_chars' => 3000,                // page text sent to the LLM per listing
    ],

    'cache_hours' => (int) env('PRODUCT_AI_CACHE_HOURS', 24),   // identify + prices; titles are never cached
    'max_rows' => 20,
    'upload_max_kb' => 5120,
    'throttle_per_minute' => (int) env('PRODUCT_AI_THROTTLE', 10), // AI requests per admin
];
