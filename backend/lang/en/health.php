<?php

return [
    'title' => 'System health', 'menu' => 'System health', 'hint' => 'Checks the connection of every service the shop depends on. It sends no message and changes no data.',
    'run_all' => 'Check all', 'recheck' => 'Re-check', 'checking' => 'Checking…',
    'status' => ['ok' => 'Working', 'warn' => 'Warning', 'fail' => 'Down', 'off' => 'Not enabled', 'err' => 'The check could not run'],
    'checks' => ['database' => 'Database', 'cache' => 'Cache', 'queue' => 'Queue', 'storage' => 'File storage', 'mail' => 'E-mail', 'whatsapp' => 'WhatsApp', 'reverb' => 'Realtime (Reverb)', 'meta' => 'Meta Ads', 'n8n' => 'n8n', 'google' => 'Google sign-in', 'sentry' => 'Error tracking (Sentry)'],
    'msg' => [
        'cache_rw' => 'Read-after-write failed.', 'storage_rw' => 'Read-after-write failed on the disk.', 'queue_sync' => 'Queue runs synchronously (sync): no background worker.',
        'queue_failed' => ':n failed jobs (:driver).', 'mail_log' => 'Mailer ":m": e-mails are not really sent.', 'unreachable' => 'unreachable',
        'not_configured' => 'Not configured.', 'configured' => 'Configured.', 'wa_pending' => 'Status: :s. Finish linking on the WhatsApp page.', 'wa_down' => 'Disconnected (:s).',
        'reverb_off' => 'Broadcasting is set to ":d", not Reverb.',
    ],
];
