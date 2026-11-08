# 3alraf Store — Requirements & Architecture

Laravel 13 / PHP 8.3 / MySQL (db `3alraf`) in `/var/www/3alraf/backend`. Design reference: raneen.com/ar (RTL-first, mega-menu, sliders, category tiles, product rails, red/white palette).

## 1. Stack & conventions (MANDATORY)
- Blade + TailwindCSS (Vite) + jQuery/AJAX + toastr.js + Yajra DataTables (server-side) + Spatie-style permissions (spatie/laravel-permission).
- **Layers**: Controller (thin) → FormRequest → Service → Repository (interface + Eloquent impl, bound in a provider) → Model. Use **DTOs** (readonly classes in `app/DTOs`) between controller and service. (User wrote "TDO" = DTO.)
- **Enums** in `app/Enums` (backed, with `label()`): OrderStatus, PaymentStatus, PaymentType, DiscountType, StockMovementType, AffiliateTier basis, etc.
- **Blade components** for everything reusable (`resources/views/components`): form inputs, modal, datatable wrapper, card, stat-card, product-card, layout shells.
- **AJAX validation before submit**: every form posts first to a `/validate` endpoint (same FormRequest) via shared JS `ajax-form.js`; show field errors inline; only then submit. Toastr for all success/error feedback.
- Money: integer minor units? NO — use `decimal(14,2)`; all math through `App\Support\Money` / `OrderCalculator` per `docs/CALCULATIONS.md`.
- Strings via lang files (`lang/{locale}/*.php`) for static text; DB translations for dynamic data (see §6).

## 2. Single-store model (one host, one panel, one database)
- One shop on one host = `APP_URL` (local `http://3alraf.loc`, production `https://domain.com`). Admin panel `/admin`, storefront `/{locale}/...` (`/` → `/{default_locale}`), mobile API `/api/v1`, integration API (n8n, read-only: ping / health / gift-finder reads, bearer + HMAC, `docs/api/integration.md`) `/api/integration/v1`, payment webhooks `/webhooks/*`. Marketer panel `/marketer` (same `admin` guard/login, perms `marketing.*` etc., role `marketer`, `docs/marketing/MARKETER_PANEL.md`) and the Marketing internal API `/api/internal/marketing` for the MCP server (hashed service tokens + scopes, `docs/marketing/API_DOCUMENTATION.md`; read + P5 suggestion create/edit-own-draft/submit, never approve/apply). P5 Suggestions Center at `/marketer/suggestions` (approval + execution engine, `docs/marketing/AI_SUGGESTIONS_ARCHITECTURE.md`); seasonal storefront themes are runtime CSS-variable overrides (`marketing_themes`), changed slugs get 301s (`seo_redirects`). P6: private product social-media library `/marketer/media` + admin product tab (private disk only, `docs/marketing/SOCIAL_MEDIA_LIBRARY.md`) and Meta campaign drafts `/marketer/meta-ads` (approval bound to a content hash; everything created in Meta is PAUSED, `docs/marketing/META_INTEGRATION.md`). No subdomains, no country picker.
- **Shop settings** (name, country, currency code + symbol, default_locale, locales[], timezone, payment credentials, COD, SEO, Gift Finder switch `gift_finder.enabled` (default ON), customer-cancel switch `customer_cancel.enabled` (default OFF), …) live in the key-value `settings` table (`App\Models\Setting`, helper `setting($key, $default)`, secrets encrypted; defaults in `config/settings.php`). Admin page: **Settings → General**. The timezone is applied once at boot.
- **Catalog**: `products` / `product_variants` hold both the master data and the selling data (price, sale price, cost, min stock alert, gift fields, active flag). There is no separate listing table.
- **One admin account type**: `admins` (`App\Models\Admin`, guard + provider `admin`, login by phone + OTP); roles/permissions (Spatie) all on guard `admin` (`config/permissions.php`, `PermissionSeeder`; Super Admin gets everything). Customers guard `web`, affiliates guard `affiliate`.
- **Legacy hosts**: old shop subdomains `{any}.APP_HOST` answer a permanent 301 to `APP_URL` (same path + query) via the global middleware `LegacyHostRedirect` (the only subdomain-aware code); `webhooks/*` and `api/*` are served directly there so gateways and old app builds keep working. `GET /api/v1/tenants` is a deprecated alias returning the one shop for old app builds (`slug` = `MOBILE_LEGACY_STORE_SLUG`, default `egy`).
- Storefront URLs are always prefixed `/{language}`; RTL for `ar`.
- **History (2026-11 conversion)**: until 2026-11 the project was multi-tenant (central domain + one subdomain per country store, rows scoped by `tenant_id`, separate central/tenant admin panels and guards). The conversion migration `database/migrations/2026_12_31_000000_convert_to_single_store.php` merged everything into one shop; the plan and log are in `/var/www/3alraf/REMOVE_MULTI_TENANCY_PLAN.md` and `docs/plans/single-store/`. Older docs in `docs/prompts/`, `docs/plans/` and `docs/qa/` describe the multi-tenant era and are marked historical.

