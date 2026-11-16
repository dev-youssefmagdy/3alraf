# Admin / Marketer split + redesign: test checklist

One file for every check of ADMIN_REDESIGN_MARKETER_SPLIT_PLAN.md. **Run once, at the end, after all changes** (owner instruction 2026-11-15).
Results go in the "Run log" at the bottom.

Reference: the previous phases ended with **8 known failures** that existed before this work (MARKETER_PLAN.md status log).
Any other failure is ours. If a failure is unclear, compare it against the code before this work (a `git worktree` of `bd22b3d`).

## 0. Prepare

```bash
cd /var/www/3alraf/backend
php artisan config:clear && php artisan view:clear
php artisan migrate --pretend | grep -E "marketers|move_marketing"   # 2 new migrations, check the SQL
npm run build
```

## 1. Automated suite (one run)

```bash
php artisan test --parallel                    # Unit + Feature + Migrations suites
php artisan test --testsuite=Migrations        # if the parallel run mixes it badly, run this suite alone after the rest
```

### Changed or new for this work (must all pass)

| File | What it proves |
|---|---|
| `tests/Feature/MarketerPanelTest.php` (rewritten) | permissions live on guard `marketer` only; `marketer-owner` has everything; the preset `marketer` role cannot approve / apply / reject / publish / issue tokens / manage accounts; pages render ar + en with **no `/admin/` link**; admin session → `/marketer` redirects to the marketer login and marketer session → `/admin` redirects to the admin login; old `/admin/gift-finder` 301 is gone (404); per-page gates; **phone + password + WhatsApp OTP login on `/marketer/login`** (admin credentials refused there, `last_login_at` set, logout, deactivation logs out); `marketer:create` command; API token create / log / revoke |
| `tests/Feature/MarketingSuggestionsEngineTest.php` | engine with `SuggestionActor::marketer`; **expect updates**: "owner and the marketer role approve" must become "only marketer-owner approves / applies" (owner decision Q2) |
| `tests/Feature/MetaAdsTest.php` | drafts / approval / PAUSED publish acting as a marketer; permission matrix (approve / publish = owner only) |
| `tests/Feature/SocialMediaLibraryTest.php` | library on guard `marketer`; **admin product editor has no social tab** and ignores `?tab=social` |
| `tests/Feature/MarketerAnalyticsTest.php` | analytics as marketer-owner, `marketing.analytics` gate |
| `tests/Feature/GiftFinderTest.php`, `GiftFinderBundlesTest.php` | gift-finder admin pages under the marketer guard; admin Settings → General switch still works with the admin guard |
| `tests/Feature/IntegrationApiTest.php` | integration clients managed from the marketer panel |
| `tests/Feature/MarketingApiTest.php`, `MarketingSuggestionsApiTest.php` | internal API unchanged; tokens now `created_by` a marketer |
| `tests/Feature/{Banners,Bundles,Blog,Seo,…}Test.php` (existing admin module tests) | shared views now use `<x-layouts.panel>` and panel-aware DataTable links: admin behaviour must be unchanged |
| `tests/Feature/WhatsappAuthTest.php`, `SecurityHardeningTest.php`, `HardeningTest.php` | admin login refactor (`PanelLoginController`) changes nothing for `/admin` |
| `tests/Feature/ConversionMigrationTest.php`, `SeedModesTest.php` | seeders: FoundationSeeder now also creates the demo marketer-owner `marketing@egy.test` / phone `201000000201` |

### Still to add before the run (tracked here)
- [x] Engine test updated: preset `marketer` role gets 403 on approve / reject / apply; `marketer-owner` succeeds.
- [x] Migration test `tests/Migrations/MarketerSplitMigrationTest.php` (own suite `Migrations`, runs `migrate:fresh` itself because DDL commits on MySQL): admin with marketing permission + authored token / suggestion / log becomes a marketer with the same phone and password hash; super admin → marketer-owner; ids remapped; `created_by_marketer_id` renamed; actor type `marketer`; marketing-only admin permissions gone, shared tool permissions kept; FK now nulls on marketer delete; `down()` restores admin ids.
- [x] Marketers accounts page: `MarketerPanelTest` "manages marketer accounts inside the panel" (owner only, one role, last-owner and self-lockout guards).
- [x] Marketing tools in the marketer panel: `MarketerPanelTest` "serves the shared marketing tools…" (banners, discount codes, flash sales, bundles, blog, static + custom pages, SEO, affiliates, product lists, reviews render under `/marketer` with no `/admin/` link; datatables answer; admin copies unchanged; permission gate).

