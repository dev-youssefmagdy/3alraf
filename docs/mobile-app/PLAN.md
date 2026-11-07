# 3alraf Customer Mobile App — Master Plan & Agent Prompts

> **Single store (2026-11-06, backend Phase 6 of `REMOVE_MULTI_TENANCY_PLAN.md`) — overrides every tenant/country statement below.**
> The backend now serves ONE shop on one host (`APP_URL/api/v1`); there is no tenant, no country picker and no `X-Tenant` resolution.
> - `GET /app-config` returns the switches **plus** `store` {name, country, currency{code,symbol}, default_locale, locales[{code,rtl}], timezone, base_url}; `GET /store` the full profile.
> - Compat for builds already in the stores: `X-Tenant` is ignored (any value), old `{slug}.` hosts still answer `/api/*`, `GET /tenants` is deprecated and returns a one-item list in the old `Tenant.fromJson` shape with `slug` = `egy` (config `mobile.legacy_store_slug`) so saved sessions/carts keep working and the picker is skipped.
> - **Phase 7 (Flutter) done (2026-11-07):** `features/tenant` removed (country selection/list/switcher, decision table, tenant list/repository/models); no `X-Tenant` interceptor, no central/tenant request split, one base URL. New `features/store` (`StoreInfo` = `app-config.store`, `StoreProfile` = `GET /store`, both cached; `StoreController`, `storeInfoProvider`, `storeProfileProvider`, `storeTextDirectionProvider`) and `features/startup` (startup controller, gate pages, first-run `LanguageSelectionPage` at `/language`, language sheet). Startup = splash -> legacy storage migration -> `app-config` -> language page only if never chosen (store locales/default) -> onboarding (first launch) -> Home. Storage keys are plain (`auth_token`, `affiliate_token`, `cart_token`, `locale`, `store_info`, `store_profile`, `recent_searches`); old `<key>:<slug>` values are moved once on first launch so sessions/carts/language survive. Contract: `backend/docs/MOBILE_API.md` §1; app details: `app/README.md`.

Audience: an AI coding agent (Sonnet 5.5) executing phase by phase. Each phase is self-contained: read **§0–§3** once, then do only your phase prompt. Update `docs/mobile-app/PROGRESS.md` (append a short section) at the end of every phase; later phases read that file instead of re-exploring.

---

## 0. Repo layout & ground rules

```
/var/www/3alraf
├─ backend/   Laravel (multi-tenant marketplace). Read backend/CLAUDE.md, docs/REQUIREMENTS.md, docs/PROGRESS.md.
│              Before touching orders/cart/discounts/shipping/fees/refunds: read backend/docs/CALCULATIONS.md.
├─ app/       Flutter app (currently the untouched `flutter create` stub; package name `app`, applicationId com.example.app).
├─ docs/mobile-app/   this plan + PROGRESS.md
```

Rules:
- Backend layering stays: Controller → FormRequest → DTO → Service → Repository → Model. API payloads are **always API Resources** (`app/Http/Resources/Api`), never models. Envelope: `{success, message, data, meta}` via `ApiController`. **Reuse the existing Services/FormRequests the web storefront uses; never duplicate business/price logic in the API or the app.** The app never computes totals — it displays what `cart/quote` and `checkout/validate` return.
- Every new endpoint gets a Pest test in `backend/tests/Feature/` (pattern: `MobileApiTest.php`). Run `php artisan test --filter=...` and the existing Mobile API tests; do not break them.
- Tenancy = one shared DB with `tenant_id`; tenant = store = country (`egy.3alraf.com`, `sa.3alraf.com`…). Never run `migrate:fresh` or touch the dev DB destructively. Use the test DB / `RefreshDatabase` as existing tests do.
- Flutter: Flutter 3.41 / Dart ^3.11. Strong typing, no `dynamic` leaking out of the data layer, `flutter analyze` must be clean, `dart format` applied. Do not commit; leave changes in the working tree.
- Arabic is the primary language: **full RTL**, Cairo font, and `ar`/`en` (plus any extra tenant locale) driven by the tenant's `locales`.