## 3. Catalog
- **Attributes**: name (translatable), type (select/color/text), values (translatable, color hex optional).
- **Products**: translatable name/description/meta, slug, brand, category (many), images, public gallery videos (`product_videos`, MP4/WebM on the media disk, optional poster; images and videos share one `sort_order`, merged by `Storefront\ProductGallery`; the private social-media library never appears here), type `simple|variable`, base sku, base barcode (simple).
- **Variants** (variable products): combination of attribute values, own sku, **unique barcode (EAN-13 generated, Code128 render)**, images, weight.
- **Categories**: unlimited-depth tree (nested set or `parent_id`+`path`), translatable, image, icon, sort, active; breadcrumbs; mega menu.
- Selling data on the product / variant itself: price, sale price, cost, active flag (Catalog page = quick price/status editor over `products`); stock per branch.

## 4. Commerce modules
- **Branches**, **Stock** (per branch × product/variant), **Stock Transfer** (branch→branch, statuses draft/in-transit/received, movement ledger), **Stock Adjustment** (increase/decrease with reason). All changes write `stock_movements` ledger.
- **Orders**: cart (open to guests) → checkout (web: signed-in customer only, guests are redirected to login and the cart is kept; `/api/v1` checkout still accepts guests), items snapshot (name, sku, barcode, unit price, cost), totals computed ONLY by `OrderCalculator` following `docs/CALCULATIONS.md`. Statuses enum with history log; fires notification events. Returns & refunds sub-flows. Invoice (printable/PDF), wallet payment proof upload.
- **Flash sales**: start/end datetime, products/variants with sale price or %, qty limit, countdown on storefront.
- **Discount codes**: percent/fixed, min order, max discount cap, usage limit total/per customer, date range, scope (all/products/categories), free-shipping option.
- **Customers**: addresses (city), order history, wishlist, reviews.
- **Affiliates** (own accounts, guard `affiliate`): own login/dashboard; **tier commission** (tiers by monthly/lifetime sales or order count → percentage); every product in dashboard shows a **barcode/QR image of its unique link**: `APP_URL/{locale}/product/{slug}?utm_source=affiliate&aff={aff_uuid}`; click tracking (cookie 30d), conversion → commission record (pending→approved→paid after return window), payout requests, stats.
- **Recommended / Most sold / Most reviewed** lists: admin pins products manually (ordered, shown FIRST), remaining slots filled by algorithm (recommended: same-category/co-purchase/popularity; most sold: sum qty delivered orders; most reviewed: review count/avg), de-duplicated, limit configurable. One reusable `ProductListResolver`.
- **Custom pages** `domain/{slug}`: admin defines filters (categories, attributes, price range, brand, flash-sale, tags, sort) + optional manually added products (pinned first); renders product grid with filters. Route registered AFTER static routes; reserved-slug validation.
- **Static pages** (privacy-policy, terms, about…): translatable, slug under `/{lang}/page/{slug}`.
- **Gift Finder** (`App\Services\GiftFinder\*`, P2; P3: one ranked list of products AND bundles, filters, relaxation, share link `/{lang}/gift-finder/r/{token}` + `GET /api/v1/gift-finder/{token}`, bundle add-to-cart; link in the header top nav, mobile drawer and home CTA): storefront `/{lang}/gift-finder` + `/api/v1/gift-finder`, admin `/marketer/gift-finder` (moved from `/admin/gift-finder` in P4, 301; stats, history, per-product gift tags; perms `gift_finder.view|manage`). Behind setting `gift_finder.enabled` (pages 404 / JSON 403 `feature_disabled` when off). Ranking rules: `docs/CALCULATIONS.md`. The n8n Growth system (brief, approvals, experiments, content, interactions, seasonal, lab, small-batch, ambassadors, inventory/returns intelligence, trends, events outbox) was removed in P2 (MARKETER_PLAN.md).
- **Customer cancellation**: with `customer_cancel.enabled` the customer may cancel an unshipped order with a reason code (web order page + `POST /api/v1/orders/{token}/cancel`).
- **Banners**: placement (home hero slider, strip, category tile, popup), image per locale, link, schedule, sort.
- **Expenses**: category, amount, date, branch, note, attachment — feeds net profit.
- **Cities**, **Shipping companies** (price list per city: price, optional free-over threshold, ETA, COD flag).
- **Payment methods**: `wallet` (vodafone cash, instapay; customer uploads transfer proof; admin approves → invoice) and `online` gateways (paymob, fawrypay, paypal, paytabs, stripe) behind a `PaymentGatewayInterface` + factory; each method has fee (percent + fixed) and who bears it (customer/merchant) per CALCULATIONS.md; credentials in encrypted shop settings (`setting()`). Implement drivers as sandbox-ready skeletons with webhook endpoint + signature verification stubs.
- **Reviews**: rating+text on purchased products, moderation.
- **Notifications** (admins): DB + realtime-ish (polling every 15s endpoint; optional broadcasting) bell dropdown with toastr pop for new orders and status changes; mark read.

