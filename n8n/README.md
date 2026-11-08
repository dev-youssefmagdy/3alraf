# n8n for 3alraf (gift-finder workflows)

Self-hosted n8n, **no Docker**, phase P2b of `MARKETER_PLAN.md`. n8n only *reads* the store through the signed integration
API (`/api/integration/v1`, `backend/docs/api/integration.md`) and notifies the owner. It cannot change anything in the store.

| | Local | Production |
|---|---|---|
| URL | http://n8n.3alraf.loc (after `setup-local-sudo.sh`), always http://127.0.0.1:5678 | https://n8n.3alraf.com |
| Process | `systemd --user` unit `n8n.service` (user `joe`) | system unit, dedicated user `n8n` |
| Data | `n8n/data/` (gitignored; SQLite at `data/.n8n/database.sqlite`) | `/var/lib/n8n` |
| Env | `n8n/.env` (gitignored, 0600) | `/etc/n8n/n8n.env` (root:n8n 0640) |
| Proxy | Apache `backend/deploy/apache/000-n8n.3alraf.loc.conf` | nginx `backend/deploy/nginx/n8n.conf` |

## Version: n8n 1.123.92 (and why not 2.x)

`npm view n8n engines`: n8n **2.9+ needs Node ≥ 22.16** and **2.40+ needs Node ≥ 24** (latest stable is 2.46.6). The machine
has Node 20.20.2, so the choices were the last Node-20 2.x release (2.8.4, Feb 2026, no longer patched) or the **1.123.x
maintenance line, which still gets security fixes** (1.123.92 on 2026-11-02, engines `>=20.19 <=24.x`). 1.123.92 is pinned.

### Why `isolated-vm` is stubbed
1.123.92 depends on `@n8n/expression-runtime` → `isolated-vm@^6.2` (native, needs Node ≥ 22, does not compile on Node 20 — the
first `npm install` failed). It is only loaded by the opt-in **`vm` expression engine**. So:
- `package.json` adds `"isolated-vm": "file:./stubs/isolated-vm"` + `"overrides": {"isolated-vm": "$isolated-vm"}` (a stub that throws if loaded);
- `.env` sets **`N8N_EXPRESSION_ENGINE=legacy`** (1.123 defaults to `vm`; without this n8n crashes at start).

n8n logs a deprecation warning: the legacy engine evaluates expressions without isolation. Acceptable for a single-owner instance
whose only editor is the owner; **the real fix is Node 22/24** → then upgrade to current 2.x, drop the stub/override and the
`N8N_EXPRESSION_ENGINE` line (see Upgrades).

## Install / start / stop (local)

```bash
cd /var/www/3alraf/n8n
npm ci                                  # installs the pinned n8n (~830 MB)
cp .env.example .env && chmod 600 .env  # then: N8N_ENCRYPTION_KEY=$(openssl rand -hex 32)
./install-user-service.sh               # systemd --user unit, enabled + started
systemctl --user status|restart|stop n8n
journalctl --user -u n8n -f             # or data/logs/n8n.log
curl http://127.0.0.1:5678/healthz      # {"status":"ok"}
./n8n-cli.sh <command>                  # any n8n CLI command with .env loaded (stop the service for DB-heavy commands)
```
Linger is **off** for `joe`, so the user service starts at login and stops at logout. To keep it running at boot:
`sudo loginctl enable-linger joe` (or `sudo ./setup-local-sudo.sh --linger`).

Fallback without systemd --user: `./start.sh --daemon` (nohup, pid file `n8n.pid`, logs `data/logs/stdout.log`),
`./start.sh --status`, `./start.sh --stop`.

### Subdomain (needs sudo once)
```bash
sudo /var/www/3alraf/n8n/setup-local-sudo.sh          # add --linger to also enable linger for joe
```
It enables `proxy proxy_http proxy_wstunnel headers rewrite`, installs `000-n8n.3alraf.loc.conf` into sites-available + `a2ensite`
(the `000-` prefix makes it load before `3alraf.loc.conf`, whose `ServerAlias *.3alraf.loc` would otherwise catch the name),
adds `127.0.0.1 n8n.3alraf.loc` to `/etc/hosts`, runs `apache2ctl configtest` and reloads Apache. It is idempotent.

