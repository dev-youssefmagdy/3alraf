# Marketing internal API (`/api/internal/marketing`) — P4 + P5 + P6

JSON API consumed by the MCP server (read endpoints; P5 adds suggestion writes — create / edit own draft / submit, never approve or apply) (P7, `mcp-server/`). Laravel stays the source of truth; the MCP server never touches the
database. Routes: `routes/api_marketing.php` (names `marketing-api.*`), registered in `bootstrap/app.php` with the `api` middleware group only
(no session, no cookies, no CSRF). Distinct from the n8n integration API (`/api/integration/v1`, HMAC, `IntegrationClient`), which is unchanged.

## 1. Authentication

| Item | Value |
|---|---|
| Header | `Authorization: Bearer {token}` (+ `Accept: application/json`) |
| Token format | `{prefix}.{secret}` — prefix `mkt_` + 12 chars, secret 48 chars. Shown **once** at creation. |
| Storage | `marketing_api_tokens` (name, token_prefix, `token_hash` = sha256 of the whole plain token, scopes json, last_used_at, expires_at, revoked_at, created_by). |
| Issue / revoke | Marketer panel → Integrations & access → **Marketing API tokens (MCP)** (`/marketer/api-tokens`, perm `marketing.tokens.manage`). Expiry 30/90 (default)/180/365 days or never. Code path: `App\Services\Marketing\MarketingTokenService::create(MarketingTokenData)`. |
| Middleware | `marketing.token:{scope}` = `App\Http\Middleware\Api\MarketingTokenAuth`: prefix lookup + constant-time hash compare (`hash_equals`), revoked/expired ⇒ 401, missing scope ⇒ 403, per-token throttle ⇒ 429 (`Retry-After`). Uniform 401 for unknown / forged / revoked / expired tokens. Clears every auth guard (an admin session never authenticates the API). |
| Throttle | `config('marketing.throttle_per_minute')` (default 120 / minute / token, env `MARKETING_API_THROTTLE`). |
| Request log | `marketing_api_requests`: token_id, method, route name, status, duration_ms, created_at. **No body, no query, no IP, no PII.** Viewer: `/marketer/api-log` (perm `marketing.audit.view`). Pruned daily 03:15 by `marketing:prune-requests` (> `marketing.request_log_days` = 30). |

### Scopes (`config/marketing.php`)

| Scope | Status | Grants |
|---|---|---|
| `marketing:read` | enabled | every endpoint below |
| `suggestions:write:{type}` (one per type: product, bundle, slider, offer, coupon, seo, blog, seasonal_theme, social_content, media_asset) | enabled | `POST suggestions/{type}`, `PATCH suggestions/{id}` (own pending/edited draft), `POST suggestions/{id}/submit`. Never approve / reject / apply / rollback (no such route exists). Reading suggestions needs `marketing:read`. |

## 2. Envelope (`App\Support\Marketing\MarketingResponse`)

```json
{
  "success": true,
  "data": { ... } | [ ... ],
  "meta": { "generated_at": "2026-11-08T10:00:00+02:00", "currency": "EGP", "timezone": "Africa/Cairo",
            "page": 1, "per_page": 25, "total": 1192, "last_page": 48, "range": {"from": "...", "to": "...", "days": 30} },
  "warnings": [ { "code": "missing_data", "field": "cost", "message": "...", "coverage_percent": 78.95 } ]
}
```
`page / per_page / total` are `null` on non-list endpoints. Errors use the same shape:

```json
{ "success": false, "error": "unauthenticated|forbidden_scope|too_many_requests|not_found|validation_failed", "message": "...",
  "errors": { "to": ["..."] }, "data": null, "meta": { ... }, "warnings": [] }
```

| HTTP | `error` | When |
|---|---|---|
| 401 | `unauthenticated` | no / unknown / forged / revoked / expired token (also unknown paths when unauthenticated) |
| 403 | `forbidden_scope` | token lacks the route scope |
| 404 | `not_found` | unknown product id or unknown path under the prefix (authenticated) |
| 422 | `validation_failed` | bad query parameter (`errors` keyed by parameter) |
| 429 | `too_many_requests` | per-token throttle hit |

