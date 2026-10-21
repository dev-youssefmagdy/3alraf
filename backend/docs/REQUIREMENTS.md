# Multi-Tenant Marketplace — Requirements & Architecture

Laravel 13 / PHP 8.3 / MySQL (db `3alraf`) in `/var/www/3alraf/backend`. Design reference: raneen.com/ar (RTL-first, mega-menu, sliders, category tiles, product rails, red/white palette).

## 1. Stack & conventions (MANDATORY)
- Blade + TailwindCSS (Vite) + jQuery/AJAX + toastr.js + Yajra DataTables (server-side) + Spatie-style permissions (spatie/laravel-permission).
- **Layers**: Controller (thin) → FormRequest → Service → Repository (interface + Eloquent impl, bound in a provider) → Model. Use **DTOs** (readonly classes in `app/DTOs`) between controller and service. (User wrote "TDO" = DTO.)
- **Enums** in `app/Enums` (backed, with `label()`): OrderStatus, PaymentStatus, PaymentType, DiscountType, StockMovementType, AffiliateTier basis, etc.
- **Blade components** for everything reusable (`resources/views/components`): form inputs, modal, datatable wrapper, card, stat-card, product-card, layout shells.
- **AJAX validation before submit**: every form posts first to a `/validate` endpoint (same FormRequest) via shared JS `ajax-form.js`; show field errors inline; only then submit. Toastr for all success/error feedback.
- Money: integer minor units? NO — use `decimal(14,2)`; all math through `App\Support\Money` / `OrderCalculator` per `docs/CALCULATIONS.md`.
- Strings via lang files (`lang/{locale}/*.php`) for static text; DB translations for dynamic data (see §6).

## 2. Tenancy model (single DB, `tenant_id` scoping)
- Central domain `domain.com` (config `APP_CENTRAL_DOMAIN`); tenants = subdomains `egy.domain.com`, `sa.domain.com` (`tenants` table: slug/subdomain, name, country, currency code+symbol+rate, default_locale, locales[], timezone, is_active, settings JSON).
- `BelongsToTenant` trait: global scope + auto-set `tenant_id` on create. Middleware `ResolveTenant` resolves by host; binds `current_tenant()`. Central requests have no tenant.
- Local dev: use `*.localhost` / hosts entries (`egy.3alraf.test`).
- **Central-only** (no tenant_id): attributes, attribute values, products, variants, barcodes base data, categories tree (global), languages, translations, tenants, super admins, global static content defaults.
- **Tenant-owned**: branches, stock, stock transfers, adjustments, banners, shipping companies + city price lists, payment methods (+fees), orders, customers, affiliates, flash sales, discount codes, recommended/most-sold/most-reviewed lists, custom pages, static pages, expenses, cities, reviews, tenant admins, notifications, tenant product listing (`tenant_products`: tenant enables a central product, sets price, sale price, cost, status; variants priced via `tenant_product_variants`).
- Central admin (`domain.com/admin`): CRUD tenants + config; manages central modules; **manager view**: dashboards/reports for all tenants with a tenant switcher / "all tenants" aggregation (currency-normalized via tenant rate to base currency).
- Tenant admin (`egy.domain.com/admin`): manages every tenant-owned module. Separate auth guards: `central` (admins) and `tenant` (admins scoped by tenant_id); customers guard `web`, tenant-scoped.
- Tenant storefront URLs: always prefixed `/{language}` (e.g. `egy.domain.com/ar/...`); `/` redirects to default locale. RTL for `ar`.

## 3. Catalog
- **Attributes** (central): name (translatable), type (select/color/text), values (translatable, color hex optional).
- **Products** (central): translatable name/description/meta, slug, brand, category (many), images, type `simple|variable`, base sku, base barcode (simple).
- **Variants** (variable products): combination of attribute values, own sku, **unique barcode (EAN-13 generated, Code128 render)**, images, weight.
- **Categories**: unlimited-depth tree (nested set or `parent_id`+`path`), translatable, image, icon, sort, active; breadcrumbs; mega menu.
- Tenant listing: price/sale price/cost per product or variant, active flag; stock per branch.

