# Mobile app - progress log

## Phase 1 - Backend API gap closure (done)

Contract for later phases: `backend/docs/MOBILE_API.md` (narrative + every endpoint with example JSON) and `backend/docs/openapi-mobile.yaml` (OpenAPI 3.1). Both were generated from `php artisan api:docs` output plus curated overlays and real captured responses; `backend/docs/api/openapi.json` was regenerated too (72 paths).

### Endpoints added (all under `/api/v1`, tenant via `X-Tenant`)
- Content: `GET blog`, `GET blog/{slug}` (published posts only, `content` HTML on detail); `POST stock-requests` (reuses `StockRequestRequest`, same de-dup as web).
- Catalog meta: `GET categories/{slug}`, `GET brands/{slug}`, `GET tags/{slug}`; new `brand=<slug>` and `tag=<slug>` filters on `GET catalog` and `GET catalog/facets` (combinable with `category`, `q`, ...).
- Growth: `GET|POST launch/{code}` (flag `small_batch_lab`, reuses `SmallBatchService` + `SmallBatchSignupRequest`); `GET affiliate/ambassador`, `POST affiliate/ambassador/join`, `POST affiliate/ambassador/links` (affiliate token, flag `ambassadors`, reuses `AmbassadorService`; report limited to what the web portal shows).
- Orders: `GET orders/{token}/invoice` (PDF via existing `InvoicePdf`), `GET orders/{token}/proof` (private-disk stream via `OrderPaymentService::proofResponse`).
- Account: `DELETE auth/me` (password, or `confirm:true` for Google-only accounts; `CustomerAccountService::deleteAccount` anonymises, removes addresses/wishlist/devices, revokes tokens and sessions, soft-deletes so the e-mail is reusable). Change password already works through `PUT auth/me` (needs `name`, `email`, `password`, `password_confirmation`, `current_password`; other tokens are revoked) - documented, no new endpoint.
- Push: `POST devices`, `DELETE devices` (token, platform, locale, app_version; optional auth binds the customer; unique per tenant+token hash).
- Central host: `GET app-config` (`min_version, latest_version, store_urls, maintenance, maintenance_message`, plus `update_required/update_available` when `?version=` is sent).

### Extended
- `GET store`: `logo_url, favicon_url, colors` (tenant `settings.branding.colors` over the 3alraf defaults), `contact{email,phone,whatsapp}`, `social`, `app_links`, `footer{pages,has_blog,payment_methods}`, `features{gift_finder,small_batch_lab,ambassadors}`.
- `GET tenants`: now returns `flag, languages, logo_url` and the standard envelope; `TenantDirectory` gained `logo_url`.

### Files
- New: `config/mobile.php`; migration `2026_12_03_100001_create_devices_table.php`; `app/Models/Device.php`; `app/Services/Push/{PushDispatcher,LogPushDispatcher,DeviceService}.php` (interface bound to the log-only no-op in `AppServiceProvider`); controllers `Api/V1/{Blog,StockRequest,Launch,Ambassador,Device,AppConfig}Controller`; requests `Api/{DeviceRequest,DeleteAccountRequest}`; resources `Api/{BlogPost,CategoryDetail,BrandDetail,Tag,Launch}Resource`; `tests/Feature/MobileApiGapsTest.php` (11 tests, 287 assertions); `docs/MOBILE_API.md`; `docs/openapi-mobile.yaml`.
- Modified: `routes/api_v1.php`, `routes/api.php`, `StoreController`, `OrderController`, `AuthController`, `TenantController`, `StoreResource`, `TenantDirectory`, `CustomerAccountService`, `AppServiceProvider` (new `devices` limiter), `ApiExceptionRenderer`, `lang/{ar,en}/store.php`, `tests/Feature/HardeningTest.php` (tenant key list), `docs/api/openapi.json`.

### Tests
`MobileApiGapsTest` 11/11 (287 assertions); `MobileApiTest`, `GoogleAuthTest`, `SecurityHardeningTest`, `RetentionTest` green. Full suite: 651 tests, 600 passed, 4 failed + 47 errors, none in API code: `BlogTest` (web meta description contains `bad()`), `HardeningTest` (Arabic unavailable page), `QrCodeTest` (logo PNG), `StorefrontTest` (sitemap Content-Type charset), `CitySeederTest`, `GrowthOffersTest` (barcode column too long), and 45 errors in `MediaTest` / `WhatsappChat*Test` caused by directory permissions on `storage/framework/testing/disks/*` (owned by another OS user). Not verified against a clean baseline; none of those files were touched.

### Deviations / decisions
- Tenant branding colours, logo and contact have no admin UI/model fields: `colors` come from `config/mobile.php` defaults unless `settings.branding.colors` exists; logo = `seo.org_logo` else `public/logo.png`; WhatsApp = `settings.contact.whatsapp` else `seo.org_phone` (digits). Flagged as a follow-up if per-store branding is wanted.
- `tenants` response gained the envelope keys (`success/message/meta`) while keeping `data` - superset of the old shape; `HardeningTest` key assertion updated to the new key list.
- Found and fixed a pre-existing defect: the `store.api.*` translation group did not exist, so API messages (logout, reset sent, verified, 401/403/404/500, e-mail code subjects) returned raw keys. Added ar/en strings. Also 404 messages no longer leak `No query results for model [...]`.
- Ambassador `report` is filtered to non-sensitive keys (no margin/commission cost).
- OpenAPI file is OpenAPI 3.1 YAML built from the route-derived generator output; field types are inferred from FormRequest rules (coarse), examples are real captured responses.

