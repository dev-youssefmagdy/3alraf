# 3alraf: Setup, Usage and Integration Guide

One document that ties every part of the project together. Each section links to the detailed doc for that component.

## 1. Architecture

```
                         ┌──────────────────────────────┐
 Customers ──web/app──▶  │  Laravel 13 (backend/)       │ ◀── Admin /admin, Marketer /marketer
 Flutter app (app/) ───▶ │  storefront, /api/v1 mobile  │
                         │  /api/internal/marketing/*   │ ◀── MCP server (service token)
                         │  /api/integration/v1         │ ◀── n8n (bearer + HMAC)
                         │  /internal/whatsapp/events   │ ◀── WhatsApp gateway (HMAC)
                         │  Reverb websockets (:8080)   │
                         └──────┬───────────────┬───────┘
                           MySQL + Redis     Meta Graph API (drafts, always PAUSED)

 Claude (Pro) ─HTTPS─▶ mcp-server/ (Node, :8787) ─▶ Laravel internal API
 n8n (:5678, no Docker) ─▶ Laravel integration API (read-only) ─▶ notifies owner
 whatsapp-service/ (Node, :3100, Baileys) ◀─▶ Laravel (OTP, chat inbox)
```

Principles:
- Laravel is the single source of truth. The MCP server and n8n never touch the database.
- Claude can read data and **save suggestions or drafts**. Only a person in the marketer panel can approve, apply or publish.
- Every Meta campaign, ad set and ad is created **PAUSED**. Nothing is activated automatically.
- n8n only reads (gift-finder endpoints) and notifies. It cannot change the store.

| Component | Path | Port | Runtime |
|---|---|---|---|
| Laravel app | `backend/` | web via nginx (80/443), dev `php artisan serve` | PHP 8.5, MySQL, Redis |
| Reverb websockets | `backend/` | 8080 | `php artisan reverb:start` |
| Queue / scheduler | `backend/` | – | `queue:work`, `schedule:work` |
| WhatsApp gateway | `whatsapp-service/` | 3100 (loopback) | Node 22 |
| MCP server | `mcp-server/` | 8787 (loopback) | Node 20.19+ |
| n8n | `n8n/` | 5678 (loopback) | Node, systemd |
| Customer app | `app/` | – | Flutter 3.41 |

## 2. Laravel backend (`backend/`)

**First install**
```bash
cd backend
composer install && npm install
cp .env.example .env && php artisan key:generate
# set DB_*, APP_URL, APP_ENV, then:
php artisan migrate:fresh --seed       # fresh install only, wipes data
php artisan storage:link
npm run build
php artisan admin:provision-super      # creates the one real super admin
```
Other commands: `php artisan seed:dev` (fake data, local only), `seed:live --force` (strip demo data), `vendor/bin/pest` (tests use the MySQL db `3alraf_test`), `php artisan db:seed --class=PermissionSeeder` after permission changes.

**Production caches** (after every deploy or `.env` change):
```bash
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan event:cache
sudo supervisorctl restart all     # or restart the 3alraf-* programs
```

**Processes** (`backend/deploy/supervisor/3alraf.conf`): `3alraf-queue`, `3alraf-scheduler`, `3alraf-whatsapp`, `3alraf-reverb`. Install with:
```bash
sudo cp deploy/supervisor/3alraf.conf /etc/supervisor/conf.d/ && sudo supervisorctl reread && sudo supervisorctl update
```
Web: `deploy/nginx/app.conf` (site vhost), `deploy/nginx/n8n.conf` (n8n subdomain), `deploy/nginx/cdn.conf`; Apache variants are in `deploy/apache/`.

**Panels**
| Panel | URL | Purpose |
|---|---|---|
| Storefront | `/ar`, `/en` | customer shop, gift finder, affiliate area |
| Admin | `/admin` | products, orders, inventory, team, settings. Login is phone, password and a WhatsApp OTP |
| Marketer | `/marketer` | suggestions, approvals, Meta drafts, social library, analytics, API tokens, integration clients |
| Settings | Admin → Settings → General | shop name, currency, locales, payments, SEO, gift-finder switch |

Key `.env` groups: `DB_*`, `REDIS_*`, `QUEUE_CONNECTION`, `MAIL_*`, `REVERB_*`, `WHATSAPP_*`, `META_*`, `GOOGLE_*`, `INTEGRATION_IPS`, `MEDIA_*`. Never commit `.env`.

More: `backend/README.md`, `backend/CLAUDE.md` (code conventions: Controller → FormRequest → DTO → Service → Repository → Model), `backend/docs/REQUIREMENTS.md`, `CALCULATIONS.md` (all money rules), `MOBILE_API.md`, `openapi-mobile.yaml`.

