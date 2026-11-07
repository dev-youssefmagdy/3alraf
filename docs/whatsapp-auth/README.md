# WhatsApp QR connection + phone login

## Architecture
- `whatsapp-service/` (Node 20, **Baileys 6.7.24**, pinned): ONE process, ONE session with the fixed id `central` (single store; validated, no path traversal). Retired ids `tenant-<n>` answer 410; leftover `WA_AUTH_DIR/tenant-<n>` dirs are skipped on restore (logged, not deleted).
  Baileys chosen over whatsapp-web.js (headless Chrome per session, heavy) and Venom (same): pure WebSocket, low memory, multi-session friendly. 7.x is still a release candidate, so 6.7.x is pinned. Baileys is unofficial: WhatsApp may restrict accounts; keep OTP volume modest.
- Credentials: AES-256-GCM encrypted files in `WA_AUTH_DIR` (persistent, 0700). Never in the DB, never sent to the browser. DB stores metadata only (`whatsapp_sessions`).
- Laravel -> Node: REST with bearer token (`NodeClient`). Node -> Laravel: HMAC-signed (`timestamp.body`, 5 min window) POST `APP_URL/internal/whatsapp/events`; Laravel updates `whatsapp_sessions` (one row, `central`; events for other ids are ignored), logs, and broadcasts `session.updated` on the private channel `whatsapp` (Reverb, guard `admin`). The page also polls as a fallback.
- Single store (2026-11): one shop, one admin panel, no `tenant_id` / `owner_key`. The session id is never taken from input (always `WhatsappSession::id()` = `central`).

## Node API (Bearer `WA_API_TOKEN`)
`GET /sessions/:id` · `POST /sessions/:id/connect|reconnect|disconnect(logout+wipe)|messages {to,text}` · `GET /health`

## Database
`whatsapp_sessions`, `whatsapp_session_logs`, `auth_settings` (key/value, one namespace: OTP length/ttl/cooldown/max attempts/max resends/message, login lockout), `admin_otps` (hashed codes), `admins`: `phone` (E.164 digits), `is_super_admin` + generated `super_admin_slot` unique index (one super admin), `otp_exempt`. Email is now optional.
Env (infra secrets only): `WHATSAPP_SERVICE_URL/TOKEN/CALLBACK_SECRET` (Laravel), `WA_*` (Node, see `.env.example`).

## Login
Phone+password; accounts flagged `otp_exempt` go straight in; everyone else gets a WhatsApp OTP through the shop session (`central`), then the session is created. Unknown phone and wrong password are indistinguishable (dummy hash). Limits come from `auth_settings`; attempts are not reset by resend; codes are single use.
Super admin: `php artisan admin:provision-super --phone=... --country=EG` (only path; exactly one; `otp_exempt` is set so the first login works before WhatsApp is paired).

## Deploy
1. `composer install`, `php artisan migrate --force` (adds 3 migrations), `npm ci && npm run build`.
2. `cd whatsapp-service && npm ci --omit=dev`; copy `.env.example` -> `.env`; `mkdir -p /var/lib/3alraf-whatsapp && chown www-data /var/lib/3alraf-whatsapp && chmod 700 ...`; **back up `WA_ENCRYPTION_KEY`**.
3. Laravel `.env`: `BROADCAST_CONNECTION=reverb`, REVERB_* values, WHATSAPP_* (same secrets as Node).
4. Copy `backend/deploy/supervisor/3alraf.conf`; `supervisorctl reread && supervisorctl update`. Proxy Reverb (wss) in nginx.
5. Provision the super admin; open Admin > WhatsApp and scan (an already paired `central` session needs no rescan).

## Recovery
- Node restart: sessions restore automatically (no rescan). Crash loop: `tail storage/logs/whatsapp.log`.
- "Logged out" status: scan again. Lost `WA_ENCRYPTION_KEY`: delete the dir, rescan.
- OTP cannot be delivered: the login screen says so; an `otp_exempt` account can still log in without OTP.
