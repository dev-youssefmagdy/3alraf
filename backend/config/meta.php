<?php

/**
 * Meta Marketing API (P6, MCP.md §7/§8; docs/marketing/META_INTEGRATION.md). The client lives in Laravel only: the access token never
 * leaves the server (never returned by the API, never logged, sent as an Authorization header — not in URLs).
 *
 * SAFETY POLICY (mandatory): every Campaign / Ad Set / Ad is created with status PAUSED. There is no code path that activates objects,
 * changes budgets of live objects or deletes anything in Meta (App\Services\Marketing\Meta\MetaGraphClient only exposes GET reads and a
 * guarded create for the campaign / adset / adcreative / ad / adimage edges).
 *
 * Verify `graph_version` and the objective / optimisation goal / CTA lists against the current Meta documentation before going live:
 * https://developers.facebook.com/docs/marketing-api (they change per Graph version).
 */
return [
    'graph_version' => env('META_GRAPH_VERSION', 'v26.0'),
    'graph_url' => rtrim(env('META_GRAPH_URL', 'https://graph.facebook.com'), '/'),

    // Secrets: .env only (System User token with ads_management + ads_read on the ad account; never commit it).
    'access_token' => env('META_ACCESS_TOKEN'),
    'app_id' => env('META_APP_ID'),
    'app_secret' => env('META_APP_SECRET'),            // when set, every call carries appsecret_proof = HMAC-SHA256(token, secret)
    'ad_account_id' => env('META_AD_ACCOUNT_ID'),      // digits, with or without the act_ prefix
    'page_id' => env('META_PAGE_ID'),                  // Facebook Page the ad creatives post as
    'instagram_user_id' => env('META_INSTAGRAM_USER_ID'), // optional Instagram account id for Instagram placements

    'timeout' => (int) env('META_TIMEOUT', 20),
    'cache_minutes' => (int) env('META_CACHE_MINUTES', 10),   // read endpoints (ad accounts, campaigns, ad sets, ads, insights)

    // Approval of a draft is valid for N hours (then re-approve). Bound to the draft version + content hash.
    'approval_valid_hours' => (int) env('META_APPROVAL_VALID_HOURS', 72),

    // Budget guard rails of a draft (currency = the ad account currency; amounts in major units, e.g. EGP).
    'min_daily_budget' => (float) env('META_MIN_DAILY_BUDGET', 50),
    'max_daily_budget' => (float) env('META_MAX_DAILY_BUDGET', 20000),
    'max_lifetime_budget' => (float) env('META_MAX_LIFETIME_BUDGET', 500000),
    'default_currency' => env('META_CURRENCY', 'EGP'),

    // Outcome-based objectives (ODAX) and the default ad set optimisation goal per objective.
    'objectives' => [
        'OUTCOME_AWARENESS' => 'REACH',
        'OUTCOME_TRAFFIC' => 'LINK_CLICKS',
        'OUTCOME_ENGAGEMENT' => 'POST_ENGAGEMENT',
        'OUTCOME_LEADS' => 'LINK_CLICKS',
        'OUTCOME_SALES' => 'LINK_CLICKS',
    ],
    'optimization_goals' => ['REACH', 'IMPRESSIONS', 'LINK_CLICKS', 'LANDING_PAGE_VIEWS', 'POST_ENGAGEMENT', 'OFFSITE_CONVERSIONS'],
    'ctas' => ['SHOP_NOW', 'LEARN_MORE', 'ORDER_NOW', 'BUY_NOW', 'GET_OFFER', 'CONTACT_US', 'SEND_WHATSAPP_MESSAGE', 'SIGN_UP'],
    'publisher_platforms' => ['facebook', 'instagram', 'messenger', 'audience_network'],
    'positions' => [
        'facebook' => ['feed', 'story', 'facebook_reels', 'marketplace', 'video_feeds', 'search'],
        'instagram' => ['stream', 'story', 'reels'], // Explore placement removed by Meta in v26.0,
    ],

    // Insights fields requested (MCP.md §7 READ): spend, impressions, reach, clicks, CTR, CPC, CPM, conversions / values, ROAS.
    'insights_fields' => ['spend', 'impressions', 'reach', 'clicks', 'ctr', 'cpc', 'cpm', 'actions', 'action_values', 'purchase_roas', 'date_start', 'date_stop'],
    'insights_presets' => ['today', 'yesterday', 'last_7d', 'last_14d', 'last_30d', 'last_90d', 'this_month', 'last_month', 'maximum'],
];