## 3. WhatsApp gateway (`whatsapp-service/`)

Used for admin OTP login and the WhatsApp chat inbox. One session with the fixed id `central`.

1. Generate two shared secrets: `openssl rand -hex 32` (API token) and `openssl rand -hex 32` (callback secret).
2. Laravel `.env`: `WHATSAPP_SERVICE_URL=http://127.0.0.1:3100`, `WHATSAPP_SERVICE_TOKEN=<api token>`, `WHATSAPP_CALLBACK_SECRET=<callback secret>`.
3. `whatsapp-service/.env`:
   ```
   WA_PORT=3100
   WA_HOST=127.0.0.1
   WA_API_TOKEN=<same as WHATSAPP_SERVICE_TOKEN>
   WA_CALLBACK_SECRET=<same as WHATSAPP_CALLBACK_SECRET>
   WA_CALLBACK_URL=https://YOUR_HOST/internal/whatsapp/events
   WA_AUTH_DIR=/var/lib/3alraf-whatsapp      # persistent, mode 0700
   WA_ENCRYPTION_KEY=<openssl rand -base64 32>   # lose it and you must re-scan the QR
   ```
4. Start it (`npm start`, or the supervisor program) and open the admin panel's WhatsApp page. Scan the QR with the store's phone: WhatsApp → Linked devices.
5. Test: log in as a non-exempt admin and check that the OTP arrives.

The first super admin is created with `otp_exempt`, so you can log in before WhatsApp is paired. Details: `WHATSAPP_INTEGRATION.md`, `docs/whatsapp-auth/`, `docs/whatsapp-chat/`.

## 4. Marketer panel and marketing API

Open `/marketer` with a user holding the `marketer` role (or the owner). The main areas are:
- **Suggestions Center:** everything Claude saves (products, bundles, sliders, offers, coupons, SEO, blog, seasonal themes, social content, media). A person reviews each one, then approves and applies it, and can roll it back.
- **Meta campaign drafts:** approve a draft, then publish it as PAUSED.
- **Social media library, analytics, gift finder admin, audit log.**
- **Marketing API tokens:** service tokens for the MCP server, with scopes `marketing:read`, `suggestions:write:<type>` (10 types), `meta:read` and `meta:draft`. The token is shown once.
- **Integration clients (n8n):** bearer and HMAC credentials for n8n (section 6).

Docs: `backend/docs/marketing/` (`MARKETER_PANEL.md`, `SUGGESTION_LIFECYCLE.md`, `APPROVAL_AND_EXECUTION_ENGINE.md`, `SECURITY.md`, `DEPLOYMENT.md`, `TESTING.md`).

## 5. MCP server and Claude (`mcp-server/`)

**Setup**
```bash
cd mcp-server
npm ci
cp .env.example .env
```
Fill in `.env`:
| Variable | Value |
|---|---|
| `LARAVEL_BASE_URL` | `https://3alraf.com` (local: your APP_URL) |
| `LARAVEL_SERVICE_TOKEN` | token from Marketer panel → Marketing API tokens |
| `MCP_PUBLIC_URL` | `https://mcp.3alraf.com` |
| `MCP_OAUTH_ENABLED` | `true` (needed for a Claude Pro connector) |
| `MCP_OAUTH_OWNER_PASSWORD` | `openssl rand -base64 24` |
| `MCP_BEARER_TOKEN` | `openssl rand -hex 32` (for Claude Code and scripts) |

```bash
npm run build && npm start
curl http://127.0.0.1:8787/health      # {"status":"ok","laravel":{"reachable":true}}
npm test                               # 85 tests, mocked Laravel
```

**Production:** `deploy/3alraf-mcp.service` (systemd, user `mcp`, secrets in `/etc/3alraf-mcp.env`, mode 600) and `deploy/nginx-mcp.3alraf.com.conf` (TLS reverse proxy). Point DNS `mcp.3alraf.com` at the server and issue a certificate (for example `certbot --nginx -d mcp.3alraf.com`).

**Connect Claude (Pro)**
1. Claude → Customize → Connectors → *Add custom connector*.
2. URL: `https://mcp.3alraf.com/mcp`. Authentication: *Sign in now*, with OAuth client set to *Register automatically*.
3. On the consent page, type the owner password and choose Allow.

**Connect Claude Code**
```bash
claude mcp add --transport http 3alraf https://mcp.3alraf.com/mcp \
  --header "Authorization: Bearer $MCP_BEARER_TOKEN"
```

