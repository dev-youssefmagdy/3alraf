# Storefront redesign — plan

Owner request (2026-11-14): professional design of the website in **Figma + Canva**, then refactor the pages to it. Branch `storefront-redesign` (from `main` at fc03ef1).

## Decisions (owner)

- **Scope:** the whole storefront — every page under `resources/views/storefront/**`, the storefront layout (`components/layouts/storefront.blade.php`),
  shared partials (header, footer, `_bottom-bar`, `_product-card`, `_bundle-card`, `_grid`, `_pagination`, gallery) and the customer auth pages.
  Admin and marketer panels are out of scope.
- **Direction:** bold and colourful — strong colour blocks, big promo banners, playful shapes; a lively marketplace feel.
  Keep the 3alraf logo and the rose / gold brand family as the base, adding vivid supporting colours.

## Constraints

- Arabic (RTL) is the default language; every design works mirrored in English (LTR).
- Mobile first: most customers shop on phones (390px); desktop 1440px.
- Same features, routes, data and behaviour — this is a visual / markup refactor. No business logic changes; forms keep their field names,
  AJAX validation, `data-*` hooks used by JS, analytics/tracking partials and SEO tags (titles, meta, JSON-LD, canonical, hreflang).
- Accessibility: WCAG AA contrast for text, visible focus, 44px touch targets, `prefers-reduced-motion`.
- Performance: no heavy new JS framework; Tailwind 4 tokens in `resources/css/theme.css`; images lazy-loaded with sizes; keep LCP fast.
- **Sliders (owner, 2026-11-14): every horizontally scrolling section uses Swiper.js** (npm `swiper`, imported per page from the storefront Vite entry):
  rails (`components/rail.blade.php`), home hero / promo banners, brand and category strips, the product image/video gallery
  (`_product-gallery.blade.php` + `resources/js/lib/product-gallery.js`, keeping video autoplay in the main slide), gift-finder results and
  any other scroll row. RTL via `dir`, keyboard and a11y modules on, autoplay only on hero banners and paused for `prefers-reduced-motion`,
  no layout shift before init (CSS sizes slides first).
- Fonts: Cairo (Arabic + Latin) stays the body font unless the designer proposes a display font with Arabic support from Google Fonts.

## Deliverables

1. **Figma file** (team "Youssef Magdy's team", plan key `team::1303992898961658821`): Foundations (colour, type, spacing, radius, shadow,
   icons), Components (header, mega menu, search, footer, bottom bar, buttons, inputs, badges, product card, bundle card, rails, banners,
   breadcrumbs, pagination, empty states, toasts, modals), and every storefront page at 390 and 1440.
2. **Design spec** `backend/docs/design/STOREFRONT_DESIGN.md`: tokens (hex), type scale, spacing, components and page layouts, so developers
   can build without guessing; plus the Figma link per frame.
3. **Canva visuals**: hero / promo banners and section art matching the palette, exported and added to the site.
4. **Code**: storefront refactored to the design, in rounds.
5. **QA**: feature tests green (known pre-existing failures only), customer-cycle browser test, screenshots ar/en × 390/1440 of every page.

## Team (strictly one at a time)

1. Designer — Figma foundations, components, all pages; design spec.
2. Visual designer — Canva banners / art; exported into the project.
3. Frontend round A — tokens, layout, header, footer, bottom bar, shared cards and components.
4. Frontend round B — home, catalog, brands, bundles, bundle, product, gift finder.
5. Frontend round C — cart, checkout, order, account, wishlist, login / register.
6. Frontend round D — blog, pages, custom page, sitemap, affiliate pages.
7. QA — tests, browser cycle, screenshots, fixes list.

## Status log

