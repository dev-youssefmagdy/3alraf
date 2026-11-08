# Integration API (n8n) — `/api/integration/v1`

Read-only API for the n8n gift-finder workflows (P2 of MARKETER_PLAN.md). Everything else of the former Growth API was removed (404).
Code: `routes/api_integration.php`, `App\Http\Middleware\Api\IntegrationAuth`, `App\Http\Controllers\Api\Integration\*`, `config/integration.php`.

## Credentials
Marketer panel → Integrations & access → **Integration clients (n8n)** (`/marketer/integration-clients`, moved from `/admin/integration-clients` in P4, perms `integration_clients.view|manage`): create a client,
choose scopes, copy the **bearer token** (`{prefix}.{token}`) and the **HMAC secret** (both shown once). Rotate = new token + secret, old ones
stop immediately. Revoke = client stops immediately. Scopes: `read` (everything), `gift_finder:read` (gift-finder endpoints only).

## Every request
| Header | Value |
|---|---|
| `Authorization` | `Bearer {prefix}.{token}` |
| `X-Timestamp` | Unix time in seconds (UTC); must be within ±300 s of the server clock (replay window) |
| `X-Signature` | lower-case hex `HMAC-SHA256(secret, "{X-Timestamp}.{raw request body}")` — for GET the body is the empty string, so the string to sign is `"{X-Timestamp}."` |
| `Accept` | `application/json` |

n8n (Code node, JavaScript — the real implementation is workflow `91 - 3alraf API request` in `/var/www/3alraf/n8n/workflows/`,
see `n8n/README.md`; the bearer comes from the n8n credential "3alraf integration (bearer)", the secret from `$env`):
```js
const crypto = require('crypto');   // NODE_FUNCTION_ALLOW_BUILTIN=crypto
const ts = Math.floor(Date.now() / 1000).toString();
const sig = crypto.createHmac('sha256', $env.THREEALRAF_HMAC_SECRET).update(ts + '.').digest('hex');
return [{ json: { ts, signature: sig } }];   // HTTP node: headers X-Timestamp / X-Signature / Accept + Authorization via credential
```
Shell: `TS=$(date +%s); SIG=$(printf '%s.' "$TS" | openssl dgst -sha256 -hmac "$SECRET" -hex | sed 's/^.* //')`.

Errors (JSON `{"success":false,"error":code,"message":...}`): `401 unauthenticated` (missing/unknown/revoked token, bad signature, stale timestamp —
deliberately uniform), `403 forbidden_scope`, `403 ip_not_allowed` (optional `INTEGRATION_IPS` allowlist), `429 too_many_requests`
(120/min per client, `Retry-After`; plus 300/min per IP on the prefix), `422` bad query parameter.
Every authenticated call is logged in `integration_requests` (client, method, path, status; no body, no IP), kept 30 days (`integration:prune`, daily 03:10).

## Endpoints (all GET, response `{"success":true,"data":{...}}`)
| Path | Scope | Data |
|---|---|---|
| `ping` | any client | `store{name,url,currency,timezone}`, `client`, `scopes`, `server_time` |
| `health` | any client | `status` ok/degraded, `database`, `gift_finder_enabled`, `server_time` |
| `gift-finder/stats?days=30` (1..365) | `read` or `gift_finder:read` | `enabled`, `days`, `sessions`, `completion_rate`, `cart_rate`, `no_result`, `avg_budget`, `orders`, `orders_revenue`, `orders_aov`, `order_rate`, `by_occasion{key:count}` (always a JSON object, `{}` when empty) |
| `gift-finder/untagged?limit=100` (1..500) | same | `products[{id,name,sku,slug,gift_suitable}]` = active products with no gift recipient/occasion tag; `bundles[{id,name,slug,items}]` = live bundles none of whose products is tagged; `counts{products,bundles,active_products,active_bundles}` |
| `gift-finder/occasions?days=45` (1..366) | same | `days`, `today`, `occasions[{key,date,days_left,approximate,calendar,label{ar,en}}]` from `config/gift_finder.php` `occasion_calendar` (Gregorian fixed dates; Hijri via the Umm al-Qura calendar, always `approximate:true`) |

No endpoint writes anything; there is no price, stock, refund, order or customer-data capability.
