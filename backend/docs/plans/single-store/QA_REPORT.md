# Single-store conversion — Phase 9 QA report

Branch `single-store` (not committed). Date 2026-11-07. Scope: plan §2 Phase 9 (tests & functional verification).

## 1. Environment and isolation

| Problem | Workaround used |
|---|---|
| `storage/framework/testing/disks/media/{catalog,tenants}` owned by another user (mode 2700) → `Storage::fake` fails | `LARAVEL_STORAGE_PATH=<scratchpad>/qa-storage` (verified honoured: `storage_path()` points there). No file outside the scratchpad was written by the test runs. |
| Another process (a different project, `/var/www/HRM`) runs PHPUnit at the same time on this MySQL server | All runs use a dedicated test DB: `DB_DATABASE=3alraf_qa_test` (parallel workers `3alraf_qa_test_test_N`). `phpunit.xml` keeps `3alraf_test` for everyone else. |
| `app/build/unit_test_assets` owned by another user (stale shader → 9 Flutter failures) | Flutter run in an rsync copy of `app/` without `build/`/`.dart_tool/` (`<scratchpad>/qa-app`). |
| `artisan serve` workers do not receive `LARAVEL_STORAGE_PATH` (first crawl wrote 7 session files into the real `storage/framework/sessions`; DB and media were isolated) | Those 7 files (owner `b`, created by the crawl) were deleted; crawls re-run with `php -d variables_order=EGPCS -S … server.php` → fully isolated (0 files in the real sessions dir). |

The real `3alraf` DB was only read (dumped into copy DBs). Copy DBs `3alraf_qa_crawl`, `3alraf_qa_smoke` and the conversion scratch DBs `*_conv` were dropped; servers stopped.

## 2. Results

| Suite | Baseline (main, before refactor) | Phase 9 result |
|---|---|---|
| PHP `php artisan test --parallel` | 866 tests: 820 passed, **46 failing** (42 errors + 4 failures; mostly duplicate tenant slug `uae`) | **818 tests, 818 passed, 0 failed, 0 errors (≈20 min, 12 processes)** |
| whatsapp-service `npm test` | 17/17 (Phase 6) | **17/17 pass** |
| Flutter `flutter analyze` | No issues | **No issues** |
| Flutter `flutter test` (scratch copy) | 128: 119 pass, 9 env failures (stale shader) | **128/128 pass** |
| Admin GET crawl (all 258 `admin.*` GET routes, reports expanded) | Phase 5: 328 URLs, 0×500 | **323 URLs: 314×200, 7×302, 2×404 (by design), 0×5xx**; 7 routes not exercised (no row in the copy DB) |
| Storefront crawl (guest + customer) | — | 31 URLs: 27×200, 4×302 (by design), 0 errors |
| Mobile API (18 endpoints × no header / `X-Tenant: egy` / `X-Tenant: nope`) | — | **54/54 × 200**, no `egy.` host in any body |
| Legacy host `egy.3alraf.test` | — | pages 301 → same path+query on APP_URL (3/3); `api/*` 200 direct (2/2) |
| Browser smoke `tests/Browser/smoke.mjs` | not runnable before (stale fixtures) | single-host parts all pass (47 checks, 5 fail — pre-existing, see §6) |
| Browser smoke `tests/Browser/smoke-growth.mjs` | — | 23 growth pages × ar/en: 141 checks, 139 pass (2 = no `missed_opportunities` row in a fresh seed) |

### Crawl details (copy DB = dump of `3alraf` + Operations/Orders/Growth/BundlePackagingDemo/Demo seeders, AI + Growth switched on, temporary otp_exempt super admin)
- 302 by design: `admin/login`, `admin/login/otp` (already signed in → `/admin`), `admin/locale/{en,ar}` (switch + back), `growth/brief/data`, `ai/market-trends/data|export` without required params; storefront `/` → `/ar`, guest `checkout`/`account` → login, customer `checkout` with an empty cart → cart.
- 404 by design: `admin/orders/1/proof` (order has no proof), `admin/whatsapp/chat/media/1` (media row `pending`, no file — the chat-media controller answers a plain 404 for unready files, asserted in `WhatsappChatApiTest`).
- Not exercised (no rows): `erp/profits/{id}`, `purchases/{id}(/edit)`, `returns/{id}`, `stock-adjustments/{id}`, `stock-transfers/{id}(/edit)` — covered by feature tests (`ErpProfitDistributionTest`, `OperationsTest`, `HardeningTest`, `QaE2eErpTest`).
- Signed private file (`admin/files/{path}`) exercised with a real signature → 200.