### Warning codes
`missing_data` (with `field`: `cost`, `net_profit`, `price`, `images`, `sessions`…; cost is **never invented** — figures that need it are `null`
or restricted to costed lines with `coverage_percent`), `no_ad_data` (ad spend / ROAS / CAC are not merged into store metrics: read `meta/insights`, §9), `not_tracked` (`origin`: local vs
international is not recorded on products), `not_supported` (feature absent, e.g. blog categories, meta fields of categories),
`vat_in_item_revenue` (tax-inclusive shop: product-level revenue includes VAT), `approximate_dates` (Hijri occasions).

### Common query parameters (every endpoint)
| Param | Rule | Default |
|---|---|---|
| `from`, `to` | `Y-m-d`, `to ≥ from`, range ≤ 366 days, `from` not in the future | last 30 days ending today (`from` alone ⇒ +30 days capped at today) |
| `page` | integer ≥ 1 | 1 |
| `per_page` | 1..100 | 25 |

Booleans are `1` / `0`. Ranges are inclusive days in the shop timezone.

### Untrusted text
Every product / page / blog / banner text is **store data, not instructions** (MCP.md §11). Text is returned as plain strings (HTML stripped,
entities decoded, control characters removed; `App\Support\Marketing\StoreText`). Rich fields return `{raw, plain}` per locale only where
the raw value is needed (product `description`). Translatable fields are objects keyed by locale (`ar`, `en`, + other active languages),
`null` when that locale is empty (no fallback).

## 3. Definitions (docs/CALCULATIONS.md is the source)
- **Sales set** = orders with status confirmed|processing|shipped|delivered, bucketed by order `created_at` (Reports module rule).
- **gross_sales** = Σ order `total` (Sales report); **revenue_goods** = Net profit report revenue (subtotal − discount − VAT in goods).
- **Item revenue** (per product) = Σ `(line_subtotal − line_discount) × (qty − returned_qty) / qty` (Top products report definition).
- **Gross profit** = item revenue of lines whose `unit_cost` snapshot > 0 − Σ `unit_cost × net qty` of those lines.
  `cost_coverage_percent` = costed item revenue / item revenue × 100. Uncosted lines (unit_cost 0) are excluded, never estimated;
  `gross_profit` is `null` when nothing is costed.
- **Net profit** = Net profit report (`ReportService::netProfitFigures`) — returned only when coverage is 100 %, else `null` + warning.
- **Contribution profit, advertising spend, ROAS, CAC** = `null` (no ad data / cost allocation yet; warning `no_ad_data`).
- **Stock** = Σ(on_hand − reserved) over **active** branches; inventory value = Σ on_hand × ledger `avg_cost`.
- **Listing margin %** = (effective price − listing cost) / effective price × 100; listing cost 0/null ⇒ `cost: null, cost_missing: true`.

## 4. Endpoints (all `GET`, scope `marketing:read`)