- Branch created, scope and direction confirmed (2026-11-14).
- Designer done (2026-11-14): Figma "3alraf — Storefront redesign" https://www.figma.com/design/9ToQZAqy6EBNsLI4ufNq59 (foundations, 45 components, 42 page frames 390/1440 RTL, 19 states, EN-LTR Home/Product); spec backend/docs/design/STOREFRONT_DESIGN.md (tokens, Swiper options, pages, node links).
- Frontend round A done (2026-11-14): storefront tokens in storefront.css (admin/marketer pixel-identical), Cairo + Baloo Bhaijaan 2 fonts, new layout (announcement bar, header + mega menu, drawer, footer with trust band, bottom bar, toasts), restyled product/bundle cards, rail on Swiper (swiper@14.4.0, resources/js/lib/sliders.js presets: hero, categories, promo, rail, flash, brands, gallery, packaging, reviews, chips), x-breadcrumbs, x-empty-state, pagination; build + tests green (known failures only).
- Frontend round B done (2026-11-14): home (hero/categories/promo strips+tiles/flash/bundles/rails/brands all on Swiper presets, gift-finder CTA, popup sheet), catalog (colour header + sub-category chips, filters sidebar / mobile bottom sheet, active-filter chips, sort, no-results state), brands, bundles, bundle, product (Swiper `gallery` preset for every product: images + videos, variant-image slide, counter, share, qty stepper, sticky mobile add-to-cart, tabs, reviews) and gift finder (results as product / bundle / near-budget Swiper rows, summary chips, ink add-selected bar); legacy `data-slider`, `data-scroll-row`/initScrollRows and `product-gallery.js` removed; new x-slider-head, x-slider-arrows, _brand-tile, _trust-trio; build + tests green (known failures only), product-video.mjs 395/395.
- Frontend round C done (2026-11-14): cart (lines with gold bundle header, stepper, sun flash note, packaging on the `packaging` Swiper, sticky summary, x-empty-state), checkout (numbered steps, address cards, add/edit address bottom sheet / 520 modal with Esc + focus trap, shipping and payment choice cards, mint COD prepay block, dashed proof upload, discount code, error summary + red address step), order (mint success hero, copy number, timeline vertical/horizontal, totals with mint COD split, items, cancel), account (brand header, pill tabs below 1024, orders / addresses / profile), wishlist (+ empty), customer login / register (x-auth-shell); storefront.js cart lines, totals and shipping options restyled with the same data flow; customer-cycle failure after round B was data (round B's generic CatalogSeeder/DemoSeeder cart passed free_over → no prepaid shipping), not a regression: 40/40 steps, 733/733 checks on the documented `db:seed` + stock + bundles setup.
- Frontend round D done (2026-11-14): blog index (mint header, tinted post cards 1/2/3-col, storefront pagination) and post (pop cover band, display H1, `.sf-prose` rich-text styles, back + share, "more from the blog" on the new Swiper `posts` preset; JSON-LD unchanged), one content template look for page (sun-icon panel, text still escaped) and custom page (pop H1, pop-soft GET filter form, 2–5-col grid), sitemap left untouched (XML), affiliate login / register (cards inside the storefront layout, x-auth-shell not used since it is the full-screen lite shell; bonus benefit as a sun block), dashboard (tone stat cards, violet available card + payout request now handled by storefront.js, bonus card with the same progressbar, tables with headers) and products (QR download, barcode, pop copy, variant-links accordion), violet `_nav`; fixes: Swiper arrow keys drive only the slider under the pointer / holding focus (sliders.js scopeKeyboard), lite login/register load the fonts in `<head>` (base layout) instead of the body; tests 163/163 on the round filter, product-video.mjs 395/395.
- QA done (2026-11-14), verdict ship with notes: feature suite 756 tests, only the 8 known baseline failures (250 shared-DB collisions re-run green); browser suites on throw-away DBs: customer-cycle 40/40 steps 729/729, product-video 395/395, marketer 103/103, gift-finder 44/44 (needs bundles within the budget), smoke.mjs 3 admin packaging failures + /admin/ai 404s identical on main (stale script, not the redesign); sweep of every storefront page ar/en × 390/1440 (134 states): 0 JS/HTTP errors, no overflow / broken images / raw keys / escaped HTML, one H1 each, lang/dir right, SEO head identical to main (+BreadcrumbList on brands/bundles); admin dashboard, product form, marketer home pixel-identical to main; CLS 0, LCP = eager fetchpriority=high image; keyboard: mega menu Esc, sliders mirrored + scoped arrow keys, hero autoplay pause/toggle/reduced motion all pass. QA fixes: drawer focus trap + return focus, filters sheet focus + trap, 44px hit areas for 36px icon controls, checkout payment error placement + "payment method" attribute, account tabs overlap on phones, product stepper order/labels, required-asterisk contrast; §3 of the spec documents the scoped Swiper keyboard. Open notes: footer links / breadcrumbs / S buttons < 44px on phones, account form duplicate ids (shared x-input, pre-existing), hero/product LCP images have no srcset, affiliate products page unpaginated (pre-existing).