## 1. What already exists (verified in code)

Mobile API `/api/v1` (`backend/routes/api_v1.php`, loaded twice by `bootstrap/app.php`):
- Tenant resolution: subdomain host (`{slug}.domain/api/v1`) **or** `X-Tenant: <slug>` header on the central host (`domain/api/v1`). Middleware `api.context` also handles `Accept-Language` (limited to tenant locales), `X-Cart-Token` (guest cart, echoed back on every response), `X-Affiliate-Ref`, `X-Attribution`.
- **`GET {central}/api/v1/tenants`** (`routes/api.php`, `Api\V1\TenantController`, data from `Services\Tenancy\TenantDirectory`) → active tenants: `slug,name,country,currency,default_locale,locales,base_url` (the directory also has `flag`,`languages`, but the controller drops them). `GET /health` exists too.
- Public: `store`, `pages(/{slug})`, `custom-pages(/{slug})`, `home`, `menu`, `catalog` (+`facets`,`suggest`), `products/{slug}` (+`reviews`), `bundles(/{slug})`, `gift-finder`, `brands`.
- Cart: `cart` show / items add/update/remove / `quote` / `merge`. Checkout: `checkout/cities|shipping|packaging|payment-methods|validate` + `POST checkout`. Orders by public token: show/track/proof/pay/payment-return; auth: list/cancel/return.
- Auth (Sanctum, ability `customer`): register, login (**email+password**), google (id_token), logout, me, update me, forgot/reset password (6-digit code), email verification. Addresses CRUD, wishlist, review create.
- Affiliate token type: register/login/logout/me/dashboard/products/qr/payouts(+request).

Web storefront routes (`backend/routes/storefront.php`) = the **source of truth for screens**. Views in `backend/resources/views/storefront/*`, layout `components/layouts/storefront.blade.php`, CSS tokens in `resources/css/theme.css`.

## 2. Gap analysis: web vs API (to be closed in Phase 1)

| Web feature | API today | Action |
|---|---|---|
| Blog index/show (`blog`) | missing | add `GET blog`, `GET blog/{slug}` (BlogPost model) |
| Tag page (`tag/{slug}`) | missing | add `tag` filter to `catalog` (like `category`) + `GET tags/{slug}` meta |
| Brand page (`brand/{slug}`) | only `brands` list + `brands[]` filter | add `GET brands/{slug}` (meta) + `brand` filter on catalog |
| Category page meta (name/image/description/breadcrumb/children) | catalog accepts `category` slug but no meta | add `GET categories/{slug}` |
| Stock request "notify me" (`stock-requests`) | missing | add `POST stock-requests` (reuse `StockRequestController` logic) |
| Small-batch launch (`launch/{code}`) | missing | add `GET launch/{code}`, `POST launch/{code}` (feature flag `small_batch_lab`) |
| Ambassador portal (affiliate) | missing | add `affiliate/ambassador` GET/join/links (feature flag `ambassadors`) |
| Order invoice PDF, proof file download | missing | add `GET orders/{token}/invoice` (pdf stream) and `GET orders/{token}/proof` |
| Store info for UI (logo, favicon, brand colours, contact, social links, footer pages, payment icons, app-store links, whatsapp) | `StoreResource` has only name/currency/locales | extend `store` (see `StorefrontData::footer()`, `SeoSettingsService`, tenant settings). Colours: use tenant colours if the model has them, else the 3alraf defaults in §4 |
| Tenant directory: flag, logo, languages | controller drops `flag`,`languages` | return them; add `logo_url` |
| Account deletion (App Store/Play mandatory) | missing | add `DELETE auth/me` (password confirm; anonymise via `CustomerAccountService`, revoke tokens) |
| Change password in-app | maybe via `PUT auth/me` | verify, document |
| Phone login/OTP for customers | web customers use email+password; WhatsApp OTP is **admin-only** | **out of scope**; keep email+password + Google |
| Push notifications | missing | add `devices` table + `POST/DELETE devices` (token, platform, locale) storing FCM tokens, tenant+customer scoped. Sending is a follow-up; just store + a `PushDispatcher` interface with a no-op/log implementation |
| App config / force-update | missing | add `GET {central}/api/v1/app-config` → `{min_version, latest_version, store_urls, maintenance, maintenance_message}` from `config/mobile.php` + env |
| Cities for address/checkout | `checkout/cities` | ok |
| Notifications inbox | missing | out of scope (note in PROGRESS) |

