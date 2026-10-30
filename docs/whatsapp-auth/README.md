# WhatsApp QR connection + phone login

## Architecture
- `whatsapp-service/` (Node 20, **Baileys 6.7.24**, pinned): ONE process, many isolated sessions. Session ids: `central`, `tenant-<id>` (regex-validated; no path traversal).
  Baileys chosen over whatsapp-web.js (headless Chrome per session, heavy) and Venom (same): pure WebSocket, low memory, multi-session friendly. 7.x is still a release candidate, so 6.7.x is pinned. Baileys is unofficial: WhatsApp may restrict accounts; keep OTP volume modest.
- Credentials: AES-256-GCM encrypted files in `WA_AUTH_DIR` (persistent, 0700). Never in the DB, never sent to the browser. DB stores metadata only (`whatsapp_sessions`).
- Laravel -> Node: REST with bearer token (`NodeClient`). Node -> Laravel: HMAC-signed (`timestamp.body`, 5 min window) POST `/internal/whatsapp/events` on the central host; Laravel updates `whatsapp_sessions`, logs, and broadcasts `session.updated` on private channels `whatsapp.central` / `whatsapp.tenant.{id}` (Reverb). The page also polls as a fallback.
- Tenancy in this app = one database, `tenant_id` column (not DB-per-tenant). Isolation: owner is derived from the authenticated panel / host, never from input; one row per owner (`owner_key` unique); tenant channel auth checks `tenant_id`.

## Node API (Bearer `WA_API_TOKEN`)
`GET /sessions/:id` · `POST /sessions/:id/connect|reconnect|disconnect(logout+wipe)|messages {to,text}` · `GET /health`

## Database
`whatsapp_sessions`, `whatsapp_session_logs`, `auth_settings` (key/value per owner: OTP length/ttl/cooldown/max attempts/max resends/message, login lockout), `admin_otps` (hashed codes), `central_admins|tenant_admins`: `phone` (E.164 digits), `is_super_admin` + generated `super_admin_slot` unique index (one per scope). Email is now optional.
Env (infra secrets only): `WHATSAPP_SERVICE_URL/TOKEN/CALLBACK_SECRET` (Laravel), `WA_*` (Node, see `.env.example`).

## Login
Phone+password; super admin goes straight in; everyone else gets a WhatsApp OTP from their OWN owner session, then the session is created. Unknown phone and wrong password are indistinguishable (dummy hash). Limits come from `auth_settings`; attempts are not reset by resend; codes are single use.
Super admins: `php artisan admin:provision-super [--tenant=slug] --phone=... --country=EG` (only path; one per scope). `php artisan tenants:create slug "Name" --owner-phone=...`.

## Deploy
1. `composer install`, `php artisan migrate --force` (adds 3 migrations), `npm ci && npm run build`.
2. `cd whatsapp-service && npm ci --omit=dev`; copy `.env.example` -> `.env`; `mkdir -p /var/lib/3alraf-whatsapp && chown www-data /var/lib/3alraf-whatsapp && chmod 700 ...`; **back up `WA_ENCRYPTION_KEY`**.
3. Laravel `.env`: `BROADCAST_CONNECTION=reverb`, REVERB_* values, WHATSAPP_* (same secrets as Node).
4. Copy `backend/deploy/supervisor/3alraf.conf`; `supervisorctl reread && supervisorctl update`. Proxy Reverb (wss) in nginx.
5. Provision the super admins; open Admin > WhatsApp and scan.

## Recovery
- Node restart: sessions restore automatically (no rescan). Crash loop: `tail storage/logs/whatsapp.log`.
- "Logged out" status: scan again. Lost `WA_ENCRYPTION_KEY`: delete the dir, rescan every owner.
- OTP cannot be delivered: the login screen says so; a super admin can still log in without OTP.