### Owner account
Created through `POST /rest/owner/setup`: e-mail `owner@3alraf.loc`, password in **`data/OWNER_CREDENTIALS.txt`** (0600,
gitignored). Change e-mail/password in n8n → Settings → Personal, then delete that file.

## Workflows (`workflows/*.json`)

| File | Trigger (Africa/Cairo) | What it does |
|---|---|---|
| `00-error-handler` | Error Trigger | Any failed production run of the workflows below → Arabic message (workflow, node, error, execution link) → notify |
| `01-gift-finder-weekly-report` | Sat 10:00 (`0 10 * * 6`) | `ping` + `gift-finder/stats?days=7` + `?days=30` → Arabic summary (searches, completion/cart/order rates, gift orders + revenue, AOV, avg budget, top occasions, hints) → notify |
| `02-gift-untagged-alert` | Sat 10:30 | `gift-finder/untagged?limit=100` → list of products/bundles with no gift tag + admin edit links + tags page → notify only if any |
| `03-gift-occasion-reminder` | daily 09:00 | `gift-finder/occasions?days=21` → reminder when an occasion is exactly **21 / 7 / 2** days away; Hijri (Umm al-Qura) dates flagged *approximate* → notify only if due |
| `04-health-monitor` | every 15 min | `health` → alert on first failure, then hourly while down, plus a recovery message (state in workflow static data) |
| `90-notify-owner` | sub-workflow | Formats `[3alraf] title / text / link`; output always in the execution; optional webhook + e-mail |
| `91-api-request` | sub-workflow | Signs and sends GET requests to the integration API, returns `{label,path,ok,status,data,error}` per request |

01–04 also have a **Run manually** trigger (editor "Test workflow" / `n8n execute`). 01–04 and 91 use `00` as error workflow;
00 and 90 deliberately have none (a failing notifier would otherwise loop through the error handler).

**Notification channels** (no external account needed by default): every notification is the output of the `90` execution
(n8n → Executions). Optional: `NOTIFY_WEBHOOK_URL` in `.env` → JSON POST `{title,text,level,source,link,message,sent_at}` (e.g. a
WhatsApp gateway endpoint in `whatsapp-service/` or a Telegram relay); e-mail → `NOTIFY_EMAIL_ENABLED=true`, create an SMTP
credential, enable the "Send email (SMTP, disabled)" node in `90`. Restart n8n after editing `.env`.

**Editing**: either edit in the n8n editor, or edit `bin/build-workflows.py` (source of the JSON), run `python3 bin/build-workflows.py`
and re-import: `./n8n-cli.sh import:workflow --separate --input=workflows/` then `systemctl --user restart n8n` (import keeps the IDs;
re-activate with `./n8n-cli.sh update:workflow --id=<id> --active=true` if needed, then restart).

## How workflows authenticate

Integration client **#1 "n8n (gift finder)"**, scope `gift_finder:read` only (the gift-finder endpoints + ping/health). It was
created through `App\Services\Integration\IntegrationClientService` — the same service the admin page `/admin/integration-clients` uses.

- **Bearer token** `{prefix}.{token}` → n8n credential **"3alraf integration (bearer)"** (`httpHeaderAuth`, `Authorization: Bearer …`,
  encrypted with `N8N_ENCRYPTION_KEY`). Used only by the HTTP node in `91`.
- **HMAC secret** → `THREEALRAF_HMAC_SECRET` in `.env`. The `Sign request` Code node computes
  `X-Signature = hex HMAC-SHA256(secret, "{X-Timestamp}.")` (GET → empty body) with `require('crypto')`.