**What to ask Claude**
- "Which products are slow-moving? Suggest a clearance bundle." Claude saves a bundle suggestion for your review.
- "Draft an Instagram caption for product X." Claude saves a social-content suggestion.
- "Create a paused Meta campaign draft for the bestsellers, 300 EGP/day." Claude saves a draft and gives you a review link.
- "Write a Ramadan SEO blog post." Claude saves a blog suggestion.

Every create, update or submit tool returns a `review_url`. Open it in the marketer panel to approve, edit or reject. The 60 tools are listed in `mcp-server/README.md`, `MCP.md` and `backend/docs/marketing/MCP_TOOLS.md`.

The server has been tested only against mocks and a local Laravel. A real Claude connection needs public HTTPS, and nobody has tried it yet. If sign-in fails, check that `MCP_OAUTH_REDIRECT_URIS` contains `https://claude.ai/api/mcp/auth_callback`.

## 6. n8n (`n8n/`)

n8n runs without Docker, pinned at 1.123.92 because the machine used to be on Node 20. On Node 22 or later you can upgrade, as described in `n8n/README.md`.

**Install and run**
```bash
cd n8n
npm ci
cp .env.example .env && chmod 600 .env
# set N8N_ENCRYPTION_KEY=$(openssl rand -hex 32), then:
./install-user-service.sh              # systemd --user, enabled and started
curl http://127.0.0.1:5678/healthz     # {"status":"ok"}
./n8n-cli.sh <cmd>                     # any n8n CLI command with .env loaded
```
Production uses the system unit `deploy/n8n-system.service` (user `n8n`, data in `/var/lib/n8n`, env in `/etc/n8n/n8n.env`) and `backend/deploy/nginx/n8n.conf` for `n8n.3alraf.com`.

**Give n8n access to Laravel**
1. Marketer panel → Integration clients → create "n8n (gift finder)" with scope `gift_finder:read`. Copy the bearer token and the HMAC secret, because both are shown once.
2. Or run `./provision-client.sh`. It rotates the credentials, writes the token into the n8n credential and the secret into `.env`, and restarts n8n.
3. Put `THREEALRAF_HMAC_SECRET` and the API base URL in `n8n/.env`.

**Request signing:** each request carries `Authorization: Bearer <prefix.token>`, `X-Timestamp` (unix seconds, ±300 s) and `X-Signature = hex(HMAC-SHA256(secret, "<ts>.<body>"))`. Workflow `91` does this for you. A shell test:
```bash
TS=$(date +%s); SIG=$(printf '%s.' "$TS" | openssl dgst -sha256 -hmac "$SECRET" -hex | sed 's/^.* //')
curl -H "Authorization: Bearer $TOKEN" -H "X-Timestamp: $TS" -H "X-Signature: $SIG" \
     https://3alraf.com/api/integration/v1/ping
```

**Workflows** (import with `./n8n-cli.sh import:workflow --separate --input=workflows/`)
| ID | When (Cairo) | What it does |
|---|---|---|
| 00 error handler | on failure | sends an Arabic alert with a link to the failed run |
| 01 weekly report | Sat 10:00 | gift-finder stats for 7 and 30 days |
| 02 untagged alert | Sat 10:30 | products or bundles with no gift tag |
| 03 occasion reminder | daily 09:00 | reminds you 21, 7 and 2 days before an occasion |
| 04 health monitor | every 15 min | alerts when the site is down, then again when it recovers |
| 90 notify owner | sub-workflow | formats and sends the notification |
| 91 API request | sub-workflow | signs and sends the GET request |

**Notifications:** by default they appear only in the n8n Executions list. Set `NOTIFY_WEBHOOK_URL` in `n8n/.env` to POST them to a WhatsApp or Telegram relay, or enable the SMTP node in workflow 90 for e-mail. Restart n8n after changing `.env`.

**Editing workflows:** edit `bin/build-workflows.py`, run `python3 bin/build-workflows.py`, then re-import.

**Adding your own workflow:** add an HTTP call to workflow 91 with the path you need. The available read endpoints are `ping`, `health`, `gift-finder/stats`, `gift-finder/untagged` and `gift-finder/occasions` (see `backend/docs/api/integration.md`). Scheduled Laravel jobs cover everything else.

## 7. Meta (Facebook and Instagram ads) and Canva

**Meta.** The Meta client lives in Laravel, so tokens never reach the MCP server or Claude.
1. In developers.facebook.com, create an app and a System User with an access token. It needs the `ads_management`, `ads_read`, `pages_read_engagement` and `instagram_basic` permissions.
2. Set these in `backend/.env`: `META_ACCESS_TOKEN`, `META_APP_ID`, `META_APP_SECRET`, `META_AD_ACCOUNT_ID`, `META_PAGE_ID` and `META_INSTAGRAM_USER_ID`.
3. Budget guard rails are `META_MIN_DAILY_BUDGET`, `META_MAX_DAILY_BUDGET`, `META_MAX_LIFETIME_BUDGET`, and `META_APPROVAL_VALID_HOURS`, which is how long an approval stays valid.
4. Run `php artisan config:cache`, then ask Claude for `get_meta_status`.

