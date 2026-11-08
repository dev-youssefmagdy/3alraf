# Marketer panel (`/marketer`) — P4 (+ P5 Suggestions Center, P6 social library + Meta drafts)

Same login as the admin (`/admin/login`, guard `admin`; after login admins land on `/admin/welcome`, which shows a "لوحة التسويق / Marketing
panel" card when allowed). Routes `routes/marketer.php` (names `marketer.*`, middleware `web`, `auth.active`, `admin.locale`, `auth:admin`,
gate `marketing.panel` = holds any marketing permission, then one `can:` per route). Guests are redirected to the admin login.
Layout `<x-layouts.marketer>` (admin base + components + CSS, plum sidebar / gold accent, header "لوحة التسويق", RTL/LTR from the admin
session locale, language switch, mobile menu button). Strings: `lang/ar/marketing.php`, English in `lang/en/admin.php` → `marketing`
(served as the `marketing` group by `AppServiceProvider::registerLangShims`).

## Navigation (`App\Support\Marketing\MarketerNav`)
An item is shown only if its route exists (`Route::has`) **and** the admin holds its permission; groups without visible items disappear.

| Group | Items (route → permission) | Phase |
|---|---|---|
| Overview | Dashboard `marketer.dashboard` → `marketing.view`; Analytics `marketer.analytics.index` → `marketing.analytics` (hidden: no route yet) | P4 |
| Suggestions | overview + products, bundles, banners, offers, coupons, themes, SEO, blog, social, execution & approval log (`marketer.suggestions.*` → `ai_suggestions.view`; actions: edit/review/re-snapshot `ai_suggestions.edit`, approve `.approve` (+ `ai_seo`/`ai_blog`/`ai_theme.approve` per type), reject `.reject`, apply/rollback `.apply`, archive `.archive`, blog publish `.apply`+`ai_blog.approve`, theme activate/revert `ai_theme.approve`) | **P5 live** — docs AI_SUGGESTIONS_ARCHITECTURE.md |
| Social & ads | media library `marketer.media.*` → `ai_media.view` (upload `.upload`, edit / submit / archive `.edit`, approve / reject `.approve`, delete `.delete`), docs SOCIAL_MEDIA_LIBRARY.md; Meta drafts `marketer.meta-ads.*` → `meta_ads.view` (create / edit / submit / archive `.draft`, approve / reject `.approve`, publish PAUSED `.publish`), docs META_INTEGRATION.md | **P6 live** |
| Gift Finder | stats & history `marketer.gift-finder.index`, tags `marketer.gift-finder.tags` → `gift_finder.view` (save/suggest: `gift_finder.manage`) | P4 (moved) |
| Marketing tools (open in /admin) | banners/slider `banners.view`, discount codes `discount_codes.view`, flash sales `flash_sales.view`, bundles `bundles.view`, blog posts `blog_posts.view`, static pages `static_pages.view`, custom pages `custom_pages.view`, SEO settings `settings.view`, affiliates `affiliates.view`, product lists `product_lists.view`, reviews `reviews.view` | links only |
| Integrations & access | Marketing API tokens `marketer.tokens.*` → `marketing.tokens.manage`; API request log `marketer.api-log.*` → `marketing.audit.view`; n8n integration clients `marketer.integration-clients.*` → `integration_clients.view` (create/rotate/revoke: `integration_clients.manage`) | P4 |

"Admin panel" link (sidebar + header) is shown when the admin holds any permission outside the marketing set (or is super-admin).
The admin sidebar has a top-level "لوحة التسويق" link (gate `marketing.panel`). Old URLs `/admin/gift-finder[/…]` and
`/admin/integration-clients[/…]` answer **301** to `/marketer/…` (GET).

**Adding a page (P5/P6/P7):** register the route in `routes/marketer.php` with its `can:` middleware, add/enable the item in
`MarketerNav::groups()` (placeholders already exist with the expected route names), add strings to `lang/ar/marketing.php` +
`lang/en/admin.php` `marketing`. If the permission is new, add it to `config/permissions.php` and `MarketingPermissions::PANEL`
(and `MARKETER` if the preset role should get it) and ship a migration that runs `PermissionSeeder`.

## Dashboard (`/marketer`, `MarketingDashboardService` → `MarketingAnalyticsService`)
Date range (default last 30 days, max 366): gross sales, orders, AOV, gross profit (costed items only, coverage % hint, `missing_data`
warning; needs `marketing.analytics`, otherwise units sold), active coupons / running flash sales / live banners / live bundles / low-stock
counts (with "manage" links when allowed), sales trend chart (Chart.js, as the admin reports), Gift Finder stats (last N days), top sellers,
slow movers, low stock, pending-suggestions card (P5: count of `status = pending`, link to the Suggestions Center).
Without `marketing.view` the dashboard redirects to the first panel page the admin may open (403 if none).

## Permissions matrix (guard `admin`, `config/permissions.php`)

| Permission | owner / super-admin | role `marketer` | Used by |
|---|:-:|:-:|---|
| marketing.view | ✓ | ✓ | dashboard |
| marketing.analytics | ✓ | ✓ | profit block of the dashboard (P5+: analytics pages) |
| marketing.tokens.manage | ✓ | — | API tokens page (create / revoke) |
| marketing.audit.view | ✓ | — | API request log |
| ai_suggestions.view / create / edit / approve / reject / apply / archive | ✓ | ✓ | P5 Suggestions Center (approve/apply are dashboard-only, never via API) |
| ai_media.view / upload / edit / delete / approve | ✓ | ✓ | P6 social-media library (also the admin product tab) |
| ai_seo.approve, ai_blog.approve, ai_theme.approve | ✓ | ✓ | P5 typed approvals |
| meta_ads.view / draft / approve | ✓ | ✓ | P6 Meta drafts |
| meta_ads.publish | ✓ | — | P6 publish (PAUSED) — spends money, owner only by default |
| gift_finder.view / manage | ✓ | ✓ | Gift Finder pages |
| integration_clients.view | ✓ | ✓ | n8n clients list |
| integration_clients.manage | ✓ | — | create / rotate / revoke n8n clients |

The role `marketer` is created once (migration `2027_01_04_000000_create_marketing_api_tables` / `PermissionSeeder`); later edits in
Roles are kept (re-seeding never resets it). It holds no admin-panel module permission, so its admin shell shows only the panel link.

## Tables
`marketing_api_tokens`, `marketing_api_requests` (see API_DOCUMENTATION.md). Scheduled `marketing:prune-requests` daily 03:15.
P5: `marketing_suggestions` (+ `_revisions`, `_approvals`, `_executions`, `_activity_logs`), `marketing_themes`, `seo_redirects`, `seo_metas`
(APPROVAL_AND_EXECUTION_ENGINE.md). Scheduled `marketing:expire-suggestions` daily 03:25.
P6: `social_media_assets` (+ `_versions`), `meta_campaign_drafts`, `meta_campaign_approvals`, `meta_audit_logs`.
