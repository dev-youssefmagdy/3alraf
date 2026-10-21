# 3alraf — Gap Analysis & Roadmap (2026-10-21)

Source: team review of `REQUIREMENTS.md`, `CALCULATIONS.md`, `PROGRESS.md` plus a static code audit (no tests were run; `vendor/` not installed).
Roles: Team Leader/PM/BA (this doc), Senior Fullstack, DB Designer/Optimizer, QA, Problem Solver.

## 0. Verdict
The 8 backend phases are well built and follow the layering rules. What is missing is everything that turns it into a launchable product:
**central website, real payments, mobile JSON API, production infrastructure, DB indexes/concurrency hardening, and test depth.**

## 1. Central website (business requirement — NOT DONE)
- `routes/central.php:17` → `Route::redirect('/', '/admin')`. The central domain only shows the super-admin login. `welcome.blade.php` is unrouted.
- Needed: public landing page on `domain.com` listing active tenants (country flag, name, currency, language) → links to `egy.domain.com`, `sa.domain.com`. Remember choice in a cookie; optionally *suggest* a store from `CF-IPCountry` / `Accept-Language` (never force-redirect crawlers). Add `GET /api/v1/tenants` for the Flutter app country picker.
- Unknown subdomain / inactive tenant → bare Laravel 404. Needed: Arabic RTL "store unavailable" page with a link back to the central landing page (`resources/views/errors/`).

## 2. Critical
| # | Area | Gap | Fix |
|---|------|-----|-----|
| C1 | Payments | All 5 gateways are stubs: `createPayment` returns fake `stub-…` ref and no redirect; webhook verify is generic HMAC (not Paymob/Stripe/PayPal/Fawry/PayTabs schemes); `parseWebhook` trusts payload (no amount/currency/status check); no idempotency; `refund()` is a no-op yet `ReturnService::refund` marks refunded. | Real drivers, Paymob + Stripe first. Verify provider signature, re-fetch transaction, check amount/currency, dedupe by event id, real refund call and only mark refunded on success. |
| C2 | API | No `routes/api.php`, no Sanctum, no API resources, no tenant resolution by header, no password reset / email verification, no push. | Sanctum tokens (customer / affiliate), `/api/v1`, API Resources, tenant by host or `X-Tenant`, API rate limiters, `Accept-Language`, `api` branch in `redirectGuestsTo` (401). |
| C3 | Deploy | No Dockerfile, compose, nginx wildcard vhost, supervisor, CI, backups. `.env.example` is dev-only (sqlite, debug, log mailer). | nginx `server_name domain.com *.domain.com`, wildcard cert (DNS-01), php-fpm image, compose (app, queue, scheduler, redis, mysql), `.env.production.example`, `spatie/laravel-backup`. |
| C4 | Proxy/host | No `trustProxies()` / `trustHosts()`. | Add both in `bootstrap/app.php`. |
| C5 | DB indexes | No tenant-leading indexes on hot tables. | See §5. |
| C6 | Stock concurrency | Reservation order can deadlock; `lockForUpdate()->first() ?? create()` can 500 on first-ever write; no `reserved >= 0` guard. | `ksort` lines before reserving, insert-ignore then lock, DB CHECK. |
| C7 | Payment proofs | Stored on the `public` disk, guessable URLs. | Private disk + authorized download route. |

## 3. High
- **Coupon bypass (QA, probable bug):** per-customer limit skipped for guests (`DiscountValidator.php:36`). Key on email/phone too.
- **Queues:** nothing is `ShouldQueue`; notifications/emails are synchronous. Queue them, run worker under supervisor.
- **Rate limits** only on login/register/reviews/checkout.store. Add to cart, quote, suggest, proof upload, returns, addresses, payout request, `login/validate` (email enumeration), webhooks; per-email login lockout.
- **Logout** does not invalidate the session when other guards remain logged in.
- **Branch trust in StockLedger:** `branch_id` not checked against tenant; assert `branch.tenant_id`.
- **Search/ranking performance:** JSON `LIKE` on product names is a full scan; `soldRanking/reviewRanking/coPurchased` are uncached and unbounded. Use generated columns + FULLTEXT, cache 5 min, limit 200.
- **Discount lookup** uses `LOWER(code)` and defeats the unique index.
- **Audit log:** spatie/activitylog installed but never used.
- **Flutter app** is the untouched template (no RTL/l10n, no deps).