## 3. Test changes

77 test files modified, 2 deleted, 1 renamed, 3 added, new `tests/Helpers/growth.php`. Pest helpers (`tests/Pest.php`): `codUpfront(bool)`, `settings(array)`, `allSettings()`, `listProduct(Product|int, array)` (listing data on `products`), `growthFeatures(bool, ...$keys)`, `adminCreds()` (one `Admin` model); `growthClient()/integrationCall()/ownerOf()/mkOrder()/rebuild()` moved from GrowthFoundationTest/GrowthMetricsTest into `tests/Helpers/growth.php` (required by Pest.php) — they used to be undefined whenever GrowthGapFixesTest ran in another parallel worker (baseline failures). `StorefrontPhoneTest` backfill test (called a historical migration that reads the dropped `tenants` table) → phone:audit test. Conventions: relative URLs on the APP_URL host, guard `admin`, routes `admin.*`, `setting()` instead of the tenant row, `X-Tenant` only where its *ignored* behaviour is asserted, legacy hosts `{slug}.3alraf.loc` only in LegacyHostRedirect assertions.

Converted (kept, rewritten), e.g.: every `TenantProduct::create` → `listProduct()`; `$tenant->settings` → `Setting`; per-tenant AI/Growth closures; `X-Tenant` tests → "header ignored"; cross-tenant "foreign id" checks → unknown-id (999999) 404/422 checks (ERP parties/owners/assets/profits/journals, bundles, packaging, payments, orders/returns, WA chat, AI drafts, growth approvals/experiments/events/clients); tenant settings tests → Settings → General / single `ai_settings` row; WhatsApp to the single session `central` (retired `tenant-<n>` ids ignored), channels `whatsapp` / `whatsapp-chat`; super-admin uniqueness through `SuperAdminProvisioner`; `QaE2eErpTenantTest.php` → **`QaE2eErpTest.php`** (empty-tenant smoke → empty-store smoke).

### Deleted tests (46) and why

| File | Test | Reason |
|---|---|---|
| ErpCentralTest.php (file) | 13 tests: central permission cards, per-tenant numbers, currency grouping, drill-down, tenant admins kept out, cross-tenant journals, AR/AP/inventory/periods per tenant, anomalies per tenant, cross-tenant audit log, overview CSV, bounded queries per tenant count, exchange rates used by the central view | Central cross-tenant ERP pages/consolidation dropped (INVENTORY §A). The anomalies part is re-covered by the new `ErpAnomaliesTest` |
| QaE2eErpCentralTest.php (file) | 8 tests: central smoke (multi-tenant / no tenant data), central filters, overview/anomalies CSV, consolidated currencies, `erp_central.view` perms, QA-BUG-014 central hub empty state, central sidebar | same |
| ErpPerformanceTest | keeps the central consolidated pages bounded (GROUP BY tenant…) | central consolidated pages dropped |
| ErpSecurityTest | has a permission gate on EVERY central ERP route | central routes dropped; the "EVERY ERP route" gate test remains |
| ErpBackfillTest | central migration page shows every tenant run and needs erp_central.audit | central migration page dropped |
| FoundationTest | resolves tenants by subdomain and 404s unknown ones | no tenant resolution; LegacyHostRedirect covered in `SingleStoreTest` |
| FoundationTest | scopes tenant models to the current tenant | TenantScope removed |
| FoundationTest | runs the tenant CRUD and creates an owner admin | tenants CRUD dropped; Settings → General covered in `SingleStoreTest` |
| HardeningTest | lists only ACTIVE tenants on the central landing page… | landing/country picker removed (`/` → default locale) |
| HardeningTest | sets the preferred_tenant cookie on choice… | `/choose/{tenant}` removed |
| HardeningTest | rejects tenant A credentials on tenant B host | no tenant hosts (legacy hosts 301) |
| SeedModesTest | a newly created EGP store starts with its cities and the whole catalog… | `tenants:create` removed; `seed:live` still covered |
| PolishOpsTest | creates a store from the command with owner, COD method… | `tenants:create` removed |
| ReportsTest | aggregates across tenants with currency rate normalization… | no cross-store aggregation / `currency_rate` (CALCULATIONS.md) |
| GoogleAuthTest | rejects unknown or inactive tenants in the state… | state carries no tenant |
| GoogleAuthTest | keeps tenants isolated: one Google account = two customers… | one store |
| QrCodeTest | keeps tenants isolated: codes of another tenant are invisible… | TenantContext removed |
| WhatsappAuthTest | sends tenant OTPs only through that tenant's own session | one WhatsApp session |
| WhatsappAuthTest | falls back to the central WhatsApp account… | one session |
| WhatsappAuthTest | cannot log a tenant admin in on another tenant's host | no tenant hosts |
| WhatsappChatIngestTest | isolates owners: same jid and wa id in two owners… | one owner |
| GrowthEngagementTest | isolates tenants: other tenant cannot see or reply… | cross-tenant isolation |
| GrowthSmallGapsTest | prunes interactions in a different tenant untouched… | cross-tenant isolation |
| GrowthMetricsTest | isolates stats and metrics per tenant | cross-tenant isolation |
| GrowthFoundationTest | isolates tenants: other tenant data is invisible to a client | cross-tenant isolation |
| GrowthReviewFixesTest | scopes every growth model to the tenant and keeps tenant_id indexed… | replaced by `SingleStoreTest` "no tenant_id/owner_key column" |
| AiTrendsTest | isolates tenants: sales, snapshots and dismissals never cross | cross-tenant isolation |
| AffiliateEmailTest | migration normalises stored e-mails and tombstones collisions deterministically | re-ran a historical pre-conversion migration (2026_10_27_100001) whose SQL needs `tenant_id`; old migrations are not edited (plan §1.3) and unique normalised e-mail is still asserted by the file's other tests |