## 4. Commerce modules (tenant)
- **Branches**, **Stock** (per branch × product/variant), **Stock Transfer** (branch→branch, statuses draft/in-transit/received, movement ledger), **Stock Adjustment** (increase/decrease with reason). All changes write `stock_movements` ledger.
- **Orders**: cart→checkout (guest or customer), items snapshot (name, sku, barcode, unit price, cost), totals computed ONLY by `OrderCalculator` following `docs/CALCULATIONS.md`. Statuses enum with history log; fires notification events. Returns & refunds sub-flows. Invoice (printable/PDF), wallet payment proof upload.
- **Flash sales**: start/end datetime, products/variants with sale price or %, qty limit, countdown on storefront.
- **Discount codes**: percent/fixed, min order, max discount cap, usage limit total/per customer, date range, scope (all/products/categories), free-shipping option.
- **Customers**: addresses (city), order history, wishlist, reviews.
- **Affiliates** (tenant-scoped accounts): own login/dashboard; **tier commission** (tiers by monthly/lifetime sales or order count → percentage; defined per tenant); every product in dashboard shows a **barcode/QR image of its unique link**: `https://{tenant}.domain.com/{locale}/product/{slug}?utm_source=affiliate&aff={aff_uuid}`; click tracking (cookie 30d), conversion → commission record (pending→approved→paid after return window), payout requests, stats.
- **Recommended / Most sold / Most reviewed** lists: admin pins products manually (ordered, shown FIRST), remaining slots filled by algorithm (recommended: same-category/co-purchase/popularity; most sold: sum qty delivered orders; most reviewed: review count/avg), de-duplicated, limit configurable. One reusable `ProductListResolver`.
- **Custom pages** `domain/{slug}`: admin defines filters (categories, attributes, price range, brand, flash-sale, tags, sort) + optional manually added products (pinned first); renders product grid with filters. Route registered AFTER static routes; reserved-slug validation.
- **Static pages** (privacy-policy, terms, about…): translatable, tenant-scoped, slug under `/{lang}/page/{slug}`.
- **Banners**: placement (home hero slider, strip, category tile, popup), image per locale, link, schedule, sort.
- **Expenses**: category, amount, date, branch, note, attachment — feeds net profit.
- **Cities** (tenant), **Shipping companies** (price list per city: price, optional free-over threshold, ETA, COD flag).
- **Payment methods**: `wallet` (vodafone cash, instapay; customer uploads transfer proof; admin approves → invoice) and `online` gateways (paymob, fawrypay, paypal, paytabs, stripe) behind a `PaymentGatewayInterface` + factory; each method has fee (percent + fixed) and who bears it (customer/merchant) per CALCULATIONS.md; credentials in encrypted tenant settings. Implement drivers as sandbox-ready skeletons with webhook endpoint + signature verification stubs.
- **Reviews**: rating+text on purchased products, moderation.
- **Notifications** (tenant admins): DB + realtime-ish (polling every 15s endpoint; optional broadcasting) bell dropdown with toastr pop for new orders and status changes; mark read.

## 5. Reports (tenant, plus central aggregated, date-range filters, stat cards + datatable + CSV export)
Purchases (total cost + cards), Sales (total | costs | discounts | shipping | net profit), Discounts, Shipping costs, Payment fees, Net profit (sales − COGS − discounts-as-applied − shipping cost − fees − expenses − refunds), Returns, Refunds, Expenses, Stock valuation & low stock, Top products / categories, Affiliate commissions & payouts, Flash-sale performance, Customers (new/repeat/LTV), Branch performance, Payment-method breakdown. Every formula defined in CALCULATIONS.md §Reports.
Purchases module: purchase orders from suppliers (suppliers table) that increase stock + record cost (needed for purchases report).

## 6. Languages
- `languages` (central): code, name, native name, direction, active, default.
- Dynamic data: `translations` via a `HasTranslations` trait (JSON columns per translatable field OR `*_translations` table — use JSON columns `name`, `description` keyed by locale). Admin forms render a tab per active language.
- Static UI: `lang/{code}/*.php` files; `ar` + `en` shipped. Language switcher keeps URL prefix.

## 7. Storefront (design = raneen.com/ar)
RTL Arabic default, top promo bar, header (logo, search with suggestions, account, cart, language), category mega menu, hero slider, category icon row, promo banners, flash-sale countdown rail, "Recommended for you / Most sold / Most reviewed" rails, per-category product rails, brand strip, footer (columns, app badges, payment icons). Pages: home, category (filters/sort/pagination via AJAX), product (gallery, variant picker, stock, reviews, related), cart, checkout, order success/tracking, account (orders, addresses, wishlist), custom/static pages, affiliate login/dashboard.

## 8. Quality
- Seeders: central admin, 2 tenants (egy EGP ar/en, sa SAR ar/en), sample categories/attributes/products(+variants), branches, cities, shippers, payment methods.
- Pest/PHPUnit feature tests for: tenant isolation, OrderCalculator (every CALCULATIONS.md example), stock ledger, discount rules, affiliate commission tiers, list resolver.
- Policies/permissions per module. Rate limits on auth/checkout. CSRF everywhere. Escape output.
- Added by senior review: audit log, soft deletes on core entities, queue for notifications/emails, sitemap per tenant, SEO meta, image upload service with thumbnails, activity log, role/permission UI, tenant-aware cache keys, `php artisan` commands for tenant creation, health seeding.

## 9. Owner decisions (2026-10-21)
- **Central site**: public landing on the central domain; visitor CHOOSES a country (no geo auto-suggest); choice remembered in a cookie; links to `{slug}.domain.com`. Unknown/inactive tenant → Arabic "store unavailable" page linking back to central.
- **Payments**: online = Paymob + FawryPay first (Egypt), others later. Offline = wallets (Vodafone Cash, E& Cash, InstaPay, …) requiring a proof-of-payment FILE upload + admin approval. COD always exists by default and collects the delivery fee upfront; the carrier collects the remaining amount. Each method independently active/inactive per tenant.
- **Profit/sales** count on order date, only for orders confirmed by an admin (customer-submitted `pending` orders never count). Returns reduce affiliate commission and tier sales.
- **Media/CDN**: all images and files live on a separate system/subdomain (`cdn.domain.com` or object storage behind a CDN), never served by the app servers. Payment proofs go to a PRIVATE disk (signed URLs), not the CDN.
See `docs/CALCULATIONS.md` (updated) and `docs/GAP_ANALYSIS.md`; build prompts in `docs/prompts/09..13`.