### Open items / notes for later phases
- Failures outside API code listed under Tests (web storefront / environment). `storage/framework/testing/disks/*` needs to be writable by the test user for the Media and WhatsApp suites.
- `backend/public/favicon.ico` is still 0 bytes (Phase 2 task).
- Notifications inbox, FCM sending, customer phone/OTP login: out of scope, not built.
- Account deletion keeps order rows (customer snapshot fields on orders) for accounting.
- Examples in the docs use the test host `egy.3alraf.test`; real hosts differ.
- A git stash entry (`stash@{0}`, "WIP on main: 5e20f72 Fix") was created by an aborted `git stash` during this phase and could not be dropped; its content is identical to the working tree and can be deleted with `git stash drop`.

## Phase 2 - Flutter foundation, branding, icon (done)

Working dir `/var/www/3alraf/app`. Nothing committed.

### Built
- Structure: `lib/core/{config,network,storage,theme,l10n,routing,widgets,utils,error}`, `lib/features/{splash,shell}/presentation`, `lib/app.dart`, `lib/main.dart`.
- `AppConfig` (`--dart-define` `API_BASE` default `https://3alraf.com`, `GOOGLE_SERVER_CLIENT_ID`, `ENV`); `apiV1` getter.
- Networking (`core/network`): single `Dio` from `buildDio()` with base `API_BASE/api/v1`; interceptors in order Tenant (`Accept`, `X-Tenant`), Locale (`Accept-Language`), CartToken (sends + persists echoed `X-Cart-Token` per tenant), Auth (Bearer from per-tenant storage; 401 on a bearer request clears the token and fires `onUnauthorized`), Retry (GET only, 502/503/504 + connection/timeouts, 2 retries, backoff), Envelope (unwraps `data`; `message`/`meta` via `response.envelopeMessage` / `response.envelopeMeta`; failures become `DioException.error = ApiException`). Non-enveloped bodies (PDF, `/health`) pass through.
  - Conventions for Phase 3+: central calls (`/tenants`, `/app-config`) use `Options(extra: {kCentralExtra: true})` (no tenant/cart/auth headers). Use `kSkipUnauthorizedExtra` for calls where a 401 must not log out. An explicit `Authorization` header (affiliate token) overrides the customer token. Repositories catch `DioException` and call `ApiException.from(e)` (kinds: network, timeout, unauthorized, forbidden, featureDisabled, notFound, validation w/ `errors` + `firstErrorFor`, rateLimited w/ `retryAfter`, server, cancelled, unknown). `errorMessage(l10n, e)` gives localised text.
  - `ApiContext` (tenantSlug, locale) is mutable and shared by interceptors; Phase 3 must set `tenantSlug` on select/switch. `LocaleNotifier.setLocale` keeps `ApiContext.locale` in sync. `unauthorizedEventsProvider` increments on a 401 logout.
- Storage: `KeyValueStore` (secure impl + in-memory for tests), `SessionStorage` (auth / affiliate / cart tokens, all keyed `kind:<tenant slug>`, `clearTenant`), `AppPreferences` (tenant slug, per-tenant locale, onboarding flag, theme mode) on shared_preferences.
- Theme: hand-built Material 3 light + plum dark `ColorScheme` from PLAN §4 tokens, `AppExtraColors` ThemeExtension (success/warning/gold/tint), radii/spacing constants, Cairo font, input/button/nav/sheet themes. Dark is selectable via `themeModeProvider` (default light).
- L10n: ARB `ar` (template) + `en` in `lib/core/l10n`, `l10n.yaml`, `flutter gen-l10n` output is checked in under `lib/core/l10n` (non-synthetic). Extend with new keys per phase and re-run `flutter gen-l10n`.
- Router: go_router with `redirectGuardProvider` hook (returns null for now - Phase 3 fills tenant/auth logic), splash route and `StatefulShellRoute.indexedStack` bottom nav (Home, Categories, Cart, Wishlist, Account) with placeholder pages. Splash currently just waits 1.2 s then goes Home.
- Widget kit (`core/widgets/widgets.dart`): AppButton, AppTextField, ProductCard, PriceText (renders server strings verbatim), RatingStars, SectionHeader, HorizontalRail, StatusChip, EmptyState, ErrorState, AppShimmer, QuantityStepper, CountdownChip, NetworkImageX.
- `main.dart`: loads SharedPreferences, `ProviderScope` override, `MaterialApp.router`; RTL comes from the `ar` locale.

### Branding / platform
- `assets/branding/{icon.png (1024, logo on #FFF9F7, ~12% pad), icon_foreground.png (transparent, inside 66% safe zone), splash_logo.png}` generated with ImageMagick from `backend/public/logo.png`.
- `flutter_launcher_icons` (Android adaptive bg `#FFF9F7`, iOS, web, Windows, macOS) and `flutter_native_splash` (bg `#FFF9F7`, logo, Android 12) configured in `pubspec.yaml` and both run.
- Application id / bundle id / Linux APPLICATION_ID: `com.example.app` -> `com.alraf.shop` (Android namespace + applicationId, Kotlin package dir moved to `com/alraf/shop`, iOS + macOS pbxproj incl. RunnerTests, macOS xcconfig). Display name "عالرف / 3alraf": Android `android:label`, iOS `CFBundleDisplayName` (`CFBundleName` = 3alraf), web manifest/title, Windows window title. Verified in the built APK with aapt2: package `com.alraf.shop`, label `عالرف / 3alraf`.
- `backend/public/favicon.ico` regenerated from the logo (multi-size 64/48/32/16 ICO, 32 KB; was 0 bytes).
- Cairo: single variable font `assets/fonts/Cairo.ttf` (+ `OFL.txt`) downloaded from google/fonts and bundled; no `google_fonts` package.