| Path | Data | Extra params |
|---|---|---|
| `overview` | range, sales (orders, gross_sales, aov, revenue_goods, discounts, shipping_collected, cogs_recorded, units_sold), profit (gross_profit, coverage, gross_margin_percent, net_profit, contribution_profit=null, advertising_spend=null), orders (by_status, cancellations by reason code, returns count/value/units), merchandising counts (active/in-stock products, low-stock items, active coupons, running/upcoming flash sales, live banners / home sliders / bundles), gift_finder stats | — |
| `products` | paginated summaries: id, sku, slug, type, name{ar,en}, active, brand, categories, pricing{price, sale_price, effective_price, cost, cost_missing, margin_percent}, discount{on_sale, flash_sale}, stock{available,in_stock}, image, origin=null, gift tags, url | `category` (id, incl. sub-categories), `brand` (id), `active`, `in_stock`, `search` (name ar/en, sku), `sort` = newest\|oldest\|name\|price_asc\|price_desc\|stock_asc\|stock_desc |
| `products/{id}` | full product: barcode, description{locale:{raw,plain}}, storefront_wording overlay, tags, pricing, discount{on_sale, running flash_sales, in_bundles}, stock (+min alert), variants[{sku, barcode, label, active, pricing, price_source, stock, images}], images, seo{slug, locales{meta_title, meta_description, missing_*}}, gift tags, urls per locale | — |
| `products/{id}/profitability` | sold (units, returned, orders, revenue, revenue_costed, cost, uncosted_units, gross_profit, coverage, gross_margin_percent, contribution/net/ad = null), by_variant[], current_listing[] (unit economics now), definitions | range |
| `products/{id}/images` | product + variant images (url, thumb_url, sort_order, variant_id); `videos: []` (product videos arrive in P8) | — |
| `categories` | id, parent_id, depth, path, slug, name, description, active, image, active_products, url | — |
| `brands` | id, slug, name, active, logo, active_products, url, origin=null | — |
| `inventory` | per product/variant (active branches): on_hand, reserved, available, alert_qty, low, stock_value_at_avg_cost, uncosted_units | — |
| `low-stock` | items at/below their alert qty (`LowStockService`, same as admin Low stock) | — |
| `best-sellers` | products with units sold in the range, ordered units desc, revenue desc, id asc | range |
| `slow-moving` | active, in-stock products with ≤ `marketing.slow_moving_max_units` (2) units sold in the range; days_since_last_sale (any time), stock_value_at_cost | range |
| `sales-summary` | periods[{period, orders, gross_sales, subtotal, discounts, shipping, cogs_recorded, net_profit_recorded, aov}] | `group_by` = day\|week\|month (week = ISO week start) |
| `orders-summary` | orders_total, by_status, sales_orders, pending, cancellations{total, by_reason, rate_percent}, returns{documents, units, value, returned_cogs, return_shipping_cost}, by_channel — aggregates only, **no customer data** | range |
| `campaign-candidates` | ranked products with score, components, margin_percent, stock_cover_days, explanation{en,ar}, data_completeness, warnings, rank (formula §5) | range |
| `marketing-metrics` | store (orders, gross_sales, aov, new_customers count), coupons (orders_with_coupon, discount_total, discount_share_percent, by_code), flash_sales performance, channels, affiliate_orders, conversion_proxies (Gift Finder rates; site_conversion_rate = null), gift_finder, advertising{spend, roas, cac = null} | range |
| `bundles` | id, slug, name, description, price, active, live, window, purchasable, available, regular_total, savings_percent, components_cost, margin_percent, sold_in_range (whole bundles), items, url | range |
| `banners` | homepage sliders (`placement=home_hero`, `is_home_slider`) and other banners: title, images per locale, safe link, window, state, sort, age_days | `placement` = home_hero\|home_strip\|category_tile\|popup, `state` |
| `offers` | flash sales: window, state (live\|upcoming\|ended\|inactive), items with regular_price, flash_price, cost, flash_margin_percent, **negative_margin**, qty_limit / sold / remaining | `state` = all\|live\|upcoming\|ended\|inactive |
| `coupons` | discount codes: type, value, min_order, max_discount, free_shipping, scope + product_ids / category_ids, usage_limit, per_customer_limit, used_count, remaining_uses, window, state (incl. exhausted), usage_in_range{usages, discount, orders_total} | range, `state` |
| `theme` | current storefront tokens from `resources/css/theme.css` (`@theme`): brand scale, accent, neutrals, status colours, font, primary_action, raw `tokens` | — |
| `static-pages` | id, slug, title, active, excerpt (300 chars plain), words per locale, url | `include_content=1` (plain body) |
| `seo-data` | per entity: slug, active, meta_fields_supported, has_missing_meta, image, urls, locales{title, description, missing_title, missing_description, long_title (> 60), short_description (< 50), long_description (> 160), source} | `entity_type` = product (default)\|category\|brand\|bundle\|static_page\|blog_post\|site, `id`, `missing_only=1` |
| `blog-posts` | id, slug, title, excerpt, meta_description, state (draft\|scheduled\|published), published_at, url | `include_content=1`, `state` |
| `blog-categories` | always `[]` + warning `not_supported` (no blog categories exist) | — |
| `seasonal-calendar` | today, upcoming occasions (key, label{ar,en}, date, days_left, approximate, calendar) from `config/gift_finder.php` `occasion_calendar`, configured rules | `days` 1..366 (default 90) |
| `gift-finder/stats` | enabled, sessions, completion / cart / order rates, gift orders + AOV, by_occasion, bundle stats (`GiftFinderService::stats`) | `days` 1..366 (default = range length) |

Product social-media library and Meta: §9 (P6). Existing suggestions: §8 (P5).
`theme` (P5): `runtime_overrides` = the active seasonal theme (`theme_id`, `key`, `version`, `name`, `tokens`, `activated_at`) or null; `overridable_tokens` = the CSS variables a `seasonal_theme` suggestion may set.

## 5. Campaign-candidate score (`App\Services\Marketing\CampaignScorer`)

