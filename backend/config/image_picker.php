<?php

/**
 * Product edit → "Find images" (tools/image-picker). Free: the picker script searches Bing (and DuckDuckGo) through the open-source
 * `ddgs` library, no API key and no quota. Laravel runs the script's `search` command, caches the answer, and imports a picture the admin
 * chose through the normal upload pipeline after downloading it with the SSRF-safe fetcher.
 */
return [
    // Python of the picker's venv (tools/image-picker/.venv, see tools/image-picker/README.md) and the script itself
    'python' => env('IMAGE_PICKER_PYTHON', base_path('../tools/image-picker/.venv/bin/python')),
    'script' => env('IMAGE_PICKER_SCRIPT', base_path('../tools/image-picker/image_picker.py')),
    'engines' => env('IMAGE_PICKER_ENGINES', 'bing'),   // add ',duckduckgo' if ddgs' DuckDuckGo backend works on your server
    'timeout' => (int) env('IMAGE_PICKER_TIMEOUT', 45),          // seconds per search
    'cache_hours' => (int) env('IMAGE_PICKER_CACHE_HOURS', 24 * 7),
    'download_max_bytes' => 15 * 1024 * 1024,
    'throttle_per_minute' => (int) env('IMAGE_PICKER_THROTTLE', 20), // searches per admin
];