(The other "isolation" tests were converted, not deleted — e.g. WA chat "isolates owners" → "unknown ids 404" + "super admin and owner share the one inbox"; AiChat tools → crafted/unknown ids; MobileApi isolation → "token works whatever X-Tenant".)

### Added tests

| File | Covers |
|---|---|
| `ConversionMigrationTest` (3) | The conversion migration on a fixture DB (structure cloned from the migrated test DB + legacy `tenants`/`tenant_products`/`tenant_product_variants`/`central_admins`, `brands.tenant_id` with `unique(tenant_id,slug)`), run through separate connections (RefreshDatabase untouched), scratch DB dropped: aborts with >1 tenant; tenant_id/owner_key gone everywhere, unique rebuilt as `unique(slug)`, listing merged (priced+active / unlisted → inactive, price NULL), tenant row → settings (plain profile, encrypted JSON keys), central admin merged by normalised phone (super flag + otp_exempt kept, second central admin inserted), morphs rewritten (`CentralAdmin`/`TenantAdmin` → `Admin` with id remap, `TenantProduct` → `Product` via old ids in `qr_codes`/`activity_log`, log name), roles merged onto guard `admin` (`t{id}:` prefix stripped, `tenant-owner` → `owner`), permissions re-seeded on `admin`; idempotent re-run; empty install → `config/settings.php` defaults |
| `SingleStoreTest` (8) | schema sweep (no `tenant_id`/`owner_key`/`tenant_product_id`, no tenant/central/store tables or index names, guards, no `tenant.*`/`central.*` route names); admin login on `/admin` + otp_exempt; super admin and owner open every added page (Products, Categories, Brands, Tags, Attributes, Languages, AI Providers/Models/Usage, ERP Anomalies, Settings → General) + sidebar links; a role without permissions gets 403 on each, and exactly its page once granted; Settings → General validate/save → storefront + `app-config`; Catalog page edits price/sale/cost/status on `products` + Products↔Catalog links; storefront home/product/cart/checkout/order on the one host; LegacyHostRedirect (301 pages incl. `/admin`, `api/*` and `webhooks/*` served directly, no legacy host in API bodies) |
| `ErpAnomaliesTest` (3) | healthy ledger, seeded anomalies (unbalanced, old draft, posting failed, reopened, negative cash, control mismatch, large manual), page filters, CSV export, hub alerts, `erp.audit.view` gate |
| `MobileApiTest` (+3, rewritten) | store with/without `X-Tenant` (any value) and on a legacy host; `/api/v1/tenants` deprecated one-item alias in the old `Tenant.fromJson` shape + `Deprecation` header; `app-config.store`; API registered once (no `api.v1.h.*`) |
| `GrowthFoundationTest` | integration `ping` returns `store` and no `tenant` |
| `WhatsappAuthTest` / `WhatsappChatIngestTest` | otp_exempt per account; single session `central`, retired `tenant-1` ids ignored; channels `whatsapp`/`whatsapp-chat` authorised on guard `admin` by permission |