Also: write **`backend/docs/MOBILE_API.md`** — complete endpoint reference (method, path, auth, params, example response, error codes, headers). It is the contract Flutter phases rely on. Also generate an **OpenAPI 3.1** file `backend/docs/openapi-mobile.yaml` (hand-written or via a package already in vendor; do not add heavy dependencies).

## 3. Mobile product spec

### 3.1 Tenant ("country") flow — the core requirement
1. **Splash** (logo animation, ≤1.2 s min) while the app: loads saved tenant from secure storage, fetches `GET /tenants` and `GET /app-config` (central host; base URL via `--dart-define=API_BASE`, default `https://3alraf.com`).
2. Decision (pure function, unit-tested):
   - saved tenant exists **and** still active → go Home (refresh store info in background).
   - saved tenant no longer active → clear it, clear cart token, go to selection (message why).
   - no saved tenant, **exactly 1 active tenant → auto-select it** and go Home (no selection screen).
   - no saved tenant, >1 tenants → **Country Selection screen** (flag, country name, store name, currency, languages; search if >8; pick → confirm → Home).
   - `/tenants` unreachable and a saved tenant exists → open offline-tolerant Home with cached data + retry banner; unreachable and none saved → full-screen error with retry.
3. **Switch country** lives in the drawer/side menu (and in Account): opens the same selection UI as a bottom sheet/page listing available tenants with the current one marked. Switching: confirm dialog (warn that the cart/wishlist belong to the store) → log out of the old tenant (revoke token), clear cart token + caches, store new tenant, apply its default locale/currency/RTL, reset navigation to Home. Tokens, cart token, cached data and locale are **namespaced per tenant slug**.
4. API calls use header `X-Tenant: <slug>` against the central base URL (single base URL; do not build per-tenant hosts). Always send `Accept-Language`, `X-Cart-Token` when present (persist the one echoed back in responses), `Authorization: Bearer`.
5. Locale: default = tenant `default_locale`; user can change among tenant `locales` in the drawer; RTL follows locale.

### 3.2 Screens (mirror web, native UX)
Bottom navigation (5): **Home · Categories · Cart (badge) · Wishlist · Account**; drawer/side menu: switch country, language, pages (static/custom), blog, brands, bundles, gift finder, affiliate program, contact/WhatsApp, about, terms, privacy, rate app.

Screens (each with loading skeleton, empty state, error+retry, pull-to-refresh where lists):
- Splash, Country selection, Force-update/maintenance, Onboarding (3 slides, first launch only, skippable).
- Home: hero carousel, category rail (round icons), promo strips/tiles, flash-sale rail with countdown, gift-finder CTA, bundles rail, rails (recommended / most sold / most reviewed), brands rail, popup banner (once per session).
- Categories (menu tree) → Category/Brand/Tag/Custom-page product listing: grid, sort sheet, filter sheet (facets: brands, attribute values, price range), infinite pagination.
- Search: live suggest, recent searches, results listing.
- Product: image gallery (pinch zoom), price/old price/discount badge/flash countdown, variant picker, stock state + "notify me", quantity, add to cart, share, wishlist heart, reviews summary + list + write review (if `can_review`), related rail, sticky add-to-cart bar.
- Bundles list/detail, Gift Finder wizard + results + add-to-cart.
- Cart: lines, qty steppers, remove, coupon/discount code field, totals from `cart/quote`, free-shipping hints if provided, guest allowed; checkout CTA.
- Auth: login, register, forgot/reset password (6-digit code), email verification, Google sign-in; guest → login → back to checkout with cart merged (`cart/merge`).
- Checkout (stepper): address (list/add/edit, city picker) → shipping options → packaging → payment method → review (validated via `checkout/validate`) → place order. Payment: COD, wallet (proof upload), online gateways via in-app WebView with `payments/return/{token}` handling.
- Order confirmation, Orders list, Order details + timeline/track, upload proof, pay now, cancel, request return, invoice PDF open/share.
- Account: profile edit, change password, addresses, wishlist, orders, language, switch country, delete account, logout, about/version.
- Affiliate area (separate auth): register/login, dashboard (stats), products + share link + QR, payouts list + request payout, ambassador portal (if enabled).
- Static pages / custom pages / blog list + detail (HTML content rendered safely), contact.

