<?php

/**
 * Admin "search by image" (IMAGE_SEARCH_PLAN.md). Vectors come from the local embedder service (image-embedder/, SigLIP ONNX, 127.0.0.1 only),
 * are stored in image_embeddings (one row per image / bundle and model) and searched in PHP (cosine top-k over a cached float32 blob).
 * Scores are cosine similarities; the UI never shows them: it shows strength = clamp((s - min_score) / (full_score - min_score)) * 100 and a label.
 */
return [
    // Model string stored with every vector. Must equal the embedder's EMBEDDER_MODEL_NAME; changing it makes every image "missing" for the new model.
    'model' => env('IMAGE_SEARCH_MODEL', 'siglip-base-p16-224@pad224'),
    'dim' => (int) env('IMAGE_SEARCH_DIM', 768),

    'embedder' => [
        'url' => rtrim((string) env('IMAGE_EMBEDDER_URL', 'http://127.0.0.1:8090'), '/'),
        'timeout' => (float) env('IMAGE_EMBEDDER_TIMEOUT', 15),          // seconds per /embed call
        'connect_timeout' => (float) env('IMAGE_EMBEDDER_CONNECT_TIMEOUT', 2),
        'health_cache_seconds' => 30,
    ],

    // New / replaced / deleted catalog images are (re)indexed automatically through the queue. false = only `embeddings:fill-missing` / MCP.
    'auto_index' => (bool) env('IMAGE_SEARCH_AUTO_INDEX', true),
    'queue' => env('IMAGE_SEARCH_QUEUE', 'embeddings'),
    'queue_connection' => env('IMAGE_SEARCH_QUEUE_CONNECTION'),        // null = default connection

    // fill-missing: a pending row older than this is considered lost (worker died) and queued again; a failed row is retried after N hours.
    'pending_timeout_minutes' => (int) env('IMAGE_SEARCH_PENDING_TIMEOUT', 360),
    'retry_failed_after_hours' => (int) env('IMAGE_SEARCH_RETRY_FAILED_HOURS', 24),
    // fill-missing compares sha256(file bytes) with the stored hash to catch files replaced in place (same path). false = path check only.
    'verify_hashes' => (bool) env('IMAGE_SEARCH_VERIFY_HASHES', true),
    'fill_limit_max' => 5000,
    // A queued job keeps retrying (with backoff) while the embedder is down, for at most this long.
    'job_retry_hours' => 12,

    // Search
    'max_upload_kb' => 10240,                                  // 10 MB
    'mimes' => ['jpg', 'jpeg', 'png', 'webp', 'avif'],
    'results' => (int) env('IMAGE_SEARCH_RESULTS', 24),        // top-k per type (products incl. variants / bundles)
    'min_score' => (float) env('IMAGE_SEARCH_MIN_SCORE', 0.60),          // hidden below
    'confident_score' => (float) env('IMAGE_SEARCH_CONFIDENT_SCORE', 0.70), // best below => "no confident match" notice
    'full_score' => 0.95,                                       // strength 100%
    'strength' => ['strong' => 0.85, 'medium' => 0.75, 'weak' => 0.60], // label thresholds (cosine)
    'throttle_per_minute' => (int) env('IMAGE_SEARCH_THROTTLE', 30),     // per admin user
    'cache_store' => env('IMAGE_SEARCH_CACHE_STORE', 'file'),   // store of the cached vector blob (~5 MB base64 at 1,200 images); null = default store
    'cache_ttl_hours' => 24,
];
