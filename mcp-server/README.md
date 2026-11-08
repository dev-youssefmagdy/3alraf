# 3alraf MCP server

Lets Claude use the 3alraf store's marketing API. It is a thin, separate Node/TypeScript service: Laravel stays the source of truth and validates every write again. Claude can read store data and save suggestions and Meta campaign drafts. **Claude cannot approve, reject, apply, roll back, publish or activate anything**; those are actions for a person in the marketer panel, and no tool or Laravel route for them exists here.

- Transport: Streamable HTTP at `POST /mcp` (stateless, JSON responses). `GET`/`DELETE /mcp` answer 405.
- Health: `GET /health`, no auth, no secrets. `200 {"status":"ok","laravel":{"reachable":true}}`, or `503` with `degraded`.
- Runtime: Node 20.19+ (tested on 20.20.2).

## Run it

```bash
npm ci
cp .env.example .env   # fill in the secrets
npm run build
npm start
```

`npm test` runs the suite against a mocked Laravel; it makes no outside network calls.

## Environment

See [`.env.example`](.env.example) for every variable. Required: `LARAVEL_BASE_URL`, `LARAVEL_SERVICE_TOKEN`, and at least one of `MCP_BEARER_TOKEN` or `MCP_OAUTH_ENABLED=true` with `MCP_OAUTH_OWNER_PASSWORD`. The server refuses to start with no authentication.

The service token comes from Marketer panel → Marketing API tokens, with scopes `marketing:read`, `suggestions:write`, `meta:read` and `meta:draft`.

## Authentication for Claude

### What Claude requires