### 3.3 Architecture (Flutter)
- **Feature-first clean architecture**: `lib/core/{config,network,storage,theme,l10n,routing,widgets,utils,error}`, `lib/features/<feature>/{data,domain,presentation}`.
- State: **Riverpod 2 (flutter_riverpod + riverpod_annotation/generator)**. Routing: **go_router** with auth/tenant redirect guards and a `ShellRoute` for bottom nav. HTTP: **dio** (interceptors: tenant, locale, cart token, auth, envelope unwrapping → typed `ApiException`, retry on idempotent GET, 401 → logout). Models: **freezed + json_serializable**. Storage: `flutter_secure_storage` (tokens), `shared_preferences` (tenant, locale, onboarding), optional `hive`/in-memory cache for home. Images: `cached_network_image`. Others: `flutter_html`/`flutter_widget_from_html_core`, `webview_flutter`, `url_launcher`, `share_plus`, `image_picker`, `google_sign_in`, `shimmer`, `carousel_slider`/`smooth_page_indicator`, `qr_flutter`, `intl`, `flutter_launcher_icons`, `flutter_native_splash`, `package_info_plus`, `connectivity_plus`. Pin versions that resolve on Dart ^3.11; if a package is incompatible, pick the closest maintained alternative and note it in PROGRESS.
- Config via `--dart-define` (`API_BASE`, `GOOGLE_SERVER_CLIENT_ID`, `ENV`). Flavors optional (dev/prod) — keep simple: `lib/main.dart` + `AppConfig`.
- i18n: ARB files `ar` (template) / `en`, `flutter gen-l10n`; no hard-coded strings. Server-provided content already comes localized.
- Money: server returns formatted/decimal strings — never use `double` for money; display with the store currency symbol/position from `store`.
- Tests: unit (tenant decision, repositories w/ mocked dio, interceptors), widget (country selection, product card, cart), golden optional. `flutter analyze` + `flutter test` green at every phase end.

## 4. Design system ("same identity as the website, app-grade UX")
Source: `backend/resources/css/theme.css` + logo `backend/public/logo.png` (rose/plum/gold; Arabic wordmark "عالرف" with shopping-cart/shelf and cosmetics).
- Brand: primary `#A8456C` (brand-600, AA with white), hover/pressed `#8F3A5C`, soft rose `#C96F91`, tint `#FBEAF0`, accent gold `#D7B987` / `#F4E8D5`, text plum `#45313B` (slate-900), headings `#2F2129`, muted `#7A6672`, border `#F0DDE4`, surface `#FFF9F7` (warm white), card `#FFFFFF`, success `#2F806A`, warning `#D99A36`, danger `#C8384F`.
- Material 3 `ColorScheme` built from these (not `fromSeed` defaults); light theme first, dark theme optional (plum-tinted) behind a setting. Font **Cairo** (bundle in assets for offline; Arabic + Latin). Radius 12/16/24, soft shadows, 8-pt spacing, 48 dp min touch targets, haptics on add-to-cart, hero/shared-element image transitions, shimmer skeletons, bottom-sheet pickers, sticky CTAs, snackbars (not dialogs) for confirmations, animated cart badge. Accessibility: semantics labels, text scale 1.3, contrast AA.
- Shared widgets: `AppButton`, `AppTextField`, `ProductCard`, `PriceText`, `RatingStars`, `SectionHeader`, `HorizontalRail`, `StatusChip`, `EmptyState`, `ErrorState`, `AppShimmer`, `QuantityStepper`, `CountdownChip`, `NetworkImageX`.
- App icon & splash: derive from `backend/public/logo.png` (1376×1143, transparent). Generate a **1024×1024 square master** by centering the logo with ~12 % padding on `#FFF9F7` (or transparent for the adaptive-icon foreground, safe-zone 66 %), using ImageMagick (`magick`/`convert`) or Python Pillow; store in `app/assets/branding/` (`icon.png`, `icon_foreground.png`, `splash_logo.png`). Configure `flutter_launcher_icons` (Android adaptive with `#FFF9F7` background, iOS, web, Windows, macOS) and `flutter_native_splash` (bg `#FFF9F7`, logo centered, Android 12 config). Run both generators. Also set app display name **"عالرف / 3alraf"** (Android `android:label`, iOS `CFBundleDisplayName`), and replace `com.example.app` with applicationId/bundle id **`com.alraf.shop`** (Android namespace + Kotlin/Java package dir + iOS PRODUCT_BUNDLE_IDENTIFIER). Also fix the empty `backend/public/favicon.ico` (0 bytes) from the same logo.