Why the split: n8n Code nodes cannot read credentials, so the signing secret has to come from the environment
(`N8N_BLOCK_ENV_ACCESS_IN_NODE=false`, `NODE_FUNCTION_ALLOW_BUILTIN=crypto`). Keeping the token in an encrypted credential means a
leaked `$env` alone cannot call the API. This is fine for a single-owner instance; if more people ever get editor access, they can
read `$env` — then move to a dedicated n8n instance or rotate. Production keeps the same settings in `/etc/n8n/n8n.env` (0640).

### Rotate / revoke the client
```bash
cd /var/www/3alraf/n8n && ./provision-client.sh        # prod: ./provision-client.sh /var/www/html (Laravel path)
```
Rotates client "n8n (gift finder)" via `IntegrationClientService::rotate` (creates it if missing/revoked), writes the new bearer into
the n8n credential (`import:credentials`, same credential id), the new secret into `.env`, restarts n8n. Nothing is printed. Old
token/secret stop working immediately. Rotating from the admin page instead works too, but then paste the token into the credential
and the secret into `.env` by hand. Revoke = admin page → Revoke (all workflows then fail with 401 → error handler).

## Run results (2026-11-08, local, against http://3alraf.loc/api/integration/v1)

Run with `N8N_RUNNERS_BROKER_PORT=5689 ./n8n-cli.sh execute --id=<id>` (the alternate broker port avoids clashing with the
running service).

| Workflow | Result | Output |
|---|---|---|
| 01 weekly report | **success** | 3 signed calls 200; summary built and sent to 90 (store "Egypt Store", 0 searches in 7/30 days, hint "no searches this week") |
| 02 untagged alert | **success** | 200; 0 untagged of 1,192 active products / 0 bundles → "All tagged" branch, no notification |
| 03 occasion reminder | **success** | 200; nothing within 21 days of 2026-11-08 (next: Ramadan 2027-02-08 ~, approximate) → no reminder |
| 04 health monitor | **success** | 200, `status: ok`, `database: true` → healthy, no alert |
| 00 error handler | **success** | Verified with a temporary every-minute workflow that threw (activated in the service, then deleted): error execution → 00 → 90 produced `[3alraf][خطأ] فشل سير عمل n8n: …` with node, error and execution link |
| 90 / 91 | **success** | as sub-workflows of the runs above |

Notification branches with data (02 with untagged items, 03 with occasions 21/7/2 days away incl. Hijri, 04 down → still down →
recovery, 01 failing on 401) were checked by running the Code nodes with mocked API responses.
01–04 are **active** in the service (logged "Activated workflow …"). `healthz` and `healthz/readiness` → `{"status":"ok"}`.

Laravel fix found while wiring 01: `gift-finder/stats` returned `"by_occasion":[]` when there were no sessions (docs say an object).
Now always an object (`Api\Integration\GiftFinderController::stats`), test in `tests/Feature/IntegrationApiTest.php`.

## Production (n8n.3alraf.com)

1. **DNS**: `A n8n.3alraf.com → <server IP>` (AAAA if IPv6).
2. **Node 22 or 24** recommended (then use current n8n 2.x, see Upgrades). With Node 20 use this folder as is.
3. **User + dirs**: `sudo useradd --system --home /var/lib/n8n --shell /usr/sbin/nologin n8n`;
   `sudo mkdir -p /opt/n8n /var/lib/n8n/files /var/log/n8n /etc/n8n && sudo chown -R n8n:n8n /var/lib/n8n /var/log/n8n`.
4. **Code**: copy `package.json`, `package-lock.json`, `stubs/`, `start.sh`, `n8n-cli.sh`, `provision-client.sh`, `bin/`, `workflows/`
   to `/opt/n8n`; `cd /opt/n8n && sudo -u n8n npm ci --omit=dev` (owner of /opt/n8n: root, readable by n8n).
5. **Env**: `/etc/n8n/n8n.env` from `.env.example` with the *Production* block (https URLs, `N8N_SECURE_COOKIE=true`,
   `N8N_USER_FOLDER=/var/lib/n8n`, new `N8N_ENCRYPTION_KEY` from `openssl rand -hex 32`); `chown root:n8n`, `chmod 640`.
   `provision-client.sh` expects `.env` next to it: symlink `/opt/n8n/.env -> /etc/n8n/n8n.env` or run its steps by hand.
