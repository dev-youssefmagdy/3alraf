<?php

return [
    // Private disk for inbound media (never publicly served; Phase 3 streams it through an authorized controller).
    'disk' => env('WHATSAPP_CHAT_DISK', 'private'),
    // `whatsapp:chat-prune` deletes messages (and their media) older than this many days; 0 = keep forever.
    'retention_days' => (int) env('WHATSAPP_CHAT_RETENTION_DAYS', 365),
    // Hard cap for one inbound media pull (bytes); matches the Node spool cap.
    'max_media_bytes' => 100 * 1024 * 1024,

    // Outbound caps in bytes per message type (plan section 4.2).
    'limits' => [
        'image' => 16 * 1024 * 1024, 'video' => 64 * 1024 * 1024, 'audio' => 16 * 1024 * 1024, 'ptt' => 16 * 1024 * 1024,
        'sticker' => 2 * 1024 * 1024, 'document' => 100 * 1024 * 1024,
    ],
    // Real (sniffed) MIME types accepted per media type. Documents accept anything.
    'mimes' => [
        'image' => ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
        'video' => ['video/mp4', 'video/3gpp', 'video/quicktime'],
        'audio' => ['audio/ogg', 'audio/mpeg', 'audio/mp4', 'audio/x-m4a', 'audio/aac', 'audio/amr', 'audio/webm', 'video/webm', 'video/ogg', 'application/ogg'],
        'ptt' => ['audio/ogg', 'audio/webm', 'video/webm', 'audio/mpeg', 'audio/mp4', 'audio/x-m4a', 'audio/aac', 'application/ogg', 'video/ogg'],
        'sticker' => ['image/webp'],
    ],
    // Types a browser may render inline; everything else is served as an attachment.
    'inline_mimes' => ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'video/mp4', 'video/3gpp', 'video/quicktime', 'audio/ogg', 'audio/mpeg', 'audio/mp4', 'audio/aac', 'audio/webm', 'application/pdf'],
    'max_text' => 4096,
];
