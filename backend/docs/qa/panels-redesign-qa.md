# Panels redesign QA (Admin, Marketer, Affiliate) - 2026-11-20

Environment: http://3alraf.loc (dev DB, 1192 products, 0 orders, 0 suggestions, 0 variants), headless Chrome via playwright-core, real login forms only.
Accounts: admin super admin 01000000001 (otp_exempt); marketer: throwaway "QA Marketer" (marketer-owner, phone 01000000299, created with `php artisan marketer:create`, otp_exempt set so the browser can log in without WhatsApp OTP; delete it if not wanted); affiliate: seeded aff@egy.test.

## Checklist

| # | Item | Result |
|---|------|--------|
| 1 | Raw translation keys, ar + en, all sidebar pages, 3 viewports | PASS after fix (admin 312 page loads, marketer 192, affiliate 66: all 200, 0 console errors, 0 raw keys) |
| 2 | Responsive (390 / 768 / 1440), drawer, bottom nav, rail, palette, dark, RTL/LTR | PASS with minor open items (below) |
| 3 | Functional smoke | PASS for what the dev data allows (see not verified) |
| 4 | Accessibility basics | PASS after fix |
| 5 | Pest: dashboard gating test + required files | PASS (65 tests / 602 assertions: AdminDashboardTest incl. 3 new gating tests, PosTest, QaE2eErpI18nPermsTest, AffiliateBonusTest, SeoTest, SystemSettingsPageTest; plus MarketerPanelTest 9/9) |
| 6 | npm run build + re-crawl | PASS (build OK; re-crawl status 200, no console errors) |

### 1. Raw keys
English strings for the marketer panel are not in `lang/en/marketing.php`: `AppServiceProvider::registerLangShims` serves `lang/en/admin.php` key `marketing` as the `marketing` group. A flattened diff of `lang/ar/marketing.php` vs that group showed only 2 missing en keys: `tokens.scope_desc.suggestions_write_new_product` (visible raw on /marketer/api-tokens in en) and `suggestions.errors.apply_interrupted`. Both added to `lang/en/admin.php`. ui / reports / pos / admin groups: no ar/en gaps found by the same diff.

### 2. Responsive
- Sidebar side correct in all cases (right in ar, left in en at 768/1440), drawer opens at 390 and closes on Esc, bottom nav present at 390, rail at 768, Ctrl+K palette opens/closes, dark toggle works, in all three panels and both languages (240 automated checks ok).
- No horizontal page overflow on any crawled page.
- Fixed: dashboard "top products" table was clipped at 390 (no scroll); wrapped in `overflow-x-auto`.
- Fixed: touch targets below 44px on mobile: `.btn-sm` (36px, table card actions, "Filters" button), lang tabs AR/EN (32px), intl-phone country button (42px), product "search by image" button (40px). 44px minimum now enforced below 768.

### 3. Functional
- Admin login, product create form: submitting from the Pricing tab with empty required fields switches to the General tab and focuses `name[ar]` (1440 and 390). Error toast shown.
- POS at 390 and 1440: search list loads, add product, floating cart button with count/total, cart sheet opens/closes, no overflow. Variant image fallback is covered by PosTest (no variants exist in dev data).
- DataTables in ar (products, 1192 rows): Arabic strings, search ("دوف" -> 7 results), pagination ok, cards on mobile.
- Affiliate: links page copy button is 44px high.
- Fixed: affiliate copy-link did nothing (the `[data-copy]` handler lives in storefront.js behind `window.__sf` and growth-form.js, neither active in the affiliate panel; pre-existing at HEAD). Handler added to `ui.js` for the affiliate panel; verified clipboard write + toast.
- Fixed: validation message showed raw attribute `name.ar`; added `name.ar` / `name.en` to `lang/ar/validation.php` attributes.

### 4. Accessibility
- `x-ui.tabs panels`: tab buttons now get `id` + `aria-controls`; panels get `role="tabpanel"`, `id`, `aria-labelledby` (done in `ui.js`, verified in browser).
- Drawer: Esc closes; modals trap focus (Tab loop), close on Esc and restore focus (code review + drawer Esc test). Command palette closes on Esc.

### 5. Tests
New in `tests/Feature/AdminDashboardTest.php`: reports.view-only admin sees no recent-orders / low-stock cards and no customer name or SKU; orders.view + products.view admin sees both; orders.view without stock permissions sees only recent orders. Full suite not run (known pre-existing failures untouched).

### 6. Build and re-crawl
`npm run build` OK. Re-crawl after the final build: admin 312 page loads, marketer 192, affiliate 24 (ar + en, 390/768/1440): all 200, no console errors, no overflow, no raw keys.

## Defects fixed
1. Raw `marketing.tokens.scope_desc.suggestions_write_new_product` in en (+ missing `apply_interrupted`).
2. Dashboard top-products table clipped on mobile.
3. Touch targets < 44px on mobile (btn-sm, lang tabs, phone country button, image-search button).
4. Affiliate copy-link button dead.
5. `name.ar` raw attribute in validation errors (ar).
6. a11y: tabs panels aria-controls / role=tabpanel.

## Defects open (minor)
- Buttons `btn-md` are 40px at >= 768 (spec value for desktop; not changed).
- Products list at 768 scrolls the table horizontally inside its container (881px table in 612px); more columns could opt into `.dt-hide-md`.
- Mobile product form: the error toast overlaps the sticky save bar while visible.
- RTL: the command palette pill placeholder shows its "(K⌘)" suffix on the wrong side (bidi).
- QR image files missing on disk in dev (404 on affiliate links page, previously known).
- Affiliate copy fallback uses `window.prompt` on non-secure origins (http://3alraf.loc), toast path only works on HTTPS/localhost.

## Not verified
- Order show page (no orders in dev DB), marketer suggestions list/show (no suggestions), POS with a variant product and quote with real variant data, product create success + delete (stopped after validation check; no data created), datatable column sort, WhatsApp-OTP login paths, focus-ring visibility only checked by reading CSS (no pixel check), real screen-reader behaviour, Reverb realtime bell.