```
score (0..100) = 35·velocity + 30·margin + 20·stock_cover + 15·recency        (weights: config marketing.campaign.weights)

velocity    = units_sold / max units_sold among the eligible products (0 if nobody sold)
margin      = clamp(margin% / 50, 0, 1); margin% = (effective price − listing cost) / effective price × 100
              listing cost missing ⇒ margin = 0 + item warning missing_data(cost) + data_completeness.cost = false
stock_cover = sold > 0: clamp((available / (units_sold / range_days)) / 60, 0, 1)
              no sales: clamp(available / 10, 0, 1)
recency     = last sale in the range d days before `to`: 1 if d ≤ 7, else max(0, 1 − (d − 7) / (range_days − 7)); no sale ⇒ 0
```
Eligible: active, priced, not deleted, available ≥ 1. Order: score desc, available desc, product id asc (deterministic; ties never depend on
the database order). Components rounded to 4 decimals, score to 2. `explanation` is generated only from these numbers (ar + en).
Example: 10 units in 30 days (max), margin 50 %, 40 available, last sale yesterday ⇒ 35 + 30 + 20 + 15 = **100**; 2 units, margin 10 %,
last sale 20 days ago ⇒ 7 + 6 + 20 + 6.52 = **39.52**.

## 6. Examples

```bash
T='mkt_ab12cd34ef56.xxxxxxxx…'
curl -s -H "Authorization: Bearer $T" -H 'Accept: application/json' \
  'https://3alraf.com/api/internal/marketing/products?in_stock=1&sort=stock_desc&per_page=50'
curl -s -H "Authorization: Bearer $T" 'https://3alraf.com/api/internal/marketing/products/42/profitability?from=2026-10-01&to=2026-10-31'
curl -s -H "Authorization: Bearer $T" 'https://3alraf.com/api/internal/marketing/campaign-candidates?per_page=10'
curl -s -H "Authorization: Bearer $T" 'https://3alraf.com/api/internal/marketing/seo-data?entity_type=product&missing_only=1'
```

## 7. Tests
`tests/Feature/MarketingApiTest.php` (every endpoint: 401 none/revoked/expired/forged, 403 wrong scope, 200 envelope; pagination +
date validation 422; profitability with and without cost; campaign ranking determinism + exact scores; request log fields, throttle 429,
prune; untrusted text; merchandising/content flags) and `tests/Feature/MarketerPanelTest.php` (token create/revoke from the panel).

## 8. Suggestions (P5) — `suggestions*`

Full contract for the MCP tools (per-type JSON schemas + examples): **docs/marketing/MCP_SUGGESTION_TOOLS.md**. Lifecycle / engine:
SUGGESTION_LIFECYCLE.md, APPROVAL_AND_EXECUTION_ENGINE.md.

| Method + path | Scope | Purpose |
|---|---|---|
| `GET suggestions` | marketing:read | list; filters `type`, `status`, `target_entity_type`, `target_entity_id`, `mine=1` (created by this token), `search`, `from`/`to` (created date, only when sent), `sort` = newest\|oldest\|priority\|updated, `page`, `per_page` |
| `GET suggestions/{id}` | marketing:read | detail: payload, compare (current vs proposed), validation {errors, warnings}, revisions, result entity, `own` |
| `GET suggestions/{id}/status` | marketing:read | status, version, content_hash, submitted, approved (valid approval for the current version), approval expiry, last decision, applied / rolled back, last_error |
| `GET suggestions/{id}/result` | marketing:read | executions (apply / rollback / publish / schedule: status, before, after, warnings, error) + result entity |
| `POST suggestions/{type}` | suggestions:write:{type} of that suggestion | create (`type` = product\|bundle\|slider\|offer\|coupon\|seo\|blog\|seasonal_theme\|social_content\|media_asset) → 201 |
| `PATCH suggestions/{id}` | suggestions:write:{type} of that suggestion | edit own suggestion while `pending` / `edited` (→ new version + revision; `payload`, when sent, replaces the whole payload) |
| `POST suggestions/{id}/submit` | suggestions:write:{type} of that suggestion | mark a draft (`draft: true` on create) ready for review; idempotent |

Create body: `title` (3..200, required), `description`, `reasoning`, `priority` (low\|normal\|high\|urgent), `target_entity_type` + `target_entity_id`
(per type), `payload` (required, the type schema), `expires_at` (future, < 1 year; default +60 days), `source` (mcp\|scheduled), `source_meta`
{model, tool, run_id, conversation_id, workflow}, `source_data` (free JSON of the references used, ≤ 20 KB), `force_reason` (10..500, only to
re-create a recently rejected suggestion), `draft` (bool). **Every other key is refused** (`422 validation_failed`, error "Field not allowed").
Links must be internal to the store; HTML is sanitised (allowlist); no SQL / table / path / arbitrary URL field exists.

