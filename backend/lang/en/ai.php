<?php

return [
    'menu' => ['group' => 'AI', 'providers' => 'Providers', 'models' => 'Models', 'tenants' => 'Tenants AI', 'usage' => 'Usage & cost', 'settings' => 'AI Settings'],
    'fields' => [
        'name' => 'Name', 'driver' => 'Vendor', 'api_key' => 'API key', 'base_url' => 'Base URL', 'provider' => 'Provider', 'model_id' => 'Model ID', 'label' => 'Label', 'kind' => 'Kind',
        'capabilities' => 'Capabilities', 'input_cost' => 'Input cost / 1M tokens', 'output_cost' => 'Output cost / 1M tokens', 'max_output' => 'Max output tokens',
        'is_default' => 'Default for kind', 'tenant' => 'Tenant', 'country' => 'Country', 'ai_enabled' => 'AI enabled', 'monthly_token_quota' => 'Monthly token quota',
        'monthly_image_quota' => 'Monthly image quota', 'monthly_video_quota' => 'Monthly video quota (secs)', 'features' => 'Features', 'allowed_models' => 'Allowed models',
        'date' => 'Date', 'feature' => 'Feature', 'model' => 'Model', 'default_model' => 'Default model', 'brand_voice' => 'Brand voice', 'forbidden_words' => 'Forbidden words (comma separated)',
        'default_platforms' => 'Default platforms', 'default_ai_model_id' => 'Default model', 'allowed_model_ids' => 'Allowed models', 'ai_provider_id' => 'Provider',
    ],
    'enums' => [
        'ai_driver_type' => ['anthropic' => 'Anthropic (Claude)', 'openai' => 'OpenAI', 'moonshot' => 'Moonshot (Kimi)', 'custom_openai' => 'Custom (OpenAI-compatible)'],
        'ai_model_kind' => ['text' => 'Text', 'image' => 'Image', 'video' => 'Video'],
        'ai_feature' => ['chat' => 'Assistant', 'trends' => 'Trends', 'studio' => 'Studio', 'editor' => 'Product editor', 'generators' => 'Generators'],
        'ai_generation_type' => ['social_post' => 'Social post', 'marketing_post' => 'Marketing post', 'banner' => 'Banner', 'offer' => 'Offer', 'bundle' => 'Bundle', 'package' => 'Package', 'product_edit' => 'Product edit', 'trend_report' => 'Trend report'],
        'ai_generation_status' => ['queued' => 'Queued', 'draft' => 'Draft', 'approved' => 'Approved', 'rejected' => 'Rejected', 'failed' => 'Failed'],
    ],
    'capabilities' => ['tools' => 'Tool calling', 'vision' => 'Vision', 'web_search' => 'Web search', 'json_mode' => 'JSON mode'],
    'platforms' => ['tiktok' => 'TikTok', 'instagram' => 'Instagram', 'facebook' => 'Facebook', 'x' => 'X', 'pinterest' => 'Pinterest', 'whatsapp' => 'WhatsApp'],
    'connection' => 'Connection', 'test_connection' => 'Test connection', 'test_ok' => 'Connection OK', 'test_failed' => 'Connection failed',
    'key_keep' => 'Leave blank to keep the current key.', 'base_url_hint' => 'Optional for built-in vendors; required for custom OpenAI-compatible.',
    'on' => 'On', 'off' => 'Off', 'unlimited' => 'Unlimited', 'quota_hint' => 'Empty = unlimited.', 'allowed_hint' => 'Tick none to allow every active model.',
    'usage_this_month' => 'This month', 'tokens' => 'Tokens', 'images' => 'Images', 'video_secs' => 'video secs', 'cost' => 'Est. cost', 'calls' => 'Calls',
    'platform_default' => 'Platform default',
    'errors' => [
        'disabled' => 'AI is not enabled for your store or for this feature. Contact the platform administrator.',
        'quota' => 'Your monthly AI quota is used up.',
        'throttled' => 'Too many AI requests. Please wait a moment and try again.',
        'unsupported' => 'This model or vendor does not support that operation.',
        'provider' => 'The AI service is temporarily unavailable. Please try again.',
        'model_unavailable' => 'The selected AI model is not available.',
    ],
];