## 5. Definition of Done (whole project)
- All §2 gaps closed, tested, documented in `backend/docs/MOBILE_API.md`; existing tests still pass.
- App runs (`flutter run`, `flutter build apk --debug` succeeds) against a local backend; all §3.2 screens implemented, RTL/Arabic correct, tenant selection + auto-select + switching work per §3.1.
- `flutter analyze` clean, `flutter test` green, README in `app/` with setup/run/build instructions and `--dart-define`s.
- App icon/splash/name/package id updated.
- `docs/mobile-app/PROGRESS.md` records what each phase did, deviations, and open items.

---

## PHASES — agent prompts (execute in order; one agent per phase)

### Phase 1 — Backend API gap closure
> You are a senior Laravel engineer. Read `/var/www/3alraf/docs/mobile-app/PLAN.md` §0–§2, `backend/CLAUDE.md`, `backend/docs/REQUIREMENTS.md`, `backend/docs/CALCULATIONS.md` (only if you touch money), and the existing mobile API (`routes/api_v1.php`, `app/Http/Controllers/Api/V1/*`, `app/Http/Resources/Api/*`, `tests/Feature/MobileApiTest.php`). Close **every** gap in the §2 table, reusing the web Services/FormRequests, adding Resources/FormRequests/routes (named, throttled, with correct auth middleware and feature flags `growth.feature:*`), Pest tests for each endpoint (success, validation, tenant isolation, auth), config `config/mobile.php`, migration for `devices`, and `TenantController` returning `flag,languages,logo_url`. Extend `store` with logo/colours/contact/social/footer pages/app links. Then write `backend/docs/MOBILE_API.md` (full contract incl. headers, pagination meta, error envelope, rate limits, every endpoint with example JSON) and `backend/docs/openapi-mobile.yaml`. Run the full mobile tests and any suites you may have impacted. Append your section to `docs/mobile-app/PROGRESS.md`. Do not modify web behaviour.

### Phase 2 — Flutter foundation, branding, icon
> You are a senior Flutter engineer. Read PLAN.md §0, §3.3, §4. In `/var/www/3alraf/app`: set up dependencies (`flutter pub add`/dev), folder architecture, `AppConfig`, theme (§4, Cairo font bundled — download Cairo from Google Fonts into `assets/fonts` or, if offline, use `google_fonts` with a note), ARB l10n (ar/en) with gen-l10n, go_router skeleton, Riverpod bootstrap, dio client with all interceptors and `ApiException`, secure storage wrappers, shared widget kit (§4), `main.dart` with RTL-aware `MaterialApp.router`. Then do the **icon/splash/name/package-id work** in §4 end to end (generate assets from `backend/public/logo.png`, run `dart run flutter_launcher_icons` and `dart run flutter_native_splash:create`, rename package/bundle id and labels on all platforms, verify `flutter build apk --debug` compiles). Also fix `backend/public/favicon.ico`. Unit-test the interceptors. `flutter analyze` clean. Append to PROGRESS.md.