## 4. Medium / Low
- Storefront gaps: brand page, tags filter, per-variant card price, footer app badges + payment icons, invoice PDF (HTML print only), tenant-creation artisan command, soft deletes missing on orders/customers/tenants/discount codes.
- `$guarded = []` on 4 item models (services only today).
- Stock costs `decimal(14,2)` → use `(14,4)` for avg/unit cost.
- Missing FKs: reviews, wishlist, product lists, custom page products, affiliate clicks → product. `orders.branch_id` nullOnDelete breaks cancel; use restrict.
- Soft-deleted product/variant `slug/sku/barcode` stay reserved forever.
- Order number derives from the global order id (leaks volume, has gaps); consider per-tenant counter.
- Images use hardcoded `public` disk; make disk configurable (CDN; local-only decision 2026-10-21, S3 removed). `APP_URL` for storage URLs is single-host.
- `/up` health check only on non-domain routes; verify on central host.
- Logging: daily channel + error tracker. `footer()` runs 2 uncached queries/page; `categoryRails` is N+1.
- Smoke script is manual, has no assertions, doesn't exit non-zero, English only.

## 5. DB fixes (migration to add)
```php
// orders
$t->index(['tenant_id','status','created_at']);
$t->index(['tenant_id','created_at']);
$t->index(['tenant_id','customer_id']);
// tenant_products
$t->index(['tenant_id','is_active','id']);
// stock_movements / stock_levels
$t->index(['tenant_id','product_id','product_variant_id','created_at']); // movements
$t->index(['tenant_id','product_id','product_variant_id']);              // levels
// order_items, reviews, discount usages, carts
order_items (tenant_id,product_id), (order_id,product_id)
reviews (tenant_id,status,product_id)
discount_code_usages (discount_code_id,customer_id), unique(order_id,discount_code_id)
carts (tenant_id,token)
// CHECKs
stock_levels reserved>=0; flash_sale_items sold<=qty_limit; discount_codes used_count<=usage_limit; reviews rating 1..5
```

## 6. CALCULATIONS.md corrections (doc + tests must change together)
1. `refund_net_loss` defined two ways (Returns: fee kept + return shipping; Reports-as-implemented: return shipping only). Profit section subtracting it literally double-counts the fee → define once as **return shipping cost only**.
2. `tax_rate` is documented as percent but formulas use `×tax_rate` and `r/(1+r)` as fractions → write `r = tax_rate/100`.
3. Tax on returns/refunds unspecified (exclusive and inclusive) → define.
4. Partial-return commission stated two ways; rounding drifts over multiple partial returns → single formula, computed from cumulative returned value.
5. Tier basis: "lifetime delivered sales" vs "monthly/lifetime or order count"; do returns reduce lifetime sales? → decide.
6. Flash sale clamp: split line (flash + regular) vs reject extra qty → code splits; document it.
7. Sales statuses: intro says confirmed→delivered, implementation includes processing/shipped; profit is by `created_at`, not delivery date → document or switch to delivered date.
8. Proportional discount distribution can give a tiny last line a share larger than its subtotal (negative `return_value`) → cap `line_discount ≤ line_subtotal`.
Worked example (73.50) verified correct.

## 7. Test gaps (add before launch)
Webhooks for 4 untested drivers + replay/idempotency/wrong amount; cross-tenant IDOR sweep (orders, returns, proofs, reports, token routes); role escalation; concurrent last unit; flash expiry between quote and place; guest→customer cart merge; backorder at checkout; refund cumulative > paid; rounding with many lines; central currency rounding; login tenant A on host B; make `smoke.mjs` assert and exit non-zero, add Arabic/RTL + wallet proof + coupon + customer + affiliate flows.

## 8. Roadmap
- **Sprint 1 — Launch blockers (1–2 wk):** central landing + tenant picker + error pages; DB index/CHECK migration; stock locking fixes; coupon guest bypass; CALCULATIONS.md fixes + tests; trustProxies/trustHosts; private proofs.
- **Sprint 2 — Money (2 wk):** Paymob + Stripe real drivers, idempotent webhooks, real refunds; remaining gateways (Fawry, PayTabs, PayPal) after; queued notifications; throttles; logout fix.
- **Sprint 3 — API (2 wk):** Sanctum, `/api/v1` auth, catalog/home/menu, cart, checkout, orders, affiliates, `GET /tenants`; API tests.
- **Sprint 4 — Flutter (4+ wk):** RTL/l10n, Riverpod + dio + go_router, tenant picker, customer flows; affiliate later.
- **Sprint 5 — Storefront polish + hardening:** brand page, tags, variant card price, badges/icons, invoice PDF, search FULLTEXT, caching, audit log, tenant-create command, backups, Docker/nginx/CI, security review.

## 9. Decisions needed from owner
1. Central landing: auto-suggest by country, or manual choice only?
2. Which gateway first (recommend Paymob for Egypt, Stripe/PayTabs for KSA)?
3. Tier basis: do returns reduce lifetime sales? Profit recognized by order date or delivery date?
4. Hosting target (VPS + Docker?) and whether to use S3/CDN for images.