## 5. Reports (date-range filters, stat cards + datatable + CSV export; one currency, no conversion)
Purchases (total cost + cards), Sales (total | costs | discounts | shipping | net profit), Discounts, Shipping costs, Payment fees, Net profit (sales − COGS − discounts-as-applied − shipping cost − fees − expenses − refunds), Returns, Refunds, Expenses, Stock valuation & low stock, Top products / categories, Affiliate commissions & payouts, Flash-sale performance, Customers (new/repeat/LTV), Branch performance, Payment-method breakdown. Every formula defined in CALCULATIONS.md §Reports.
Purchases module: purchase orders from suppliers (suppliers table) that increase stock + record cost (needed for purchases report).

## 6. Languages
- `languages`: code, name, native name, direction, active, default.
- Dynamic data: `translations` via a `HasTranslations` trait (JSON columns per translatable field OR `*_translations` table — use JSON columns `name`, `description` keyed by locale). Admin forms render a tab per active language.
- Static UI: `lang/{code}/*.php` files; `ar` + `en` shipped. Language switcher keeps URL prefix.

## 7. Storefront (design = raneen.com/ar)
RTL Arabic default, top promo bar, header (logo, search with suggestions, account, cart, language), category mega menu, hero slider, category icon row, promo banners, flash-sale countdown rail, "Recommended for you / Most sold / Most reviewed" rails, per-category product rails, brand strip, footer (columns, app badges, payment icons). Pages: home, category (filters/sort/pagination via AJAX), product (gallery; with a video: one slider of images + videos where the active video autoplays muted and inline; variant picker, stock, reviews, related), cart, checkout, order success/tracking, account (orders, addresses, wishlist), custom/static pages, affiliate login/dashboard.

## 8. Quality
- Seeders: one shop (settings), Super Admin, sample categories/attributes/products(+variants), branches, cities, shippers, payment methods.
- Pest/PHPUnit feature tests for: single-store smoke (admin panel, storefront, APIs), OrderCalculator (every CALCULATIONS.md example), stock ledger, discount rules, affiliate commission tiers, list resolver.
- Policies/permissions per module. Rate limits on auth/checkout. CSRF everywhere. Escape output.
- Added by senior review: audit log, soft deletes on core entities, queue for notifications/emails, sitemap, SEO meta, image upload service with thumbnails, activity log, role/permission UI, cache keys, `php artisan` seed commands (`seed:live`, demo), health seeding.

## 9. Owner decisions (2026-10-21)
- ~~Central site / country picker~~: superseded by the 2026-11 single-store conversion (§2): `/` redirects to the default locale of the one shop; old country subdomains 301 to `APP_URL`.
- **Payments**: online = Paymob + FawryPay first (Egypt), others later. Offline = wallets (Vodafone Cash, E& Cash, InstaPay, …) requiring a proof-of-payment FILE upload + admin approval. COD always exists by default and collects the delivery fee upfront; the carrier collects the remaining amount. Each method independently active/inactive.
- **Profit/sales** count on order date, only for orders confirmed by an admin (customer-submitted `pending` orders never count). Returns reduce affiliate commission and tier sales.
- **Media/CDN**: all images and files live on a separate system/subdomain (`cdn.domain.com` or object storage behind a CDN), never served by the app servers. Payment proofs go to a PRIVATE disk (signed URLs), not the CDN.
See `docs/CALCULATIONS.md` (updated) and `docs/GAP_ANALYSIS.md`; build prompts in `docs/prompts/09..13`.
- **Storefront phones (docs/plans/phone-country-code.md)**: every storefront phone input is locked to the shop's dial code (`setting('country')` -> `config/phone.php`, e.g. EG +20, SA +966). Customers type the national number only; the server normalises it and stores the code in a separate `phone_country_code` column (customers, customer_addresses, orders snapshot, affiliates); a client-sent code is ignored. Display/tel/notifications use `phone_full` / `phone_display`. Admin forms (branches, suppliers) are out of scope.