### Phase 3 — Tenant flow (splash, selection, switching), auth, app-config
> Senior Flutter engineer. Read PLAN.md §3.1 and `backend/docs/MOBILE_API.md`. Implement: splash + startup orchestration, tenant models/repository/`TenantController` (Riverpod notifier) with the exact decision table in §3.1 as a pure, unit-tested function, Country Selection screen/bottom sheet (reused by the drawer "Switch country"), per-tenant namespaced storage, switch procedure (revoke token, clear cart token/caches, apply locale/RTL), force-update/maintenance screen from `app-config`, onboarding, `StoreInfo` provider (logo, colours, currency). Then auth: login, register, forgot/reset (code), verify email, Google sign-in, session persistence, guest→login→return, `cart/merge`. App shell with bottom nav + drawer. Widget tests: selection screen, single-tenant auto-select, switch confirm. Append to PROGRESS.md.

### Phase 4 — Catalog: home, categories, listing, search, product
> Senior Flutter engineer. Read PLAN.md §3.2 (Home → Product), §4 and `MOBILE_API.md`. Build Home (all sections), category tree, listing screens (category/brand/tag/custom page/search/bundles) with filter+sort sheets and infinite pagination, search with suggest + recents, product detail (gallery, variants, flash countdown, reviews, write review, notify-me, related, share, wishlist), bundles, gift finder, wishlist screen. Skeleton loaders, empty/error states, image caching, hero transitions. Mirror the website's `home.blade.php`, `product.blade.php`, `catalog.blade.php` structure but with native UX. Tests for repositories and key widgets. Append to PROGRESS.md.

### Phase 5 — Cart, checkout, orders, payments
> Senior Flutter engineer. Read PLAN.md §3.2 (Cart → Orders) and `backend/docs/CALCULATIONS.md` (display only — **never compute money client-side**). Build cart (quote-driven totals, discount code, steppers, guest cart token persistence), the checkout stepper (addresses CRUD + city picker, shipping, packaging, payment methods, validate, place), payment handling (COD, wallet proof upload with image_picker, online gateway WebView + return handling, pay-now), order confirmation, orders list/detail/tracking, cancel/return flows, invoice PDF open/share, address management in Account. Handle every API error envelope and 422 field errors inline. Tests: cart controller, checkout validation mapping. Append to PROGRESS.md.

### Phase 6 — Account, affiliate, content, polish
> Senior Flutter engineer. Read PLAN.md §3.2 (Account, Affiliate, content) and §4. Build profile/change password/delete account/language/switch country entry/about+version, push-token registration (`devices` API; use `firebase_messaging` only if Firebase config files exist, otherwise leave the interface with a no-op implementation and document the steps), affiliate area (auth, dashboard, products, QR, payouts, ambassador), static/custom pages + blog (safe HTML), contact/WhatsApp, rate app, offline banner, deep links (`https://{slug}.3alraf.com/...` → product/category/order; Android intent filters + iOS associated domains scaffolding), dark theme setting, accessibility pass, final performance pass (const, image sizes, list keys). Append to PROGRESS.md.

### Phase 7 — QA, hardening, documentation
> Senior QA/full-stack engineer. Verify the whole project against PLAN.md §5. Run backend tests (mobile + anything touched) and fix regressions; run `flutter analyze`, `flutter test`, `flutter build apk --debug`. Smoke-test the app against a local backend if it can be started (otherwise review contract conformance by diffing every Dart repository path/model against `MOBILE_API.md`/`openapi-mobile.yaml`). Review for: leaked tokens in logs, tenant isolation (no cross-tenant caches), RTL breakages, hard-coded strings, unhandled errors, `dynamic` leaks, accessibility. Fix what you find. Write `app/README.md` (setup, `--dart-define`s, build/release, Firebase/Google sign-in setup, deep links) and finalize PROGRESS.md with open items.