6. **systemd**: `sudo cp deploy/n8n-system.service /etc/systemd/system/n8n.service && sudo systemctl daemon-reload && sudo systemctl enable --now n8n`.
7. **TLS + nginx**: `sudo certbot certonly --webroot -w /var/www/certbot -d n8n.3alraf.com` (or `--nginx`), copy
   `backend/deploy/nginx/n8n.conf` to `/etc/nginx/sites-available/`, enable, `sudo nginx -t && sudo systemctl reload nginx`.
   Certbot's timer renews automatically. Optionally restrict the editor by IP (commented block in the config).
8. **Owner**: open https://n8n.3alraf.com and create the owner (strong password, then enable 2FA in Settings → Personal).
9. **Client + workflows**: create the integration client (`provision-client.sh /var/www/html` or the admin page), import
   `workflows/` with `n8n import:workflow --separate --input=workflows/`, activate 01–04, restart.
10. Optional on Laravel: `INTEGRATION_IPS=127.0.0.1` (or the n8n server IP) to allow the integration API only from n8n.

**Backups**: the encryption key + `/var/lib/n8n/.n8n/` (SQLite DB with credentials, workflows, executions) are everything.
Daily: `sqlite3 /var/lib/n8n/.n8n/database.sqlite ".backup '/backup/n8n-$(date +%F).sqlite'"` plus a copy of `/etc/n8n/n8n.env`.
Without the same `N8N_ENCRYPTION_KEY` the credentials in a restored DB cannot be decrypted. Workflows are also in git (`workflows/`).

**Upgrades**: read the n8n release notes first; back up; change the pinned version in `package.json`; `npm install`; restart; check
`healthz` and the Executions list. Moving to Node ≥ 22: remove `"isolated-vm"` from dependencies and `overrides`, delete `stubs/`,
remove `N8N_EXPRESSION_ENGINE=legacy`, then pin a current 2.x (2.x blocks `$env` by default — keep
`N8N_BLOCK_ENV_ACCESS_IN_NODE=false` for the signing node — and enables task runners by default).

## Troubleshooting

| Symptom | Fix |
|---|---|
| n8n exits at start: "Could not initialize the vm expression engine" | `N8N_EXPRESSION_ENGINE=legacy` missing in `.env` (Node 20) |
| `npm ci` fails building isolated-vm | the stub/override in `package.json` was removed while still on Node 20 |
| 401 `unauthenticated` in 91 | token/secret mismatch, client revoked/rotated elsewhere, or clock skew > 300 s → `./provision-client.sh`, check `timedatectl` |
| 403 `forbidden_scope` | client lacks `gift_finder:read` (admin page → edit scopes) |
| 403 `ip_not_allowed` | `INTEGRATION_IPS` on Laravel does not include the n8n host |
| "THREEALRAF_API_BASE / THREEALRAF_HMAC_SECRET missing" | `.env` not loaded → restart the service; values set? |
| `Cannot find module 'crypto'` in Code node | `NODE_FUNCTION_ALLOW_BUILTIN=crypto` missing |
| `n8n.3alraf.loc` shows the shop | the n8n vhost is not loaded before `3alraf.loc.conf` → `apache2ctl -S`, file must be `000-…` |
| editor says "connection lost" | websocket not proxied: `proxy_wstunnel` enabled? nginx `Upgrade/Connection` headers present? |
| login loop on https | `N8N_SECURE_COOKIE=true` needs https; on plain http keep it false (local only) |
| n8n stops when you log out | enable linger (`sudo loginctl enable-linger joe`) |
| CLI changes (import/activate) not visible | restart n8n (`systemctl --user restart n8n`) |
| `n8n execute` fails with "Missing node to start execution" | the workflow needs a Manual Trigger (01–04 have "Run manually") |