Errors (same envelope; `data` carries details): `422 validation_failed` (`errors` per field), `422 target_not_found`, `409 duplicate_suggestion`
(`data.existing_id`: an open suggestion with the same type + target + normalised payload exists), `409 recently_rejected` (`data.rejected_id`,
`cooldown_days`; send `force_reason`), `403 not_owner` (another token's suggestion), `409 not_editable` (status does not allow edits —
e.g. after approval), `404 not_found`. Approve / reject / apply / rollback / archive / publish paths answer 404 (no route).

## 9. Product social-media library + Meta (P6)

Scopes added: `meta:read`, `meta:draft`. Library reads use `marketing:read`. Tool map for P7: MCP_SUGGESTION_TOOLS.md "Media library and Meta tools".

### Media library (metadata only, read-only, scope `marketing:read`)
| Endpoint | Returns | Filters |
|---|---|---|
| `GET media` | items: metadata, `file{original_name, mime, size_bytes, width, height, duration_seconds, checksum_sha256}`, `preview_url`, `thumb_url`, `preview_expires_in_minutes`, `internal_only: true` | `product_id`, `kind`, `status`, `platform`, `campaign`, `tag`, `search`, `sort`, `page`, `per_page` |
| `GET media/{id}` | same + `versions[]` | — |
| `GET products/{id}/social-media` | the product's items (`missing_data` warning when none) | as `media` |

Never returns storage paths or bytes. `preview_url` is signed, valid 10 minutes and for that file version only; fetching it needs no token. Items are created through `social_content` / `media_asset` suggestions; uploads happen in the panel.

### Meta reads (scope `meta:read`, cached)
| Endpoint | Query |
|---|---|
| `GET meta/status` (configured, graph version, safety flags; no secrets) | — |
| `GET meta/ad-accounts`, `GET meta/account` | — |
| `GET meta/campaigns` | `limit` 1..100, `after` |
| `GET meta/adsets` | `campaign_id`, `limit`, `after` |
| `GET meta/ads` | `adset_id`, `limit`, `after` |
| `GET meta/insights` | `object_id`, `level` (account/campaign/adset/ad), `date_preset` or `from` + `to` |
| `GET meta/drafts` | `status`, `mine=1`, `search`, `page`, `per_page` |
| `GET meta/drafts/{id}` | full content, `checks`, `approvals`, `meta` ids, `safety`, `review_url` |

List cursors: `meta.next_cursor`. Budgets come as `*_minor` (Meta minor units) and major units. Unavailable metrics are `null`, never invented. Errors: `503 not_configured`, `502 connection_failed`, `422|502 meta_error`.

### Meta drafts (scope `meta:draft`)
| Endpoint | Notes |
|---|---|
| `POST meta/drafts` | 201; optional `submit: true` |
| `PATCH meta/drafts/{id}` | own draft in `draft`, `pending_approval` or `rejected`; each sent top-level field replaces the stored one; a content change means version + 1, back to `draft` |
| `POST meta/drafts/{id}/submit` | own draft; idempotent |

Body (`App\Support\Marketing\MetaDraftRules`; unknown fields 422): `name`, `objective` (`OUTCOME_AWARENESS|TRAFFIC|ENGAGEMENT|LEADS|SALES`), `product_ids[]`, `budget_type` (`daily|lifetime`), `budget_amount`, `start_at`, `copy{primary_text, headline, description}`, `cta`; optional `end_at` (required for lifetime), `currency`, `audience{description, age_min, age_max, genders, interests[]}`, `locations{countries[], cities[]}`, `placements{automatic, publisher_platforms[], facebook_positions[], instagram_positions[]}`, `creative{asset_ids[], canva_url, notes}`, `landing_url` (this store only), `optimization_goal`, `expected_kpis{}`, `risks`, `assumptions`.

Refusals: `not_owner` 403, `not_editable` / `frozen` / `illegal_transition` 409, `validation_failed` 422. **There is no approve, reject, publish or activate endpoint.**

Tests: `tests/Feature/SocialMediaLibraryTest.php`, `tests/Feature/MetaAdsTest.php`.
