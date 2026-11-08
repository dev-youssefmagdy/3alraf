# Marketer release QA: full customer cycle (P9)

Date: 2026-11-08. Branch `single-store`. Script: `tests/Browser/customer-cycle.mjs` (real Chrome via playwright-core).
Final run: **40 / 40 steps, 778 / 778 checks, 0 failures.**

## Environment
- Throw-away DB `3alraf_smoke_qa`, served by `php artisan serve --port=8099` with env overrides. Dev DB `3alraf` and `backend/.env` were not written to. The DB and the server were removed after the run; proof files written by the test orders were deleted.
- Schema: loaded from the dev DB structure (no rows except the `migrations` table), then `php artisan migrate` (nothing pending). A plain `migrate:fresh` was too slow while another agent's parallel Pest run loaded MySQL.
- Data: `php artisan db:seed` (catalog, admins, shipping rates, payment methods). The default seed has no stock and no bundles, so on top of it: opening stock of 50 on 60 products through `StockLedger`, 2 active bundles, and 1 product suggestion, 1 applied social suggestion, 1 Meta draft, 1 API token and 1 integration client so the marketer detail pages have rows.
- The server ran with `CHECKOUT_OFFLINE_IP_ATTEMPTS` and `CHECKOUT_OFFLINE_IP_DAILY` raised. All test orders come from one IP, and the anti-abuse cap (5 offline orders per IP per 10 minutes) is correct behaviour that blocked repeat runs. Per-contact limits and the open-order cap stayed at their defaults.
- Settings during the run: tax 0, COD upfront ON (default), COD extra fee OFF, no discount code, no packaging.

## Results
Every storefront step ran in ar and en at 1366px and 390px. Each step also fails on JS errors, failed requests (4xx/5xx other than intended ones) and untranslated keys, including keys rendered later by JS such as toasts and cart lines.

| Step | Result |
|---|---|
| Home, direction, product cards, gift finder in nav (top nav at 1366px, bottom bar at 390px), home CTA | Pass |
| Category, brands, brand, search, empty search, no horizontal overflow | Pass |
| Product page, add to cart | Pass |
| Bundle list, bundle page, add to cart | Pass |
| Gift finder: results hold products and bundles, add one of each, share link shows the same results | Pass |
| Cart: + raises subtotal, - restores it, remove drops the line | Pass |
| Guest checkout sent to login | Pass |
| Register, guest cart survives; logout shows an empty guest cart; login brings the customer's cart back | Pass |
| Guest items merge into the customer cart at login (en, 390px) | Pass |
| Checkout: add address, shipping, COD, wallet prepay of shipping with proof | Pass |
| Order success page, invoice PDF, account list, order detail | Pass |
| Totals against CALCULATIONS.md (below) | Pass |
| Stock: reserved at placement, on_hand unchanged | Pass |
| Cancel OFF: no form, endpoint answers 404, order stays pending | Pass |
| Cancel ON (en 1366px, ar 390px): form shown, reason required, cancelled, reason stored, reservation released, on_hand unchanged, no form afterwards | Pass |
| Admin: order listed, same total, proof approved to `partially_paid` | Pass |
| Admin status confirmed, processing, shipped, delivered; customer timeline follows each one | Pass |
| Stock at shipment: on_hand and reserved both drop by the ordered quantity; no cancel form after shipment | Pass |
| Delivered COD order: `paid_amount = total` | Pass |
| Marketer panel: 27 pages (nav plus suggestion, media and Meta detail/edit pages) at ar/en 1366px; same pages return 200 with no overflow at ar/en 390px | Pass |

Totals checked on every order: order subtotal = cart subtotal; order total = checkout total; `line_subtotal = unit_price x qty`; subtotal = sum of lines; `cogs` = sum of `unit_cost x qty`; shipping = rate for the city (with `free_over`); `shipping_net = shipping - shipping_discount`; `total = subtotal - discount + shipping_net + tax + packaging + payment fee + cod_fee`; `prepaid_shipping = shipping_net`; `cod_amount = total - prepaid_shipping`; bundle lines sum to bundle price x whole quantity; `net_profit` is 0 while pending and matches the profit formula at confirmation.

## Defects
Found and fixed:
1. Marketer suggestion detail showed the raw key `marketing.suggestions.targets.social_media_asset` once a social suggestion was applied. The same label was missing for `marketing_theme` (a seasonal theme result, found by reading the code and not exercised in the browser). Added both, ar and en: `lang/ar/marketing.php`, `lang/en/admin.php`.

Found and not fixed: none. No money rule changed, and `CALCULATIONS.md` needed no update.

Observation for the owner (not a defect): `DatabaseSeeder` seeds the catalog with no stock, although the `seed:dev` command text says "gift catalog WITH opening stock". This is likely behind the known failing `SeedModesTest`, which is in the baseline list.

## Not tested
- Product video autoplay: checked separately after P8.
- Product gallery behaviour (thumbnails, swipe, lightbox): owned by P8. Images rendered with no errors on the product page; no gallery defect seen.
- Tax, discount codes, packaging, COD extra fee and COD with upfront OFF: all off in the seed, so the totals above cover only those branches at zero. Unit tests cover the formulas.
- Online payment: the gateway is inactive in the seed.
- Return requests: hidden in the storefront (`@if (false ...)` in `order.blade.php`).
- Variant products: the stocked products used were all simple.
- Marketer write actions (approve, apply, publish) from the browser: pages only. They were driven through the engine to build the test rows.
- Live site `http://3alraf.loc`: the run used a separate host and port so no test orders reach dev.

## Pest
Full suite, run once at the end (parallel): 704 tests, 696 passed, 8 failed. All 8 are the known baseline failures (`SeedModesTest` 1, `ImageOptimizationTest` 1, `CatalogTest` 1, `ImageHygieneTest` 4, `SingleStoreTest` 1). No new failures, including P8's in-progress tests.

## Re-running
See the header of `tests/Browser/customer-cycle.mjs`. It refuses any DB other than `3alraf_smoke_qa`. Screenshots from this run are in the session scratchpad and are not part of the repo.