From Anthropic's docs ([Authentication for connectors](https://claude.com/docs/connectors/building/authentication), [Add a connector that isn't in the directory](https://claude.com/docs/connectors/custom/remote-mcp), read 2026-11-08):

- Custom connectors are available on Free, Pro, Max, Ultra, Team and Enterprise.
- **OAuth 2.0** is the supported path by default (Dynamic Client Registration or Client ID Metadata Document).
- A **static bearer** through "Request headers" is a beta "for a limited set of organizations". Accounts without it do not see the section.
- A `client_credentials` grant is not supported.

So a static bearer alone is not enough for a Claude Pro connector unless that account has the beta. This server supports both:

| Method | For | Setup |
|---|---|---|
| OAuth 2.1 (PKCE S256, DCR, refresh rotation) | Claude custom connector | `MCP_OAUTH_ENABLED=true`, owner password |
| Static bearer (constant-time compare) | Claude Code, API clients, accounts with the Request headers beta | `MCP_BEARER_TOKEN` |

There is no identity provider in this project, so OAuth is a small single-owner authorization server: signing in means typing `MCP_OAUTH_OWNER_PASSWORD` on a consent page that shows which site you will be sent back to. Only callback URLs in `MCP_OAUTH_REDIRECT_URIS` can be registered. Attempts are limited per sign-in, per address, and overall.

### Connect in Claude

1. Customize → Connectors → **Add custom connector**.
2. Server URL: `https://mcp.3alraf.com/mcp`.
3. Authentication: **Sign in now**. OAuth client: **Register automatically**. ("Use Claude's published identity" needs Client ID Metadata Document support, which this server does not advertise.)
4. On the consent page, enter the owner password and choose **Allow**.

Claude Code with the static bearer:

```bash
claude mcp add --transport http 3alraf https://mcp.3alraf.com/mcp --header "Authorization: Bearer $MCP_BEARER_TOKEN"
```

### Verified and assumed

**Verified**

- Claude's auth requirements above, from Anthropic's current docs (not from memory).
- The OAuth flow end to end with automated tests: 401 + `resource_metadata`, discovery, registration, consent, PKCE, token exchange, refresh rotation, resource binding.
- Tools called through the official MCP client SDK against this machine's Laravel.

**Not verified: no connection from real Claude has been made.** That needs a public HTTPS deployment. The server is built to the documented requirements, but only a real connection will confirm it. Assumptions to check at that point:

- Claude's Dynamic Client Registration sends `https://claude.ai/api/mcp/auth_callback` as a redirect URI (documented, not observed).
- Claude accepts this metadata as produced by the MCP SDK's auth router, and the stateless server's 405 on `GET /mcp`.
- A Claude Pro account does not have the Request headers beta. If it does, the static bearer also works there.

If sign-in fails, Anthropic's [troubleshooting page](https://claude.com/docs/connectors/building/troubleshooting) lists the usual causes.

## Tools

60 tools. Source of truth: `src/tools/`.

| Group | Tools |
|---|---|
| Store and analytics (26) | `get_store_overview`, `get_products`, `get_product_details`, `get_product_profitability`, `get_product_images`, `get_categories`, `get_brands`, `get_inventory`, `get_low_stock_products`, `get_best_sellers`, `get_slow_moving_products`, `get_sales_summary`, `get_orders_summary`, `get_campaign_candidates`, `get_marketing_performance`, `get_bundles`, `get_sliders`, `get_current_offers`, `get_coupons`, `get_current_theme`, `get_static_pages`, `get_seo_data`, `get_existing_blog_posts`, `get_blog_categories`, `get_seasonal_calendar`, `get_gift_finder_stats` |
| Suggestion reads (6) | `list_suggestions` (alias `get_existing_suggestions`), `get_suggestion` (alias `get_suggestion_details`), `get_suggestion_status`, `get_suggestion_application_result` |
| Suggestion creation (10) | `create_product_suggestion`, `create_bundle_suggestion`, `create_slider_suggestion`, `create_offer_suggestion`, `create_coupon_suggestion`, `create_seo_suggestion`, `create_blog_suggestion`, `create_seasonal_theme_suggestion`, `create_product_social_content_suggestion`, `create_media_asset_suggestion` |
| Suggestion management (2) | `update_suggestion_draft`, `submit_suggestion_for_review` |
| Media library (3) | `get_product_social_media_assets`, `list_social_media_assets`, `get_social_media_asset` |
| Meta reads (10) | `get_meta_status`, `get_meta_ad_accounts`, `get_meta_account`, `get_meta_campaigns`, `get_meta_ad_sets`, `get_meta_ads`, `get_meta_campaign_insights`, `get_meta_ad_insights`, `list_campaign_drafts`, `get_campaign_draft` |
| Meta drafts (3) | `create_campaign_draft`, `update_campaign_draft`, `submit_campaign_for_approval` |

Create, update and submit tools return a `review_url` so a person can act in the panel. `tests/tools.test.ts` fails if a tool name implies approve, reject, apply, roll back, publish or activate. Its allowlist has two entries: `get_suggestion_application_result` (read-only report) and `submit_campaign_for_approval` (hands the draft to a person).

## Security

- **Untrusted data:** every tool result sits in an `<untrusted_store_data>` block after a notice that its content is data, not instructions. Angle brackets are JSON-escaped so store text cannot close the block. Review links are the only server-authored text outside it, and a link from the store is used only if it points at the panel.
- **Secrets:** the service token stays on the server and is sent only to Laravel. Redirects are not followed. Known secret fields and token shapes are removed from tool output. Logs carry no tool arguments, results or query strings, and pino redacts authorization and token fields.
- **OAuth storage:** the state file holds only SHA-256 hashes of tokens, mode 0600. Delete it to sign every connector out.
- **Input:** every tool has a strict Zod schema with unknown arguments refused, and there is no URL, SQL, path or table parameter. Laravel validates again.
- **Limits:** rate limit per client, failed-auth limit per address, body limit, Host header allowlist, binds to 127.0.0.1 by default.

## Deployment

Files in [`deploy/`](deploy/):

- `3alraf-mcp.service`: systemd unit (user `mcp`, secrets in `/etc/3alraf-mcp.env`, state in `/var/lib/3alraf-mcp`).
- `nginx-mcp.3alraf.com.conf`: TLS reverse proxy for `mcp.3alraf.com`. Review it against the server's existing nginx setup before enabling.

```bash
sudo useradd --system --no-create-home --shell /usr/sbin/nologin mcp
npm ci && npm run build
sudo install -m 600 -o root -g root .env.production /etc/3alraf-mcp.env
sudo cp deploy/3alraf-mcp.service /etc/systemd/system/
sudo systemctl daemon-reload && sudo systemctl enable --now 3alraf-mcp
curl -s http://127.0.0.1:8787/health
```

Logs are JSON on stdout (journald). Rotate the service token in the marketer panel and restart the service.