### Packages (resolved on Dart 3.11 / Flutter 3.41.2)
flutter_riverpod 3.3.2, go_router 17.5, dio 5.11, flutter_secure_storage 11.2, shared_preferences 2.5, cached_network_image 3.4, shimmer 3, smooth_page_indicator 3, carousel_slider 5.1, url_launcher 6.3, share_plus 13.3, package_info_plus 10.2, connectivity_plus 7.3, intl 0.20, freezed_annotation 3.1 + json_annotation 4.12; dev: build_runner, freezed 3.2, json_serializable 6.14, flutter_launcher_icons 0.14, flutter_native_splash 2.4, mocktail. Added later when needed (not yet): webview_flutter, image_picker, google_sign_in, qr_flutter, flutter_html / flutter_widget_from_html_core.

### Deviations
- **No `riverpod_annotation` / `riverpod_generator`**: no generator release resolves with the Flutter 3.41 SDK's pinned `meta` (the latest need `meta ^1.18`/Dart 3.12). Use plain Riverpod 3 `Notifier` / `Provider` (hand-written). freezed + json_serializable work and are available for models.
- go_router uses `StatefulShellRoute.indexedStack` instead of plain `ShellRoute` (preserves each tab's stack).
- Packages for later phases (webview, image_picker, google_sign_in, qr, html) were left out until needed, to keep the dependency graph small.
- Variable Cairo font is declared as one asset; Flutter selects weights from the `wght` axis on Android/iOS. Verify bold rendering visually once on a device; if weights look flat, add static Cairo weights.

### Results
- `flutter analyze`: no issues. `dart format`: clean. `flutter test`: 25/25 pass (interceptors: headers, central, tenant isolation, cart-token persistence, envelope unwrap/pass-through, 422/429/feature_disabled mapping, 401 logout / guest / skip, retry on 503 / give-up / POST never / 4xx never; widgets: RTL/LTR, PriceText, ProductCard, QuantityStepper, ErrorState, CountdownChip, EmptyState; app boot -> shell nav).
- `flutter build apk --debug` succeeded (first run 7m51s incl. Gradle downloads): `app/build/app/outputs/flutter-apk/app-debug.apk`. Android SDK at `~/Android/Sdk` (set with `flutter config --android-sdk`); JDK 21.
- Not run: iOS/macOS/Windows builds (no toolchain here), on-device visual check of icon/splash/fonts.

### Open items / notes for later phases
- Phase 3 must: set `ApiContext.tenantSlug`, replace `redirectGuardProvider`, real splash orchestration, and wire `unauthorizedEventsProvider` to the login route.
- `ProductCard.onAddToCart` is accepted but not rendered yet (Phase 4 decides the add-to-cart affordance).
- Google sign-in / Firebase config files are not in the repo; Android release signing still uses the debug key (Phase 7 README).
- `android/app/src/main/res` and `ios/.../Assets.xcassets` contain generated icon/splash files; regenerate with `dart run flutter_launcher_icons` and `dart run flutter_native_splash:create` if branding assets change.
- The `stash@{0}` mentioned in Phase 1 was not touched.

## Phase 3 - Tenant flow, auth, app-config (done)

Working dir `/var/www/3alraf/app`. Nothing committed; stashes untouched.

### Built
- **Tenant feature** (`lib/features/tenant`): models (`Tenant`, `StoreInfo`, `AppConfigInfo`; hand-written typed `fromJson`), `TenantRepository` (`/tenants`, `/app-config?version=`, `/store`, best-effort `/auth/logout`), pure `decideTenant()` (`domain/tenant_decision.dart`, sealed result: UseSaved / AutoSelect / ShowSelection(reason) / OfflineWithSaved / UnreachableNoTenant), `TenantController` (activate / select / switchTo / clear / refreshStore / chooseLocale), `StartupController` (splash orchestration + `recheck()` for the offline banner).
- **Splash** (`SplashPage`): min 1.2 s (`splashMinDurationProvider`), runs startup, routes: maintenance -> update-required -> onboarding (first launch) -> Home or country selection. Unreachable + nothing saved = full-screen error with retry.
- **Country selection** page + shared `CountryList` (flag, store name, currency, languages; search shown when > 8; current marked) + `showCountrySwitcher(ref)` bottom sheet used from the drawer and Account. Switch procedure: confirm dialog -> `POST auth/logout` for the old tenant -> clear its auth/affiliate/cart tokens + cached store -> bind new tenant (`ApiContext.tenantSlug`, locale from per-tenant pref else tenant default, RTL follows) -> `/store` refresh -> `go(/home)` + snackbar.
- Gate pages: force-update (store URL by platform), maintenance (server message, retry), onboarding (3 slides, skippable, pref `onboarding_done`). Offline banner (MaterialBanner with retry) in the shell.
- **Store info**: `storeInfoProvider` / `tenantControllerProvider.store` (logo, colors, currency, locales, contact/whatsapp, features), cached per tenant in SharedPreferences (`store_cache:<slug>`) for offline start.
- **Auth** (`lib/features/auth`): `AuthRepository`, `AuthController` (restore from secure storage at tenant activation, login/register/google/logout, `cart/merge` after sign-in then drops the guest cart token, `sessionExpired`), pages: login, register, forgot, reset (6-digit code), verify e-mail (skippable), Google button (shown only when `GOOGLE_SERVER_CLIENT_ID` is set; `google_sign_in ^7.2` behind `GoogleIdTokenProvider`). Guest -> login -> back via `?from=` (sanitised in-app path).
- **Shell**: drawer (switch country, language when >1 tenant locale, WhatsApp, version), `ShellMenuButton`, Account tab (guest CTA / profile summary / verify chip / switch country / language / logout), Wishlist tab shows a sign-in prompt for guests.
- **Router**: `redirectGuardProvider` removed; `computeRedirect()` (pure, tested) + `refreshListenable` on tenant/session/startup. Rules: splash always; not-ready -> splash; no tenant -> `/select-country`; protected prefixes (`AppRoutes.protectedPrefixes`: /orders, /addresses, /checkout, /verify-email) need a session -> `/login?from=`. `unauthorizedEventsProvider` is listened to in `routerProvider`: clears the in-memory session and goes to `/login?from=<current>&expired=1` (skipped on pre-tenant/auth screens).
- l10n: ~90 new ar/en keys; `flutter gen-l10n` re-run.

### Deviations
- Models are plain Dart classes (no freezed/json_serializable codegen) - small and typed; freezed remains available.
- Tenant colours are exposed in `StoreInfo.colors` but the Material theme is still the fixed 3alraf palette (per-tenant theming not applied).
- Drawer only has switch country / language / WhatsApp / version; pages, blog, brands, bundles, gift finder, affiliate, rate app entries come with their phases.
- Switching tenant does `go(/home)`; tab navigators are not force-reset (tabs are placeholders). Phase 4+ providers must `ref.watch(tenantSlugProvider)` (or be invalidated by it) so no cross-tenant data survives.
- Removed the "signed-in user on /login redirects away" guard rule: it raced with register -> verify navigation; the pages navigate themselves.
- Google sign-in untested on device (no Firebase/Google client IDs in repo).

### Results
- `flutter analyze`: no issues. `flutter test`: 64/64 (decision table 9, redirect rules 5, tenant controller/startup/switch 8, tenant widget flows 9 incl. auto-select, selection + search, inactive saved tenant, offline banner, maintenance, switch confirm/cancel, auth controller 3 + auth widget flows 5 incl. 401 -> login, guest->login->return, register->verify, forgot->reset, inline 422).
- `flutter build apk --debug` succeeded after adding `google_sign_in` (no native config changed).

### Open items / notes for later phases
- Test helper: `test/support/harness.dart` (`TestEnv`: fake backend, in-memory secrets, provider overrides; `settle()` lets retry back-off timers fire). Reuse it.
- Register cart/wishlist/etc. `protectedPrefixes` as routes are added; push token registration (`devices`) on login/logout is Phase 6.
- Account deletion, profile edit, change password UI: Phase 6.
- Real `X-Tenant`-less central host health check not surfaced; `update_available` (soft update) is parsed but not shown.

## Phase 4 - Catalog: home, categories, listing, search, product (done)

Working dir `/var/www/3alraf/app`. Nothing committed; stashes untouched.

### Built (`lib/features/catalog`, `lib/features/wishlist`)
- **Data**: typed models (`catalog_models.dart`, `product_models.dart`; helpers in `core/network/json.dart`), `CatalogRepository` (home, menu, brands, catalog + custom-pages paging, facets, suggest, category/brand/tag meta, product, reviews + add review, stock-request, bundles, gift finder search + cart, `cart/items` add, wishlist + toggle). `CatalogQuery` is value-equal (keys Riverpod families); `VariantPicker` is the pure, unit-tested variant resolution (complete selection, per-value availability, default selection).
- **Providers** (`catalog_providers.dart`): every provider calls `watchStoreScope` (watches `tenantSlugProvider` + `localeProvider`) so nothing survives a tenant switch or language change; product also watches the session. `ListingController` (autoDispose family keyed by `ListingScope`) does infinite pagination, sort/filter restart, stale-response guard, inline load-more retry; `WishlistController` is rebuilt (emptied) on tenant switch and sign-in/out. Recent searches are stored per tenant (`AppPreferences.recentSearches`).
- **Screens**: Home (hero carousel, category rail, promo strips/tiles, flash-sale rail + countdown that refetches at zero, gift-finder CTA when `features.gift_finder`, bundles rail, recommended / best sellers / most reviewed rails, brands rail, popup banner once per session and tenant, pull-to-refresh, skeleton), Categories tab (expandable tree + search pill), Listing (category / brand / tag / custom page / search; header with description + sub-category chips; sort sheet; filter sheet with price, brands, attribute values + colour swatches; infinite scroll; empty/error/skeleton), Search (debounced suggest, recents, results), Product (gallery with Hero + full-screen pinch zoom, variant picker with disabled impossible values, flash countdown, stock/low-stock chip, quantity capped by stock, HTML description via `flutter_widget_from_html_core`, reviews summary + list + all-reviews page + write-review sheet, related rail, share, wishlist heart, sticky buy bar, notify-me sheet with inline 422 errors), Bundles list/detail (add bundle to cart), Brands page, Gift Finder wizard (budget -> occasion/recipient -> category/restrictions) + results with per-item variant choice, bundles, near-budget list, add selected to cart, Wishlist tab.
- **Routing**: root-level routes (outside the shell, so the bottom bar hides): `/search`, `/search/results?q=`, `/category|brand|tag|custom|bundle/:slug`, `/product/:slug` (+`/reviews`), `/brands`, `/bundles`, `/gift-finder`. `/wishlist` added to `AppRoutes.protectedPrefixes` (guests tapping the tab go straight to `/login?from=/wishlist`; the old in-tab guest prompt stays only as an expired-session fallback). `resolveLink()` (pure, tested) maps banner/deep links (absolute store URLs, locale prefixes) to routes, other http(s) links open in the browser.
- **Drawer**: Brands, Bundles, Gift finder (hidden when the store feature is off).
- `Tenant` now keeps `base_url` (used for the product share link).
- l10n: ~110 new ar/en keys (gen-l10n re-run).

### Deviations / decisions
- **Add-to-cart** is only a thin `POST cart/items` call (product page, bundle page, gift finder) with haptics + snackbar action "View cart" (`go /cart`, still a placeholder). Cart state, badge and screens are Phase 5; the `ProductCard` has no quick-add button (variable products need a variant, so cards open the product page).
- Filter sheet uses text fields for price from/to (facet min/max shown as hint) instead of a slider; custom pages only get price + sort (API supports no more); `top_rated` sort is hidden on custom pages.
- Product page shows no computed "% off" badge (the detail payload has old/new price, the percent only exists on cards); cards show the server `discount_percent`.
- Home "category rails" of the web page are not in the API payload and were not mirrored.
- Hero tags are `(scope, slug)` objects passed through `GoRouter` `extra`, so the same product in two rails never collides; deep-linked product pages simply have no hero flight.
- Product description is rendered by `flutter_widget_from_html_core` (safe subset, no JS/WebView). Blog/static pages (Phase 6) can reuse it.
- Gift-finder `restrictions`, occasions and recipients mirror `GiftFinderService` constants (hard-coded lists, labels localised).

### Results
- `flutter analyze`: no issues. `flutter test`: 81/81 (previous 64 + 17 new): variant picker (resolve / availability / default / low stock), query value-equality + params (`brands[]`, `values[]`), link resolver, home parsing, repository params + pagination, **tenant switch refetches `/home` and rebuilds listing with the new `X-Tenant`**, wishlist load / optimistic toggle / empty on sign-out, widget flows (home -> product -> add to cart body `{product_id, variant_id, quantity}`, sold-out -> notify-me POST, category listing page-2 load + sort restart, guest heart -> login). Two existing assertions changed: `/wishlist` is now protected (`redirect_test`), and the guest->login->return test starts from the login page.
- `flutter build apk --debug` succeeds (new dependency `flutter_widget_from_html_core`).
- Not run: on-device visual check (RTL grids, hero flight, carousel), real backend smoke test.

### Open items / notes for later phases
- Phase 5: cart tab is still a placeholder; replace `CatalogRepository.addToCart` usage with the cart controller (badge, optimistic count) and call it from the same places (`product_page.dart`, `bundles_pages.dart`, `gift_finder_page.dart`). Session-aware providers can reuse `watchStoreScope` / `watchCustomerScope` in `catalog_providers.dart`.
- Phase 6: drawer still lacks pages/blog/affiliate/about/rate-app; launch (`launch/{code}`) pages not built; push token registration untouched.
- Product images are loaded with `memCacheWidth` but the API sends only full URLs / `thumb`; `thumb` is parsed but unused.
- Notify-me phone is sent as typed (server prepends the store country code); no country-code picker.
- `GET /products/{slug}` returns only the first page of reviews; the all-reviews page uses `GET products/{slug}/reviews`.

## Phase 5 - Cart, checkout, orders, payments (done)

Working dir `/var/www/3alraf/app`. Nothing committed; stashes untouched.

### Built
- **Cart** (`lib/features/cart`): typed models (`Cart`, `CartLine`, `CartTotals` keep every money value as the server string; `isZeroMoney` only decides whether a totals row is shown), `CartRepository` (`cart`, `cart/items` add/PUT/DELETE, `cart/quote`), `CartController` (Riverpod `Notifier`; watches `tenantSlugProvider`, locale and sign-in so it is emptied and reloaded on tenant switch / language / login / logout; generation guard against stale responses). Stepper is optimistic for the unit count, then replaced by the server cart, rolled back on failure (422 stock text shown). Discount code via `cart/quote {discount_code}`: accepted code is kept and re-quoted after every cart change, a 422 stays inline under the field. Cart tab (`CartPage`: lines, bundle components, unavailable chip, code box, `TotalsCard` with server totals, "shipping calculated at checkout" hint, sticky checkout CTA, pull-to-refresh, skeleton/empty/error) and an animated `CartBadge` (pop animation on count change) on the bottom-nav icon. `ShellScaffold` watches the cart so the badge loads at start.
- **Real add-to-cart**: `product_page.dart` and `bundles_pages.dart` now call `cartControllerProvider.notifier.add(...)`; `gift_finder_page.dart` keeps the gift endpoint and calls `cart.refresh()`. The thin `CatalogRepository.addToCart` was removed.
- **Auth/merge ordering fix**: `AuthController._finish` now runs `cart/merge` *before* flipping `signedIn`, so the cart controller (which reloads on sign-in) loads the merged customer cart instead of racing the merge. Guest -> checkout -> login -> back to `/checkout` with the cart merged works through the existing `?from=` flow.
- **Addresses** (`lib/features/addresses`): `AddressRepository` (`addresses` CRUD + `checkout/cities`), `AddressController` (emptied on tenant/locale/sign-out), `citiesProvider`, list page (default chip, edit/delete with confirm), form page (searchable city bottom sheet, default switch, inline 422 field errors, pops the saved address so checkout can select it). Account tab got "My orders" and "My addresses" entries (signed-in only).
- **Checkout** (`lib/features/checkout`): `CheckoutController` (autoDispose; same tenant/locale/session watches) holds only the shopper's choices; every number shown comes from `cart/quote` (live) or `checkout/validate` (review). Steps: address (saved addresses, default preselected, add new) -> shipping (`checkout/shipping?city_id=` of the chosen address, single option preselected, fee/free/ETA from server) -> packaging (only if the store has options) -> payment (COD, wallet/bank with instructions + account number copy + proof picker, online; COD with `cod_collect_shipping_upfront` asks for a prepay method + proof) -> review (summary, notes, validated totals, edit links). Requests are JSON, or multipart when a proof is attached. `stepForErrors()` (pure, tested) maps 422 field names to the step that owns them (discount_code -> back to cart); errors are shown inline on that step and on the review banner. Non-422 errors are snackbars. Placing clears the local cart and goes to the confirmation.
- **Orders/payments** (`lib/features/orders`): models (`Order` list+detail superset, `PlacedOrder`, `NextAction`, `PaymentReturn`), `OrdersRepository` (list paged, show, pay, payment-return, proof multipart, cancel, return, invoice download to temp file), `OrdersController` (infinite scroll), `orderProvider`. Pages: **confirmation** (reads `meta.next_action`: `none` -> awaiting-confirmation text; `redirect_to_payment` -> WebView opens automatically; `retry_payment` -> "Pay now" calls `orders/{token}/pay`; `upload_proof` -> uploader), **orders list**, **order detail** (status/payment/proof chips, items, server totals, address, timeline, returns; Pay now when `can_pay_online`, proof uploader when `can_upload_proof`, cancel sheet with the 9 server reason codes, return sheet with per-item quantities up to `quantity - returned_qty`, invoice PDF downloaded then shared via share sheet), **payment WebView** (`webview_flutter`; the gateway's hand-back to `/{locale}/payments/return/{token}` is intercepted, then `pollPayment()` asks `payments/return/{token}` up to 6 times / 2 s until `meta.paid`; paid / still-pending / error states, pops `true` when paid).
- **Routing**: root-level routes `/checkout`, `/orders`, `/orders/:token`, `/orders/:token/confirmation`, `/orders/:token/pay`, `/account/addresses`, `/account/addresses/edit`; `/cart` is now the real tab. `AppRoutes.protectedPrefixes` = wishlist, `/orders`, `/addresses`, `/account/addresses`, `/checkout`, verify-email (guests are sent to `/login?from=...`).
- l10n: ~140 new ar/en keys (cart*, checkout steps, order*, pay*, proof*, reason*, address*); gen-l10n re-run.

### Packages / platform
- Added `webview_flutter ^4.14`, `image_picker ^1.2`, `path_provider ^2.1` (invoice temp file); `share_plus` (already present) shares the PDF.
- Android: added `INTERNET` to the **main** manifest (it existed only in debug/profile, so release builds had no network). minSdk comes from Flutter's default (>= 21 required by WebView/image_picker is satisfied); no camera permission declared (image_picker uses the system camera/photo picker intents). iOS `Info.plist`: `NSPhotoLibraryUsageDescription`, `NSCameraUsageDescription` (English text placeholders).

### Deviations / decisions
- Checkout is sign-in only in the app flow (guest -> login -> back, cart merged). The API also allows guest checkout with name/phone/city/address; not built.
- Proof files are images only (camera/gallery through `image_picker`); the API also accepts PDF, which would need `file_picker` (not added).
- Order cancel is offered for statuses pending/confirmed/processing and return for delivered; the server stays authoritative (cancel needs feature `return_intel`, otherwise 404 shown as an error).
- Cart payload has no stock limit or variant label, so the stepper max is 99 and the server's 422 explains stock problems. No free-shipping hint exists in the API, so none is shown.
- The cart badge animates the whole icon+badge pair (scale bounce), not only the dot.
- Notes field is only on the review step; the saved-address notes are ignored by checkout.
- `PlaceholderPage` is no longer used by the router (left in the tree for Phase 6 screens).

### Results
- `flutter analyze`: no issues. `flutter test`: 106/106 (25 new): cart controller (load, add count from server, guest token echoed, optimistic stepper + rollback, remove, discount accept/reject inline, tenant switch refetch with new `X-Tenant`, merge-before-reload on login, safe parsing), cart widget flow (badge, totals, stepper PUT, guest checkout -> login), checkout (`stepForErrors`, init/default address/shipping query, proof rules incl. COD-upfront, place body + next action + cart cleared, 422 field mapping/jump, discount 422 -> cart, multipart validate), checkout widget flow (cart -> 5 steps -> place -> confirmation), orders (parsing, return-URL detection, `pollPayment` stop/give-up, paging, cancel/return/proof/pay request shapes, address CRUD + 422), redirect rules for the new protected routes. Two existing tests adapted (`app_test` cart tab now shows the empty cart; nothing else changed).
- `flutter build apk --debug` succeeds.
- Not run: real backend/gateway smoke test, on-device WebView payment, camera/gallery picker, invoice share sheet, iOS build.

### Open items / notes for later phases
- Phase 6: profile edit / change password / delete account / push token registration are still missing from Account; drawer still lacks pages/blog/affiliate entries.
- WebView payment has only been verified through the pure URL matcher and the poller; test one real gateway in Phase 7 (check that the gateway really redirects to `/{locale}/payments/return/{token}` and that cookies are not required).
- Order list/detail refresh after a webhook relies on pull-to-refresh; no push yet.
- Guest order tracking by token (deep link `/orders/{token}` for a guest) is blocked by the `/orders` protected prefix; if public tracking links are wanted, add a separate unprotected route.
- `ios/Runner/Info.plist` permission strings are English only (cosmetic indentation of the inserted keys too).

## Phase 6 - Account, affiliate, content, polish (done)

Working dir `/var/www/3alraf/app`. Nothing committed; stashes untouched. Added dependency `qr_flutter`.

### Built
- **Account** (`features/account`): edit profile (`PUT auth/me`; the current-password field appears only when the e-mail is edited), change password (password trio on the same endpoint), delete account (`DELETE auth/me`: password, or the "Google sign-in, no password" switch sends `confirm:true`; confirm dialog; wipes auth + cart token locally), About page (logo, version, rate app, contact, static pages), theme picker (light / dark / system, persisted via existing `themeModeProvider`) in Account and drawer. `AuthController` gained `updateProfile` / `deleteAccount`; `Customer` keeps `phoneLocal` + `phoneCountryCode`. Pages opened by deep link fall back to `/account` when there is nothing to pop.
- **Push** (`features/push`): `PushTokenProvider` interface + `NoopPushTokenProvider` (default; no Firebase files in repo), `DeviceRepository` (`POST/DELETE devices` with token, platform, locale, app_version), `PushRegistrar` (register after login/restore/register/google, unregister BEFORE the token is dropped on logout/delete; all failures swallowed). Steps to enable FCM are in the doc comment of `push_service.dart` (add google-services.json / GoogleService-Info.plist, firebase_core + firebase_messaging, implement the interface, override `pushTokenProviderProvider`).
- **Content** (`features/content`): static pages (`/page/:slug`), blog list (infinite scroll, pull-to-refresh) + post (`/blog`, `/blog/:slug`), contact page (WhatsApp / phone / e-mail / social from `GET store`), small-batch launch page `/launch/:code` (interest/survey/limited stock/pre-order form, consent, inline 422). HTML via `flutter_widget_from_html_core` (`SafeHtml`, links routed through `openLink`). `StoreInfo` now keeps `appLinks` and `hasBlog`.
- **Affiliate** (`features/affiliate`): separate token (`SessionStorage.affiliate*`, explicit `Authorization` header, `kSkipUnauthorizedExtra`, so the customer session is never touched; login is sent without any bearer). Screens: hub (login or dashboard) `/affiliate`, apply `/affiliate/register` (awaiting-approval state), dashboard (stats, tier / next tier, commissions, recent commissions), products with copy / share / QR (QR rendered locally from the server `link`), payouts (available balance + request, 422 shown), ambassador portal `/affiliate/ambassador` (403 feature_disabled -> unavailable state, join with disclosure + terms, tracked links per channel, ideas, report). Providers watch tenant + locale + affiliate session; controller rebuilds on tenant switch.
- **Drawer**: collections / custom pages (expandable), blog (when `footer.has_blog`), affiliate program, track an order (paste token or link), information pages (expandable), contact, about, rate app, appearance, switch country, language, WhatsApp, version.
- **Public order tracking**: `/orders` (list) is protected exactly; `/orders/{token}`, `/confirmation`, `/pay` are open to guests (API serves them by token). Guests see the order, can pay / upload proof / get the invoice; cancel and return are hidden (API needs a bearer).
- **Deep links**: `rewriteIncomingLocation` (pure, tested) maps `https://{slug}.3alraf.com/{ar|en}/...`, web `order/{token}`, `search?q=`, and web catch-all `/{slug}` (custom page) onto app routes; `resolveLink` also knows order, page, blog, launch, cart, about, contact, affiliate. Cold-start links are held in `PendingLink` and pushed by the splash after Home. Android: https intent filter (`3alraf.com`, `*.3alraf.com`, autoVerify) + `alraf://` scheme in the manifest. iOS: `FlutterDeepLinkingEnabled`, `alraf` URL scheme, and `ios/Runner/Runner.entitlements` with associated domains (NOT wired into the pbxproj; see open items).
- **A11y / perf**: tooltips on favourite heart and password visibility toggle, banner `Semantics(button)`, decorative logos excluded, text scale 1.3 smoke test on account / about / contact / affiliate; list keys on new lists; images use `cacheWidth`.
- l10n: ~85 new ar/en keys.

### Deviations
- Rate-app uses `app_links` from `GET store` (no store URL fallback from `app-config`); shows a snackbar when none is configured.
- Web catch-all `/{slug}` links open the custom-page listing; unknown single-segment http(s) links that used to open the browser now do the same (documented in `resolveLink`).
- Launch pages have no drawer entry (codes are shared as links/QR); reachable by deep link `/launch/{code}`.
- Ambassador report shows only valid / pending / excluded orders and window; link creation requires a product from the affiliate product list.
- Dark theme: the existing plum dark scheme from Phase 2; no per-tenant colours.
- `alraf://` custom-scheme routing is scaffolding only (not unit-tested; go_router receives scheme links as host+path).
- iOS entitlements file is not referenced by the Xcode project, because associated domains break signing with a free team.

### Results
- `flutter analyze`: no issues. `flutter test`: 132/132 (Phase 5: 106 + 26 new: redirect rules for public order tracking / content, deep-link rewrite + resolve, profile / password / delete controller + UI, push register/unregister ordering + no-op, guest order tracking UI, locale-prefixed order deep link, drawer entries, theme persistence, text-scale smoke, affiliate models/auth isolation/restore/errors/UI + QR, blog paging, launch join body, tenant-switch refetch of pages). Two older assertions adapted (`/orders/{token}` now public; `resolveLink` junk example).
- `flutter build apk --debug` succeeds.
- Not run: device checks of deep links, QR scan, share sheets, iOS build.

### Open items
- Phase 7: README with the FCM steps, assetlinks.json / apple-app-site-association hosting, `CODE_SIGN_ENTITLEMENTS` wiring, real-device check of deep links and the `alraf://` scheme.
- Guest checkout and guest order lookup by number are not built; tracking works only with the token link.
- Affiliate/ambassador flows were verified against fakes, not a live backend.
- Locale change after login does not re-register the push token.

## Phase 7 - QA, hardening, documentation (done)

Nothing committed; stashes untouched. Dev DB only read (GET smoke tests plus one guest cart add/quote with a throw-away `X-Cart-Token`, cart verified empty afterwards).

### Verified
- **Contract conformance (Dart vs Laravel)**: every path used by the 16 repositories exists in `route:list` with the same verb; request bodies diffed against the FormRequests (`CheckoutRequest`, `CartItemRequest`, `AddressRequest`, `ReviewRequest`, `StockRequestRequest`, `ReturnRequest`, `ProofRequest`, `DeviceRequest`, `DeleteAccountRequest`, reset/verify requests, cancel `reason_code`) - no mismatch found. Not called by the app on purpose: `orders/{token}/track` (timeline comes with the order), `orders/{token}/proof` GET, `affiliate/products/{slug}/qr` (QR rendered locally), `catalog` suggest/facets are used.
- **Live smoke test**: `php artisan serve` on a spare port against the dev DB (`Host: 3alraf.loc`, `X-Tenant: egy`), captured real envelopes for tenants, app-config, store, home, menu, catalog (+facets, suggest), product (+reviews), bundles, brand/category/custom-page, pages, blog, cities, packaging, payment methods, shipping, cart add/quote. A temporary Flutter test (deleted afterwards) pushed those payloads through the real repositories/dio interceptors: all parsed, key fields populated. Order/returns/affiliate/launch shapes were checked by reading `OrderResource` and the Dart models against each other and by the Pest tests (no orders in the dev DB; not exercised live).
- Backend: `MobileApiTest` + `MobileApiGapsTest` 32/32 (628 assertions). Full suite 651 tests: 600 passed, 4 failed + 47 errors, identical to Phase 1 and unrelated to the mobile API: `BlogTest` (web meta description `bad()`), `HardeningTest`, `QrCodeTest`, `StorefrontTest` (Content-Type charset), `CitySeederTest`, `GrowthOffersTest` (barcode column too long), and ~45 errors in `MediaTest` / `WhatsappChat*Test` from unwritable `storage/framework/testing/disks/*`.
- Flutter: `flutter analyze` no issues; `flutter test` 133/133 (132 + the new push test); `flutter build apk --debug` succeeds.
- Review results: no `print`/`debugPrint`/log interceptors (tokens cannot reach logs); `dynamic` only inside `core/network/interceptors.dart`; tokens, cart token, store cache and locale are all keyed by tenant slug and wiped on switch; no hard-coded user-visible strings found (only the brand name `3alraf` as a decorative logo semantic label and interpolated values); directional chevrons use `matchTextDirection` icons, no left/right absolute positioning; every `IconButton` has a tooltip.

### Fixed
- Changing the language after login now re-registers the push token with the new locale (`PushRegistrar.onLocaleChanged`, wired by a `localeProvider` listener in `AuthController`; no-op when no token is registered, so tenant switches and the no-op provider never hit `/devices`). Test added.
- `main.dart`: `FlutterError.onError` and `PlatformDispatcher.onError` handlers (output only in debug) so escaped async errors do not crash the app or print request data in release.
- Android: release signing from `android/key.properties` (falls back to debug key when absent); `<queries>` for https/tel/mailto (url_launcher on Android 11+); debug-only `usesCleartextTraffic` so local http backends work with debug builds while release stays https-only.
- `app/README.md` written (setup, dart-defines, run/build/release, signing, FCM steps, Google sign-in, deep links incl. assetlinks.json, apple-app-site-association and `CODE_SIGN_ENTITLEMENTS`).

### Final status
All PLAN §5 items are met in code; remaining work is configuration and device verification (below).

### Open items
- Real-device checks not done: deep links (App Links / Universal Links / `alraf://`), Google sign-in, share sheet, QR scan, online-payment WebView against a real gateway (confirm the gateway redirects to `/{locale}/payments/return/{token}` without cookies), iOS build.
- `assetlinks.json` and `apple-app-site-association` must be served by the web server on `3alraf.com` and every store host (Laravel has no route for them); `CODE_SIGN_ENTITLEMENTS` is still not wired in the pbxproj (needs a paid team).
- FCM: Firebase files, `firebase_messaging` and the `PushTokenProvider` implementation are not in the repo; backend `PushDispatcher` is log-only (no sending). Notifications inbox out of scope.
- Android release keystore and Play/App Store listings, store URLs in `config/mobile.php` / `store.app_links` (rate-app shows a snackbar until set).
- Guest checkout and guest order lookup by number are not built (tracking only via token link); affiliate/ambassador flows verified against fakes and Pest, not a live affiliate account.
- Per-tenant branding colours/logo/contact have no admin UI (defaults from `config/mobile.php`); the app uses the fixed 3alraf palette.
- Pre-existing backend failures listed above (web storefront/environment), plus `backend/public/favicon.ico` status unchanged from Phase 2.
- `stash@{0}` from Phase 1 still exists (content identical to the working tree at the time); drop it manually when sure.
- Soft-update (`update_available`) is parsed but not shown; `thumb` image sizes are parsed but unused; `matchTextDirection` relies on Flutter's built-in icons only.
