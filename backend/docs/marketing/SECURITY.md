# Marketing system — security

Summary of the controls in place, with links to the detail. Permission matrix: [MARKETER_PANEL.md](MARKETER_PANEL.md).

## Access
| Surface | Authentication | Limits |
|---|---|---|
| `/marketer` | Admin session, guard `admin`, one `can:` per route | CSRF. Approve and apply are panel-only |
| `/api/internal/marketing` | Hashed bearer token (sha256), scopes, expiry, revoke. Uniform 401 | 120/min per token. Log has no body, query, IP or PII |
| `/api/integration/v1` | Bearer + `X-Timestamp` (±300 s) + HMAC-SHA256 | 120/min per client, optional `INTEGRATION_IPS`. Read-only |
| MCP `/mcp` | OAuth 2.1 (PKCE S256, DCR, refresh rotation) and/or constant-time static bearer | Per-client and failed-auth limits, body limit, Host allowlist, binds to 127.0.0.1 |
| MCP consent page | `MCP_OAUTH_OWNER_PASSWORD` (16+ characters), shows the redirect host | Attempts limited. Only `MCP_OAUTH_REDIRECT_URIS` can register |
| n8n editor | n8n owner + 2FA | Secure cookie in production, optional IP restriction in nginx |

Owner / super-admin only by default: `marketing.tokens.manage`, `marketing.audit.view`, `integration_clients.manage`, `meta_ads.publish`.

## What an AI client can never do
No tool or route exists to approve, reject, apply, roll back, publish or activate. Enforced by route absence and by tests (`mcp-server/tests/tools.test.ts`, `tests/Feature/MarketingSuggestionsApiTest.php`). Tools that hand work to a person return a `review_url`.

## Prompt injection
Store text is treated as data. Each MCP result is wrapped in `<untrusted_store_data>` after a notice, with angle brackets JSON-escaped. Only review links pointing at the panel are shown outside the block. See `mcp-server/src/untrusted.ts`.

## Secrets
| Secret | Location |
|---|---|
| Meta token, app secret | `backend/.env` only. Sent as a header, never in a URL, response or log; Meta error text is scrubbed |
| Service token, OAuth owner password | `/etc/3alraf-mcp.env` (root, 600). Redirects are not followed. Pino redacts auth fields |
| OAuth state | `/var/lib/3alraf-mcp/oauth-state.json` (0600), token hashes only |
| n8n encryption key, HMAC secret | `/etc/n8n/n8n.env` (root:n8n, 640) |
| n8n bearer | Encrypted n8n credential. Editors can read `$env`, so keep the instance single-owner |

## Spending and irreversible actions
- Meta: everything is created PAUSED and read back. Budget guards in `config/meta.php`. See [META_INTEGRATION.md](META_INTEGRATION.md).
- Coupons check a margin guard. Applying is idempotent, targets are checked for staleness, and rollback exists where the type supports it. See [APPROVAL_AND_EXECUTION_ENGINE.md](APPROVAL_AND_EXECUTION_ENGINE.md).
- Migrations `2027_01_01` and `2027_01_02` are irreversible. Back up first.

## Media privacy
The social library sits on the private disk with authenticated or signed-URL access, `no-store` and a sandbox CSP. Files never appear in the public gallery, feeds, API v1 or sitemap. Uploads are checked by file content. Canva links are limited to `canva.com` and `www.canva.com`. See [SOCIAL_MEDIA_LIBRARY.md](SOCIAL_MEDIA_LIBRARY.md).

## Open items
- No real connection from Claude has been tested. `mcp-server/README.md` lists the assumptions.
- n8n below 2.x on Node 20 runs the legacy, non-isolated expression engine. Move to Node 24 and n8n 2.x.
- Service tokens default to 90 days. Rotation is manual.
