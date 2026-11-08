# Marketing system — deployment

The owner's step-by-step guide is `implementation_steps_of_mcp.docx` in the repo root. This page is the short reference for developers.

## Order
1. Back up the database and files. `2027_01_01` and `2027_01_02` drop tables permanently.
2. Deploy Laravel: `composer install --no-dev`, `npm ci && npm run build`, `php artisan migrate --force`, `php artisan db:seed --class=PermissionSeeder --force`, `php artisan optimize`, `php artisan queue:restart`, reload PHP-FPM (`opcache.validate_timestamps=0`).
3. Raise upload limits (below).
4. Node 24, then n8n 2.x (`n8n/README.md` → Upgrades).
5. DNS and certificates for `n8n.3alraf.com` and `mcp.3alraf.com`. `A` records are required: Claude connectors are IPv4-only.
6. n8n, then MCP server, then service token, then Claude connector, then Meta.

## Deploy files
| Service | Files | Runtime |
|---|---|---|
| Laravel | `deploy/nginx/app.conf`, `deploy/nginx/cdn.conf`, `deploy/php/php.ini`, `deploy/supervisor/3alraf.conf`, `docker-compose.yml` | php-fpm 8.3, queue worker, scheduler |
| n8n | `n8n/deploy/n8n-system.service`, `deploy/nginx/n8n.conf`, `n8n/.env.example` | `127.0.0.1:5678`, user `n8n`, `/etc/n8n/n8n.env` |
| MCP | `mcp-server/deploy/3alraf-mcp.service`, `mcp-server/deploy/nginx-mcp.3alraf.com.conf`, `mcp-server/.env.example` | `127.0.0.1:8787`, user `mcp`, `/etc/3alraf-mcp.env` |

If production uses `docker-compose.yml`, the compose nginx owns ports 80/443 and cannot reach `127.0.0.1` on the host. The docx section 5.4 lists the changes; that route is untested.

## Upload limits
| Limit | Value | File |
|---|---|---|
| `upload_max_filesize` / `post_max_size` / `max_input_time` | `256M` / `300M` / `300` | PHP-FPM ini, or `deploy/php/php.ini` (currently 12M / 16M) |
| `client_max_body_size`, `fastcgi_read_timeout` | `300m`, `300s` | Main site server block in `deploy/nginx/app.conf` (currently 16m, 60s) |
| App limits | `SOCIAL_MEDIA_VIDEO_MAX_KB=204800`, `PRODUCT_VIDEO_MAX_MB` (default 50) | `.env` |

## Environment
Variables are documented in `backend/.env.example` (Meta, social media, product video, `INTEGRATION_IPS`), `mcp-server/.env.example` and `n8n/.env.example`.

`META_GRAPH_VERSION` defaults to `v26.0`. Check Meta's versions table before going live and move to a newer version if needed.

## Readiness checks
- `php artisan migrate:status` has no pending migrations; `php artisan schedule:list` shows the marketing jobs.
- `curl https://mcp.3alraf.com/health` returns `status: ok`; `POST /mcp` without auth returns 401 with `WWW-Authenticate`.
- `curl http://127.0.0.1:5678/healthz` on the server returns `{"status":"ok"}`.
- Run the final checklist in docx section 14.

## Operations
- Service token rotation: create new, update `/etc/3alraf-mcp.env`, restart, revoke old.
- Back up `/etc/3alraf-mcp.env`, `/etc/n8n/n8n.env` and `/var/lib/n8n/.n8n/`. Without the n8n encryption key, stored credentials cannot be read.
- Logs: `journalctl -u 3alraf-mcp`, `journalctl -u n8n`, `storage/logs/`.