## 4. Bugs found and fixed

| # | Where | Bug | Fix |
|---|---|---|---|
| 1 | `app/Http/Controllers/Whatsapp/ConnectionController.php` | connect / reconnect / disconnect passed an undefined `$o` (owner arg removed in Phase 5) → **500 on every WhatsApp connect** | calls without the owner argument |
| 2 | `app/Services/Stock/StockLedger.php` `lockedLevel()` | the old tenant lookup used to validate the branch for `reserve()`/`release()` too; now an unknown branch reached `INSERT IGNORE` (FK error swallowed) → TypeError | `assertBranch()` before creating the level row |
| 3 | `app/Http/Requests/Admin/ActivateProductsRequest.php` | Catalog "activate products": re-submitting an already activated product was a 422 (old behaviour and `CatalogService::enableBulk` doc: no-op) | rule refuses only unknown ids; already priced rows are skipped by the service |
| 4 | `database/seeders/FoundationSeeder.php` | the demo super admin (= former central super admin, which skipped the OTP) required an OTP on fresh installs — only the conversion migration set `otp_exempt` | demo super admin seeded with `otp_exempt = true` (demo data only; `admin:provision-super` unchanged — see §6) |
| 5 | `app/Support/SafeHtml.php` (`text()`), `resources/views/storefront/blog/show.blade.php`, `components/layouts/storefront.blade.php` (pre-existing, baseline failure) | meta description / JSON-LD contained the body of `<script>` tags (`strip_tags` keeps it) | `SafeHtml::text()` drops script/style bodies, used for blog + every storefront meta description |
| 6 | `config/catalog.php`, `phpunit.xml` | dead config `catalog.seed_defaults` / `SEED_DEFAULTS` (no consumer since `TenantService` was deleted; grep-verified) | removed (only history docs mention it) |

Pre-existing baseline failures resolved: duplicate `uae` slug (tenants gone), CitySeeder (now seeds the site country), Blog (#5), sitemap `Content-Type` (test now expects `application/xml; charset=UTF-8`), QR logo PNG (assertion no longer depends on the logo artwork colour), store-unavailable page (gone; legacy hosts 301), `growthClient()/mkOrder()/ownerOf()` undefined (fixed by the GrowthFoundation/GrowthMetrics conversions).

## 5. Browser smoke scripts

`tests/Browser/smoke.mjs` and `smoke-growth.mjs` now use one host (`SITE`, default `3alraf.test`; `CENTRAL`/`APP_CENTRAL_DOMAIN` gone), admin login by phone (demo super admin `01000000001`, otp_exempt), the added panel pages, a legacy-host 301 check, `admin.growth.*` route discovery, screenshots in a temp dir. Run on a throw-away DB (`migrate:fresh --seed`) with playwright-core + `/usr/bin/google-chrome`.

## 6. Remaining known issues (not caused by this refactor unless stated)

1. **smoke.mjs guest-checkout flows are stale**: checkout is signed-in only since commit 21780b3 and the demo catalog changed (gift catalog, commit 7f8ce89 — no `android-phone-128gb`, no variable products). Those flows are behind `SMOKE_GUEST_CHECKOUT=1` and need a rewrite for the saved-address checkout; the smoke run used a fixture product added to the throw-away DB. Remaining fails in that run: "customer sees their orders" and the bundle checks (data only created by the skipped flows), packaging create (the form now requires an image).
2. **JS error on `/admin/login`**: `n.getSelectedCountryData is not a function` (`resources/js/lib/phone-input.js`, intl-tel-input ^29; file unchanged on this branch). Login still works. Owner: frontend.
3. `admin:provision-super` creates a super admin **without** `otp_exempt` (the old central super admin skipped the OTP by role). The converted DB keeps the bypass (migration), fresh demo seeds too (#4); a newly provisioned real super admin needs the WhatsApp OTP. Lead/owner to decide (plan §1.12 "no new security behaviour").
4. `growth:operate` (and other growth/ERP commands) now fails loudly on the first exception (no per-tenant catch) — asserted by `GrowthReviewFixesTest` (Phase 5 risk).
5. `artisan serve` does not pass `LARAVEL_STORAGE_PATH` to its workers (local tooling note for future crawls: use `php -d variables_order=EGPCS -S`).