- [x] System health page (`tests/Feature/HealthPageTest.php`, 4 tests): page + 11 checks, JSON per check, off / warn / fail states, permission gate. Manual: open /admin/health with WhatsApp connected and disconnected.

## 2. Browser scripts (one run each, throw-away DB)

```bash
npm run smoke                                # storefront + admin
node tests/Browser/marketer.mjs              # update login: /marketer/login with marketing@egy.test's phone 01000000201 / password
node tests/Browser/suggestions.mjs
node tests/Browser/social-meta.mjs
node tests/Browser/customer-cycle.mjs        # step "27 marketer pages" must log in through /marketer/login
```

The scripts that logged into `/marketer` as the admin owner must be pointed at the marketer login (phone `01000000201`, password `password`).

## 2b. Design system in code (B4 shells + components) — visual run

Checked already (2026-11-15, headless Chrome on http://3alraf.loc, no DB change needed): `/admin/login` 1366 + 390 and `/marketer/login` 1366 render the
split-screen design with the panel colours, no JS errors. Still to check **after `php artisan migrate` on a test DB** (needs logins):

- [ ] `/admin/welcome` greeting + shortcut tiles; `/admin` dashboard header + ERP tiles in brand / mint / sun / violet; `/admin/products` header with count.
- [ ] Any generic list (e.g. `/admin/banners`) — page header with "+ add", table card; same page under `/marketer/banners` in violet.
- [ ] Sidebar: plum (admin) / violet (marketer); shortcuts then groups; current group open; pop-pink count pills; user menu dropdown with logout; language links.
- [ ] ⌘K / Ctrl+K focuses the top-bar search; typing an Arabic or English page name lists matching sidebar links; ↑ ↓ Enter navigates; Esc clears.
- [ ] Phone 390 px: menu button opens the sidebar over a dark backdrop; tapping the backdrop closes it; no horizontal overflow.
- [ ] Affiliate `/ar/affiliate` and `/ar/affiliate/products`: mint sidebar on desktop, 4-tab bottom nav on phones, balance card with the sun payout button only when the balance > 0, bonus progress bar, tables scroll on phones; "Home ↗" link to the shop; storefront pages unchanged (no data-panel).
- [ ] OTP step (`/admin/login/otp`, `/marketer/login/otp`) shows "Step 2 of 2" in the same split layout; resend countdown still works.
- [ ] `tests/Feature/*` that assert old shell markup (e.g. text in the old header) — update the assertion, not the design.

## 3. Manual checks (http://3alraf.loc, ar + en, 1366 + 390 px)

- [ ] Admin sidebar (re-sorted): shortcuts Dashboard → POS → Orders → Reports, then groups Sales & customers, Catalog, Stock & purchasing, Offers & marketing, Affiliates, Content, Finance, Shipping & branches, Team & access, Settings; the group of the current page opens; badges (orders, returns, low stock, stock requests) still show; a staff member with few permissions sees only their groups.
- [ ] `/admin/login` → admin works; the admin sidebar shows no "Marketing panel" item; `/admin/welcome` has no marketer card.
- [ ] `/marketer` while logged in as admin only → marketer login page.
- [ ] `/marketer/login` → OTP → dashboard; header has its own logout + language switch; no "Admin panel" link anywhere.
- [ ] Marketer (preset role): Approve / Apply / Publish buttons hidden or 403.
- [ ] Logging out of one panel keeps the other panel's session.
- [ ] Production: back up the DB, run the migrations, check `marketers` rows created from admins and their roles.

## Run log

| Date | Command | Result | Notes |
|---|---|---|---|
| 2026-11-16 | `php artisan test --parallel --processes=8` (clean test DBs) | **754 / 763 passed**, 20,934 assertions | Every test changed or added for this work passes (MarketerPanelTest, MarketerSplitMigrationTest, engine / Meta / media / gift-finder / API tests). |

**Remaining 9 failures — none from this work** (verified by running them on untouched HEAD `bd22b3d` in a temporary worktree):
- the 8 known: `CatalogTest` bulk activation (405), `SingleStoreTest` (`admin.products.available` route no longer exists), `SeedModesTest`, `ImageHygieneTest` ×4, `ImageOptimizationTest`;
- `Unit\SystemSettingsTest` (from commit 2f88f30): its `->uses(..., RefreshDatabase)` does not migrate, so it errors when it is the first test on an empty DB.

**Order-dependent flake found (pre-existing, commit 2f88f30):** migrating an empty DB inserts `default_locale = ar` and `SystemSettings::apply()` makes that the app locale for every app booted after the migration, so English-expecting tests (Packaging, GiftFinder explanations, ERP "closed" messages) pass or fail depending on run order. `import_env_into_system_settings` also copies the *current* config into `settings` on every migrate, so a polluted test DB re-pollutes itself. Fix suggestion for the owner: in tests, do not apply `default_locale` / `sys_*` from the DB (or seed English in the test base).

**Environment fixes done for this run:** `storage/framework/testing/disks` (owned by another OS user, mode 700) renamed to `disks_old_joe_20261115` and recreated; polluted test DBs `3alraf_test` + 28 `3alraf_test_test_N` dropped / recreated.

**Browser scripts (2026-11-16, partial — stopped by the owner before the usage limit):** throw-away DB `3alraf_smoke` (`migrate:fresh --seed` with env overrides `DB_DATABASE=3alraf_smoke APP_URL=http://3alraf.test:8000 BROADCAST_CONNECTION=null META_ACCESS_TOKEN= META_AD_ACCOUNT_ID=`), served with `cd public && php -S 127.0.0.1:8000 ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php` (same env). Dev DB and `.env` untouched.
- `marketer.mjs`: **103 / 103 passed** (after removing the duplicate `data-panel` attribute from the shell; it now lives only on `<html>`).
- `smoke.mjs`: stops at step 4: `/en/product/android-phone-128gb` is 404 because `DatabaseSeeder` now seeds the gift catalogue and the generic `CatalogSeeder` product has no price; `DemoSeeder` fails on stock. Script out of date with the current seed (pre-existing, not this work).
- `suggestions.mjs`: **all checks passed** (exit 0) on the rerun with high-margin fixtures (products 1102 / 1101, picked by lowest cost / price). The first run's only failure was the 5% coupon correctly refused by the margin guard on product 1.
- `social-meta.mjs`: **138 / 139** + the last check verified by hand (product 1102 public page 200, private library item absent). Script updated: the library opens the new item after an upload, so the script returns to the list; the public-page check uses `FIX.slug` instead of the old `smoke-one` fixture. Media: a 1080×1080 PNG and a 2 s WebM recorded in Chrome (no ffmpeg on this machine).
- `customer-cycle.mjs` (DB `3alraf_smoke_qa`: `migrate` + `db:seed` + `GiftCatalogSeeder` (stock on 298 products) + `BundlePackagingDemoSeeder` (1 bundle) + 1 bundle copied by SQL, port 8099, offline-checkout IP limits raised): **959 / 967 checks, 33 / 41 steps**. Only gift-finder bundle steps fail ("results hold … bundles (0)" + the cart steps after it): the copied bundle has no gift tags, a fixture gap. Admin lifecycle, checkout, totals, cancel flow, separate marketer login (admin session refused on /marketer) and the full /marketer crawl (incl. the new tool pages) pass.
  - Fixed during the run: crawler now skips `/qr` downloads (new bundle pages in the marketer panel); missing translations `seo.fields.social.*` (labels are now the platform names) and `orders.fields.qty` (ar/en) — both also affected /admin.
- Not run yet: §2b / §3 visual checks by a person.
- Left in place: throw-away DBs `3alraf_smoke` and `3alraf_smoke_qa` (drop them or reuse them).
