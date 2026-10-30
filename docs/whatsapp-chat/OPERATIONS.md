# WhatsApp Chat - Operations

## Components that must run
1. Node gateway (`whatsapp-service`): `npm start` (keep under systemd/pm2). Binds `WA_HOST:WA_PORT` (default 127.0.0.1:3100).
2. Queue worker (sends, media pulls, broadcasts): `php artisan queue:work --tries=3` under a supervisor. Without it sent messages stay `pending` and inbound media stays `pending`.
3. Reverb (realtime): `php artisan reverb:start`. Optional: without a Reverb key the UI polls every 15 s.
4. Scheduler (`php artisan schedule:run` every minute) for the retention command.

## Config
Node (`whatsapp-service/.env`): `WA_API_TOKEN`, `WA_CALLBACK_URL`, `WA_CALLBACK_SECRET`, `WA_AUTH_DIR`, `WA_ENCRYPTION_KEY` (required); `WA_PORT`, `WA_HOST`, `WA_SPOOL_DIR` (private spool for media hand-off), `WA_MEDIA_TTL_MS` (15 min), `WA_MAX_INBOUND_MEDIA` / `WA_MAX_UPLOAD` (100 MB), `WA_LOG_LEVEL`.
Laravel (`.env`): `WHATSAPP_SERVICE_URL`, `WHATSAPP_SERVICE_TOKEN` (= WA_API_TOKEN), `WHATSAPP_CALLBACK_SECRET` (= WA_CALLBACK_SECRET), `WHATSAPP_CHAT_DISK` (default `private`), `WHATSAPP_CHAT_RETENTION_DAYS` (default 365, 0 = keep forever). `config/whatsapp_chat.php` also holds per-type size `limits`, allowed `mimes`, `inline_mimes`, `max_text`, `max_media_bytes`.
Run `php artisan migrate` (chat tables, permissions seed, thread index).

## Retention
`php artisan whatsapp:chat-prune [--days=N] [--dry-run]` removes messages (and their media files) older than the retention window; scheduled daily 04:20.

## Permissions
`whatsapp.chat.view` (read, mark read), `.send` (send/react/edit/delete/presence), `.manage` (archive/pin/assign/delete locally), `.media` (download media; also needs view). Auto-granted to super-admin and tenant owner; assign to other roles in Roles UI.

## Security notes
Owner always comes from the route/panel, never request input; every query uses `->ownedBy()`. Media is served only via `media.show` (nosniff, CSP sandbox, inline only for safe types). Node callbacks are HMAC-signed with a 5-minute window; ingest is idempotent so replays inside the window are no-ops. Message bodies are never logged (audit log stores ids/types only). Laravel never fetches URLs supplied by Node; it pulls only `/sessions/{own id}/media/{hex token}` from the configured Node URL. Customer linking is by exact phone match inside the same tenant, only when unambiguous.

## Troubleshooting
- Messages stay "pending": queue worker not running, or Node not connected (409 `not_connected`).
- No realtime: Reverb down or `REVERB_*`/`VITE_REVERB_*` missing (rebuild assets after changing VITE vars); UI falls back to polling.
- Media stuck "pending"/"expired": token TTL passed or Node restarted before the pull (spool is in-memory indexed); the original cannot be re-fetched.
- 401 on callback: secret mismatch or clock skew > 5 min between Node and Laravel.
- Inbound lost during Node downtime beyond WhatsApp's offline delivery (no history sync).

## Known limitations
No "mark unread"; no poll-sending UI and no poll vote results; live-location updates and view-once flags not surfaced; groups/broadcast/status are ignored; emoji picker is a small built-in set; echo adoption of phone-typed identical text is heuristic (same text within 5 min); react/edit/delete are synchronous calls to Node; no real-device or real-browser verification was done during development (see PLAN.md progress log).