Flow: Claude drafts the campaign, a person approves it in the panel, then the panel publishes it as **PAUSED**. You activate it yourself in Meta Ads Manager. See `backend/docs/marketing/META_INTEGRATION.md`.

**Canva.** Add Canva's own connector in Claude (Customize → Connectors) and ask Claude to design creatives for the products. No project code is involved. Download the result and add it to the Social media library, or attach it to a suggestion.

## 8. Customer app (`app/`)

```bash
cd app
flutter pub get
flutter run --dart-define=API_BASE=https://3alraf.com
flutter build apk --release --dart-define=API_BASE=https://3alraf.com
```
Flutter is not installed on this server, so run the app and its builds on a development machine. At startup the app reads the shop's name, currency, languages and RTL setting from `/api/v1/app-config`. The API reference is `backend/docs/MOBILE_API.md` and `openapi-mobile.yaml`. See also `app/README.md` and `docs/mobile-app/`.

## 9. Going live checklist

1. DNS: `3alraf.com`, `mcp.3alraf.com` and `n8n.3alraf.com` all point at the server. Issue TLS certificates for all three.
2. Laravel: `APP_ENV=production`, `APP_DEBUG=false`, correct `APP_URL`, strong DB password, mail configured, caches built.
3. Supervisor programs running: queue, scheduler, reverb, whatsapp.
4. WhatsApp QR paired and an OTP login tested.
5. Real super admin created, and the demo accounts from the seeders removed (`seed:live --force`).
6. A Marketing API token created and put into `mcp-server`'s env. MCP is running on 127.0.0.1:8787 behind nginx.
7. Claude connector added and one read tool tested.
8. n8n running, the integration client created, and workflows 00 to 04 activated.
9. Meta credentials set (when you are ready to run ads).
10. Backups: MySQL dumps, `backend/storage`, `/var/lib/3alraf-whatsapp`, `/var/lib/n8n` and `/var/lib/3alraf-mcp`. Keep the `WA_ENCRYPTION_KEY` and `N8N_ENCRYPTION_KEY` values somewhere safe, because losing them forces a re-pair or breaks the stored credentials.

## 10. Troubleshooting

| Symptom | Check |
|---|---|
| MCP `/health` returns `degraded` | `LARAVEL_BASE_URL` is reachable and `LARAVEL_SERVICE_TOKEN` is valid and not revoked |
| MCP tool returns 403 | the token is missing the scope for that tool. Re-issue it with the needed scopes |
| Claude connector sign-in fails | the URL is public HTTPS, the owner password is right, and the redirect URI is in `MCP_OAUTH_REDIRECT_URIS` |
| n8n workflows get 401 | the clock is off by more than 5 minutes, the client was revoked, or the HMAC secret or bearer token is stale. Run `./provision-client.sh` |
| n8n won't start | `N8N_EXPRESSION_ENGINE=legacy` is set (Node 20 only), and `journalctl --user -u n8n` |
| No OTP on WhatsApp | gateway running, QR still paired, `WA_API_TOKEN` equals `WHATSAPP_SERVICE_TOKEN`, and the queue worker is running |
| Admin not updating live | Reverb is running and the `REVERB_*` values match in `.env` and the built assets (rerun `npm run build`) |
| Emails or jobs never run | the `3alraf-queue` and `3alraf-scheduler` programs are running |
| Changes to `.env` ignored | `php artisan config:cache` (or `config:clear`) and restart the supervisor programs |

## 11. Where the detailed docs are

| Topic | File |
|---|---|
| Laravel app, conventions, money rules | `backend/README.md`, `backend/CLAUDE.md`, `backend/docs/REQUIREMENTS.md`, `backend/docs/CALCULATIONS.md` |
| Marketing system | `backend/docs/marketing/*` |
| MCP server | `mcp-server/README.md`, `MCP.md` |
| n8n | `n8n/README.md`, `backend/docs/api/integration.md` |
| WhatsApp | `WHATSAPP_INTEGRATION.md`, `docs/whatsapp-auth/`, `docs/whatsapp-chat/` |
| Mobile app | `app/README.md`, `backend/docs/MOBILE_API.md`, `docs/mobile-app/` |
| Affiliate bonus | `AFFILIATE_BONUS_PLAN.md` |
| SEO | `backend/docs/marketing/SEO_MODULE.md`, `backend/docs/SEO_SETUP.md` |
