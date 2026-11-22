<?php

// AI Casting Studio (/marketer/casting). Provider-independent: URLs / credentials live here (env) and never reach the browser or the logs.
return [
    // comfyui = local ComfyUI HTTP API (free, no paid API). fake = deterministic placeholder images (tests / demos without a GPU).
    'provider' => env('CASTING_PROVIDER', 'comfyui'),
    'private_disk' => env('CASTING_DISK', env('MEDIA_PRIVATE_DISK', 'private')),
    'candidates' => ['min' => 4, 'max' => 6, 'default' => 4],
    'upload' => ['max_kb' => 6144, 'mimes' => ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'], 'max_px' => 6000, 'min_px' => 256],
    'job' => ['tries' => 3, 'backoff' => [30, 120], 'timeout' => 900, 'queue' => env('CASTING_QUEUE', 'casting')],
    'comfyui' => [
        'url' => env('COMFYUI_URL', 'http://127.0.0.1:8188'),
        'timeout' => (int) env('COMFYUI_TIMEOUT', 600),
        'unet' => env('COMFYUI_UNET', 'z_image_turbo_nvfp4.safetensors'),
        'clip' => env('COMFYUI_CLIP', 'qwen_3_4b_fp8_mixed.safetensors'),
        'vae' => env('COMFYUI_VAE', 'ae.safetensors'),
        'steps' => 8,
        'video_timeout' => (int) env('COMFYUI_VIDEO_TIMEOUT', 2400),
    ],
    // 3alraf.com (live) link. READ side = the store MCP (Claude reads live products, then `casting:live-import`). WRITE side = ONE upload endpoint
    // (scope media:write) that stores the file privately as pending_review; a person approves it in the live marketer panel. Credentials stay in .env.
    'live' => [
        'url' => env('LIVE_MEDIA_URL'),            // e.g. https://3alraf.com (https required except for localhost / *.loc / *.test)
        'token' => env('LIVE_MEDIA_TOKEN'),        // marketer-panel API token with ONLY the media:write scope
        'image_hosts' => ['3alraf.com', 'www.3alraf.com'],   // product photos are downloaded only from these hosts, only under /media/
        'timeout' => 60,
    ],
    // Video Studio. A profile = a workflow template + the ComfyUI models it needs + the settings verified by a real generation.
    // Video stays OFF unless the workflow file exists, every required model is present in ComfyUI AND `verified` is true (set only after a real test clip).
    'video' => [
        'profile' => env('CASTING_VIDEO_PROFILE', 'wan22_5b'),
        'profiles' => [
            'wan22_5b' => [
                'label' => 'Wan 2.2 TI2V 5B',
                'workflow' => resource_path('casting/workflows/wan22_ti2v_5b_i2v.json'),
                'models' => ['unet' => 'wan2.2_ti2v_5B_fp16.safetensors', 'clip' => 'umt5_xxl_fp8_e4m3fn_scaled.safetensors', 'vae' => 'wan2.2_vae.safetensors'],
                // verified by real generated clips on the RTX 5060 8 GB (2026-11-22): 480x832, 640x640, 512x640, 832x480, 33 frames @ 24 fps, 20 steps, 46-60 s each, peak ~7.6 GB VRAM
                'verified' => (bool) env('CASTING_VIDEO_VERIFIED', true),
                'fps' => 24, 'steps' => 20, 'lengths' => [33], 'sizes' => ['9:16' => [480, 832], '1:1' => [640, 640], '4:5' => [512, 640], '16:9' => [832, 480]],
                'negative' => 'distorted product, warped or changed logo, changed colours, morphing shape, extra objects, text artifacts, blurry, shaky camera, low quality',
            ],
        ],
    ],
    // What each provider can honestly do. identity = how a model's face is carried across images.
    'capabilities' => [
        'comfyui' => ['text_to_image' => true, 'reference_image' => false, 'video' => false, 'identity' => 'prompt_seed'],
        'fake' => ['text_to_image' => true, 'reference_image' => true, 'video' => false, 'identity' => 'prompt_seed'],
    ],
];
