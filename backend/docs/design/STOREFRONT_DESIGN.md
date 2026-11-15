# 3alraf storefront redesign: design spec

Direction (owner, 2026-11-14): **bold and colourful**, a lively marketplace with strong colour blocks, big promo banners and playful shapes. It is built on the existing logo and the rose/gold family, plus four vivid supporting colours. Arabic RTL comes first; every screen also mirrors to English LTR. The site is designed mobile-first at 390, with a desktop layout at 1440 (content width 1280).

**Figma file:** https://www.figma.com/design/9ToQZAqy6EBNsLI4ufNq59

Node links have the form `https://www.figma.com/design/9ToQZAqy6EBNsLI4ufNq59?node-id=<id with - instead of :>`. Every frame is listed in §8.

| Figma page | Contents |
|---|---|
| Cover | Cover frame (`33:5760`) |
| 1 · Foundations | Foundations board (`22:2`): colours with hex and contrast, type, spacing, radius, elevation, icons, logo, motion/a11y |
| 2 · Components | 40+ components and component sets, plus the image assets frame |
| 3 · Pages — 390 & 1440 (RTL) | 42 frames: 21 screens × 2 breakpoints, one screen per row (mobile at x=0, desktop at x=520) |
| 4 · Key states | 19 state frames |
| 5 · English LTR | Home and Product at 1440, mirrored |

All values are Figma variables or styles (collections **Primitives**, **Color**, **Spacing** and **Radius**, plus text and effect styles). Frames are built with auto-layout from component instances. The only exceptions are decorative shapes on banners and absolutely positioned overlays.

---

## 1. Tokens: `resources/css/theme.css` (`@theme`)

Keep the existing names. Only **add** the new ones below. Hex values that already exist are unchanged.

```css
@theme {
  --font-sans: 'Cairo', 'Instrument Sans', ui-sans-serif, system-ui, sans-serif;           /* unchanged: body + UI */
  --font-display: 'Baloo Bhaijaan 2', 'Cairo', ui-sans-serif, system-ui, sans-serif;     /* NEW: headings, prices, hero */

  /* brand rose: existing 50–900 unchanged */
  --color-brand-950: #3f1828;            /* NEW: announcement bar, footer body, auth backdrop deep */

  /* gold accent: existing + dark text shade */
  --color-accent: #d7b987;               /* gold/base */
  --color-accent-light: #f4e8d5;         /* gold/light (bundle media, packaging selected) */
  --color-accent-dark: #7a5a22;          /* NEW: gold text on accent-light (5.24:1) */

  /* NEW vivid supports */
  --color-pop-50: #ffe8f2;  --color-pop-100: #ffc9e0;  --color-pop-500: #e5177a;  --color-pop-600: #c80f66;  --color-pop-700: #a10b52;   /* logo magenta */
  --color-sun-100: #fff3c4; --color-sun-300: #ffd95a;  --color-sun-400: #ffc940;                                                   /* sunshine */
  --color-mint-100: #d5f5ec; --color-mint-400: #3ccfae; --color-mint-700: #0b7560;                                                /* mint */
  --color-violet-100: #ece7ff; --color-violet-400: #8b6cff; --color-violet-500: #6c4bf4; --color-violet-700: #4b2bc9;            /* violet */

  /* status (extend) */
  --color-red-50: #fce8eb;  --color-red-700: #a82d42;   /* red-500/600 #c8384f unchanged */
  --color-emerald-50: #e3f4ee;                          /* emerald-600/700 unchanged */
  --color-amber-50: #fdf1dc; --color-amber-700: #8a5a12; /* amber-500 unchanged */

  /* elevation (NEW) */
  --shadow-card: 0 1px 3px rgb(47 33 41 / .06);
  --shadow-raised: 0 6px 16px rgb(47 33 41 / .08);
  --shadow-overlay: 0 16px 40px rgb(47 33 41 / .14);
  --shadow-sticker: 0 4px 0 var(--color-brand-800);     /* playful hard shadow under primary/pop CTAs */
  --shadow-bar: 0 -4px 16px rgb(47 33 41 / .08);        /* bottom bar, sticky add-to-cart */
}
```

Neutrals stay as the plum-tinted `--color-slate-*` (50 `#fff9f7` … 950 `#2f2129`) and `white`. Radius uses the Tailwind 4 defaults: `rounded-md` 6, `lg` 8, `xl` 12, `2xl` 16, `3xl` 24, `4xl` 32, `full`. Spacing uses the Tailwind 4px grid (`--spacing`).

`red-700` and the `*-50` additions also change the admin badges (`badge-danger` and others), because they share theme.css. Those values are AA-safe there too. If the admin must stay pixel-identical, scope them to `storefront.css`.

Load the display font once in the storefront layout from Google Fonts: `family=Baloo+Bhaijaan+2:wght@600;700;800&display=swap`, preconnected next to Cairo, using only the weights listed.

### 1.1 Semantic colour roles (Figma "Color" collection → Tailwind)

| Figma variable | Value (primitive) | Use in Tailwind | Usage |
|---|---|---|---|
| bg/page | slate-50 `#fff9f7` | `bg-slate-50` | body background |
| bg/surface | white | `bg-white` | cards, panels, inputs |
| bg/subtle | slate-100 `#f7ecef` | `bg-slate-100` | search field, code prefix, table head |
| bg/brand / brand-hover / brand-strong / brand-deep | brand-600 / 700 / 800 / 950 | `bg-brand-600` … | primary CTA / hover / hero + trust band / announcement + footer |
| bg/brand-soft / brand-muted | brand-50 / 100 | `bg-brand-50` | selected cards, chips |
| bg/pop / pop-bright / pop-soft | pop-600 / 500 / 50 | `bg-pop-600` … | sale badge, flash rail, deals pill / decorative blobs only / countdown pill |
| bg/sun / sun-soft | sun-400 / 100 | `bg-sun-400` | Sun button, gift finder, countdown units, kicker |
| bg/mint / mint-strong / mint-soft | mint-400 / 700 / 100 | `bg-mint-400` | success hero, promo tile, COD tag |
| bg/violet / violet-soft | violet-500 / 100 | `bg-violet-500` | gift finder block, affiliate area |
| bg/gold / gold-soft | accent / accent-light | `bg-accent` | bundles, packaging |
| bg/ink | slate-950 | `bg-slate-950` | toast, out-of-stock badge, copyright |
| bg/success(-soft), warning(-soft), danger(-soft) | emerald-600/50, amber-500/50, red-600/50 | | status |
| text/primary, secondary, muted | slate-950, 600, 500 | `text-slate-950` … | |
| text/on-color | white | `text-white` | on brand/pop/violet/ink |
| text/brand, brand-strong, pop, gold, violet, mint | brand-600, 800, pop-600, accent-dark, violet-700, mint-700 | | coloured text |
| text/success, warning, danger, danger-strong | emerald-700, amber-700, red-600, red-700 | | |
| border/default, strong, brand, focus, danger | slate-200, 300, brand-500, pop-500, red-600 | | |

### 1.2 Contrast (WCAG 2.1, checked on the real background)

| Pair | Ratio | Result |
|---|---|---|
| white on brand-600 | 5.63 | AA |
| white on brand-800 | 9.03 | AA |
| white on pop-600 | 5.65 | AA (solid magenta buttons and badges) |
| white on pop-500 | 4.43 | **large text only**, so pop-500 is used for blobs, focus ring and decoration only |
| slate-950 on sun-400 | 9.98 | AA |
| white on violet-500 | 5.32 | AA |
| slate-950 on mint-400 | 7.83 | AA (no white text on mint-400: 2.5) |
| white on mint-700 | 5.63 | AA |
| slate-950 on slate-50 | 14.7 | AA |
| slate-600 on white | 6.82 | AA |
| slate-500 on slate-50 | 5.07 | AA (muted / placeholder) |
| brand-600 on brand-50 | 4.86 | AA |
| pop-600 on pop-50 | 4.86 | AA |
| accent-dark on accent-light | 5.24 | AA |
| mint-700 on mint-100 | 4.86 | AA |
| violet-700 on violet-100 | 7.01 | AA |
| emerald-700 on emerald-50 | 5.19 | AA |
| amber-700 on amber-50 | 5.29 | AA |
| red-700 on red-50 | 5.73 | AA |
| red-600 on white | 5.08 | AA |
| white on slate-950 | 15.3 | AA |

### 1.3 Typography

| Style | Font | Size/line (px) | Tailwind | Use |
|---|---|---|---|---|
| Display/XL | Baloo Bhaijaan 2 ExtraBold | 56/64 | `font-display text-[56px]/[64px] font-extrabold` | desktop hero headline |
| Display/L | Baloo 800 | 44/52 | `text-[44px]/[52px]` | desktop page heroes (category, brands, success) |
| Display/M | Baloo 800 | 32/40 | `text-[32px]/[40px]` | mobile hero, page H1 (cart, checkout…), big numbers |
| Heading/H1 | Baloo 700 | 28/36 | `text-[28px]/9` | rail titles desktop, product H1 mobile |
| Heading/H2 | Baloo 700 | 22/30 | `text-[22px]/[30px]` | section/panel titles, rail titles mobile |
| Heading/H3 | Cairo 700 | 18/28 | `text-lg/7 font-bold` | card titles, sub-sections |
| Heading/H4 | Cairo 700 | 16/24 | `text-base font-bold` | footer columns, mega-menu columns |
| Body/L | Cairo 400 | 16/26 | `text-base/[26px]` | descriptions, article text |
| Body/M | Cairo 400 | 14/22 | `text-sm/[22px]` | default UI text |
| Body/M Strong | Cairo 600 | 14/22 | `text-sm font-semibold` | product names, labels |
| Body/S | Cairo 400 | 12/18 | `text-xs/[18px]` | help text, meta |
| Label/L · M · S · XS | Cairo 700 | 16/24 · 14/20 · 12/16 · 11/14 | | buttons, chips, badges |
| Price/L · M | Baloo 800 | 30/36 · 18/24 | `font-display` | product page, cards |
| Price/Old | Cairo 400 | 12/16, strikethrough | `line-through` | old price |

Digits are Western (number_format). Arabic text is never letter-spaced. Use `text-start` / `text-end`, never `text-left` / `text-right`.

### 1.4 Spacing, radius, elevation

- **Spacing:** a 4px grid with tokens 0, 4, 8, 12, 16, 20, 24, 32, 40, 48, 64, 80.
  - Page gutter: 16 on mobile, 80 on desktop (`max-w-7xl` = 1280).
  - Section gap: 28 (mobile home), 20–24 (inner pages), 48 (desktop home), 32 (desktop inner).
  - Card padding: 8 (product/bundle card), 16–20 (panels), 24–32 (hero blocks).
- **Radius:** pills (`rounded-full`) for buttons, chips, badges, search, steppers and icon buttons. 12 for inputs. 16 for cards, rows and choice cards. 24 for panels, banners (mobile), sheets and modals. 32 for desktop colour blocks and hero.
- **Elevation:**
  - `shadow-card`: cards at rest. Cards use a 1px slate-200 border instead of a shadow.
  - `shadow-raised`: hover lift, glass icon buttons, gift circle.
  - `shadow-overlay`: dropdowns, mega menu, sheets, modals, toasts.
  - `shadow-sticker`: primary/pop buttons and hero CTA.
  - `shadow-bar`: bottom bar and sticky CTA.
  - Focus is a 3px pop-500 ring: `focus-visible:ring-[3px] ring-pop-500/45`.

### 1.5 Iconography

The 24px grid uses a 2px round stroke in the lucide style. The paths are the inline SVGs already in the Blade views (home, grid, gift, cart, heart, user, search, menu…), extended with truck, cash, shield, clock, tag, box, pin, copy, globe, share, upload, phone, sparkle, logout, info, alert, qr, printer, star, play, chevrons, plus, minus, check, close, filter, sort, trash and edit. They are listed in Figma on Components → "Components / Icons" (`5:2`).

- Icons use `stroke="currentColor"`. Sizes are 14 / 16 / 20 / 24 / 28.
- Decorative icons get `aria-hidden="true"`. Icon-only buttons need an `aria-label`.
- Only chevrons and arrows mirror in LTR (`rtl:` / `ltr:` variants or `-scale-x-100`). Logos, numbers, phone numbers, play icons and media controls never mirror.

---

## 2. Global layout

- **Mobile 390:**
  - Sticky header: the announcement bar (brand-950) scrolls away; the main row (menu · logo · gift · cart) and the search row stay.
  - Main area has a 16px gutter.
  - Footer.
  - Fixed bottom bar: 72px plus `env(safe-area-inset-bottom)`; main gets `pb-24`.
- **Desktop 1440:**
  - Announcement bar.
  - Header row: logo 96×80 · search (flex) · Gift-finder sun pill · language · account · wishlist · cart.
  - Brand-600 category nav with the "كل الأقسام" sun pill and the "عروض خاطفة" pop pill.
  - Main area: `max-w-7xl px-20 py-8`.
  - Footer.
- **RTL/LTR:** `<html dir>` drives everything. Use logical utilities (`ms-/me-/ps-/pe-/start-/end-`). Rows flow from the start side.
- **Breakpoints:** `<768` mobile layout, `768–1023` tablet (mobile header and bottom bar, 3-col grids), `≥1024` desktop header, no bottom bar, 4–5-col grids.

---

## 3. Swiper.js (all horizontal scrollers)

These scrollers replace `data-scroll-row` and the custom `data-slider`. Use the Swiper Element or core bundle with Navigation, Pagination, Autoplay, A11y, FreeMode and Keyboard.

General rules:

- Set `dir` from `<html>`: `rtl: document.dir === 'rtl'`, or the `dir="rtl"` attribute on the container.
- Prev/next buttons are our 44px **Slider controls** (`19:354`), passed via `navigation: { prevEl, nextEl }`.
  - In RTL, prev sits on the right and shows chevron-right; Swiper's rtl mode handles the swap.
  - Hide them below 1024px and while `isLocked`.
- Use `a11y: { prevSlideMessage: __('store.prev'), nextSlideMessage: __('store.next') }`.
- Keyboard is **scoped**, not global: every slider is built with `keyboard: { enabled: false, onlyInViewport: true }` and
  `scopeKeyboard()` in `resources/js/lib/sliders.js` enables it only while the pointer is over that slider's root or focus is inside it
  (`pointerenter` / `focusin` on, `pointerleave` / `focusout` off), and only one slider at a time (the last one entered). Swiper's keyboard
  module listens on the whole document, so with it always on one arrow key press moved every slider on screen. Arrow keys follow the reading
  direction (in RTL, ArrowLeft = next). The gallery thumbs are real buttons, so Tab + Enter also moves the gallery.
- Only hero banners autoplay. Never autoplay when `matchMedia('(prefers-reduced-motion: reduce)').matches`. Add a pause/play toggle button (WCAG 2.2.2).
- Lazy images: `loading="lazy"`. The first hero slide stays eager with `fetchpriority="high"`.

| Component | 390 (base) | ≥768 | ≥1024 | ≥1280 | Other options |
|---|---|---|---|---|---|
| **Hero banner** (`19:477`) | `slidesPerView: 1, spaceBetween: 12` | 1 | 1 | 1, `spaceBetween: 20` | `loop: true`, `autoplay: { delay: 5000, pauseOnMouseEnter: true, disableOnInteraction: false }` (off under reduced motion), `pagination: { el, clickable: true }` dots on image, navigation ≥1024, `speed: 400` |
| **Category row** (home) | `slidesPerView: 4.3, spaceBetween: 12, freeMode: true` | 6.3 | 8, spaceBetween 24 | 8 | no loop, navigation ≥1024 (arrows in the rail header), `slidesOffsetBefore/After: 16` on mobile |
| **Promo tiles** (strips/tiles) | `slidesPerView: 1.15, spaceBetween: 12` | 2.1 | 3, spaceBetween 20 | 3 | no loop; when ≤3 tiles on desktop it is a static grid (`enabled: false`) |
| **Product rail** (`x-rail`, `19:1272`) | `slidesPerView: 2.3, spaceBetween: 12, freeMode: true` | 3.3 | 4, spaceBetween 20 | 5 | `pagination: { type: 'progressbar' }` below 1024, navigation ≥1024, `slidesOffsetBefore/After: 16` mobile, `watchOverflow: true` |
| **Flash-sale rail** | same as product rail | | | | sits in a pop block; countdown via `data-countdown` |
| **Bundles on home** | grid 2-col (no Swiper) | 4-col grid | | | if more than 4 bundles, use the rail settings |
| **Brand strip** | `slidesPerView: 2.4, spaceBetween: 12, freeMode: true` | 4.4 | 6, spaceBetween 16 | 7 | navigation ≥1024 |
| **Product gallery** (`_product-gallery`) | `slidesPerView: 1, spaceBetween: 0` + thumbs Swiper `slidesPerView: 5.2, spaceBetween: 8, watchSlidesProgress: true` | same | main + vertical thumbs (`direction: 'vertical', slidesPerView: 5, spaceBetween: 12`) | same | `thumbs: { swiper }`, `pagination: { type: 'fraction' }` counter pill "1 / 5", navigation on image ≥1024, `zoom` optional; on `slideChange` pause the previous video and play the active one (keep `data-gallery-*` hooks, muted + playsinline) |
| **Packaging options** (cart/checkout) | `slidesPerView: 2.2, spaceBetween: 10` | 3 | 3 | 3 | radios stay native |
| **Testimonials / reviews** (if a reviews row is added on home) | `slidesPerView: 1.1, spaceBetween: 12` | 2 | 3, spaceBetween 20 | 3 | navigation ≥1024 |
| **Sub-category chips / tabs** | `slidesPerView: 'auto', spaceBetween: 8, freeMode: true` | auto | auto | auto | wraps instead of scrolling inside the desktop category header |

---

## 4. Components

Each heading below gives the Figma node. Each component also has a description in Figma.

### 4.1 Button (`5:308`)
- **Anatomy:** a pill with an optional leading icon at inline-start and a label.
- **Variants:**
  - Primary: brand-600, white text, sticker shadow.
  - Pop: pop-600, white, sticker.
  - Sun: sun-400, ink text.
  - Secondary: white with slate-300 border.
  - Ghost: brand text.
  - Danger: red-50 background, red-700 text.
- **Sizes:** L 52 (mobile primary CTAs, hero), M 44 (default, minimum touch target), S 36 (dense desktop only).
- **States:**
  - Hover: Primary goes to brand-700, Secondary to slate-100.
  - Focus: 3px pop ring.
  - Pressed: `translate-y-0.5` and a 2px shadow.
  - Disabled: opacity 45%, no shadow, `cursor-not-allowed`.
  - Loading: spinner replaces the icon, label stays.
- **Code:** replaces `.btn`, `.btn-primary`, `.btn-secondary`, `.btn-danger` (keep the class names and restyle them).

### 4.2 Icon button (`5:369`)
- **Shape:** a circle.
- **Styles:**
  - Soft: brand-50 with brand icon.
  - Outline: white with slate-200 border.
  - Solid: brand.
  - Glass: white with `shadow-raised`, used over images.
  - Plain.
- **Sizes:** 48 / 44 / 36.
- **Uses:** wishlist heart (`data-card-wish`, `aria-pressed`), menu, close, share, slider arrows.

### 4.3 Field (`7:138`)
- **Types:** Text / Select / Phone / Textarea.
- **States:** Default / Focus / Error / Disabled.
- **Anatomy:**
  - Label: Body/M Strong.
  - Control: 48px high, radius 12, 1px slate-300 border.
    - Focus: 2px pop-500 border plus the focus ring.
    - Error: 2px red-600 border.
  - Help or error line: 12px (`.field-error`).
- **Phone:** always `dir="ltr"`, with the `+20` shop-code prefix block on the left.
- **Select:** native `<select>` with a custom chevron at inline-end.
- **Hooks:** keep `name`, `.is-invalid`, `data-validate-url` and the AJAX error placement.

### 4.4 Search field (`7:167`) and Search suggest (`20:1340`)
- **Search field:** a tinted pill, 48 high on desktop and 44 on mobile, with a brand circle submit button at inline-end. It keeps `data-search-form`, `data-search-input` and `name=q`.
- **Suggest dropdown (`data-suggest`):**
  - Product rows: thumb, name, pop price.
  - Category chips.
  - Empty state: "لا توجد منتجات مطابقة".
  - Use the `role="listbox"` pattern with arrow-key navigation.

### 4.5 Checkbox & Radio (`7:195`), Chip (`5:446`), Choice card (`7:233`)
- **Checkbox and radio:** 22px boxes inside a 44px label row.
- **Chip:**
  - 40px pill; Selected = brand fill with a check.
  - An optional colour swatch for option values (`data-option`) and filter values.
  - Used for budget presets, gift type and sub-categories.
- **Choice card:**
  - A radio card for payment (`payment_method_id`, `prepay_method_id`) and shipping (`shipping_company_id`).
  - Selected = brand-50 fill with a 2px brand-500 border.
  - Disabled shows "غير متاح مع هذا الشحن" (`data-cod-note`).

### 4.6 Badge (`5:420`)
- **Shape:** an 11px bold pill.
- **Product types:**
  - Sale (pop): `store.off`.
  - New (violet).
  - Gift (sun).
  - COD (mint).
  - Out of stock (ink).
  - Low stock (amber).
  - Bundle (brand-soft).
  - Flash (sun).
- **Status types:** Success / Warning / Danger / Muted / Default, for order and payment status, proof status and the address default flag.
- **Placement:** on cards, badges sit at the top-start corner of the image.

### 4.7 Price (`7:277`) and Rating (`7:306`)
- **Price:**
  - Baloo 800 amount, followed by the currency symbol.
  - Sale amount in pop-600, the old price struck through, and a sun "وفّر X ج.م" pill (product page only).
  - "ابتداءً من" prefix for variable products.
  - Hooks: `data-price`, `data-old`.
- **Rating:** sun stars, slate-200 for empty stars, and a count. Add a hidden text "4.6 من 5".

### 4.8 Quantity stepper (`7:252`)
- **Sizes:** M 48, S 36.
- **Behaviour:** +/− buttons in brand-50 around the native `<input type=number min=1>` (`data-qty`). In RTL, + sits on the right.

### 4.9 Product card (`8:454`)
- **Sizes:**
  - Mobile 173 (2-col grid; 156 inside rails).
  - Desktop 240 (5 across 1280; 221 inside the 4-col catalog grid).
  - Dir RTL/LTR.
- **Types:** Default / Sale (with flash countdown) / Out of stock / Variable.
- **Anatomy:**
  - Media: square, radius 12, tinted background. Tints rotate mint / pop / sun / violet soft.
  - Badge at top-start.
  - Glass heart at top-end (`data-card-wish`, `aria-pressed`, login redirect `data-login`).
  - Name: two lines, then ellipsis.
  - Rating S.
  - Price M.
  - Flash countdown pill (`data-countdown`) and low-stock line.
  - Full-width CTA:
    - Add to cart (Primary + cart icon, `data-add-to-cart`).
    - Choose options (Secondary link).
    - Request it (Sun, `data-stock-request` opens the stock-request modal).
- **Interaction:**
  - Desktop hover: lift by -2px, `shadow-raised`, image scales to 1.04.
  - Under reduced motion there is no scaling.
  - The whole card is not a single link; the image and the name link separately, as today.

### 4.10 Bundle card (`8:527`)
- **Sizes:** Mobile 173, Desktop 300/305.
- **States:** Default / Sold out.
- **Anatomy:**
  - Gold-soft media with a collage of the components, or the bundle image.
  - Bundle badge, item count, name.
  - Bundle price with the struck `regular_total`.
  - Savings pill (`store.bundle_save`); in the Sold out state it shows `store.sold_out` instead.
- **Behaviour:** the whole card is one link.

### 4.11 Category tile (`8:538`) and Brand tile (`8:546`)
- **Category tile:**
  - Circle: an 80px image in a 4px coloured ring. Ring colours rotate brand → pop → sun → mint → violet. The fallback is the first letter on brand-50.
  - Block: a colour tile for headers and future grids.
- **Brand tile:** 144 / 164 / 197 wide.
  - Logo on a tinted panel; the initial letter in a white circle when there is no logo.
  - Name and `sellable_count`.

### 4.12 Hero banner (`19:477`) and Promo tile (`19:526`)
- **Hero banner (Mobile 358×440, Desktop 1280×440):**
  - Colour block (brand-800, sun, pop, violet or mint).
  - Large blobs, a ring and a sparkle.
  - Product cut-out in a white-ringed circle.
  - Kicker pill, display headline, subline, one CTA.
  - Dots on the image; arrows on desktop.
  - CMS banners: the uploaded image fills the slide. The admin needs a **mobile crop (358:440)** and a **desktop crop (1280:440)**; the Canva step produces both.
  - `alt` = banner title.
- **Promo tile (Mobile 300×150, Desktop 413×180):**
  - Colour block, product circle, headline, and a "تسوق الآن ←" link.
  - The whole tile is the link.

### 4.13 Gift finder CTA (`19:558`)
A violet block with a sun gift badge, `gift_finder.cta` / `gift_finder.intro`, and a Sun button `gift_finder.find` (`data-home-gift-finder`).

### 4.14 Rail header (`19:639`) and Rail (`19:1272`)
- **Rail header:** h2 in the display font, "عرض الكل" with a chevron in the reading direction, and arrows on desktop.
- **Flash type:**
  - Sun bolt icon.
  - Countdown units (ساعة/دقيقة/ثانية) in sun boxes, with `aria-live="off"`.
  - A full-bleed pop block on mobile and a radius-32 pop block on desktop.

### 4.15 Breadcrumbs (`7:315`), Pagination (`7:332`), Tabs (`7:352`)
- **Breadcrumbs:** 12px with chevrons in the reading direction. The last crumb has `aria-current="page"`. They scroll horizontally on mobile.
- **Pagination:** 44px circles; the current page is brand-filled. Keep `data-page-link`.
- **Tabs:**
  - Underline style: product details on desktop.
  - Pill style: account and affiliate navigation. The affiliate `_nav` uses a violet pill style.

### 4.16 Accordion (`21:1724`)
A `<details>/<summary>` with plus/minus at inline-end. Used for:
- Filters on mobile.
- Gift finder "خيارات أكثر".
- Affiliate variant links.

### 4.17 Empty state (`21:1787`)
- **Types:** Cart / Search / Wishlist / Orders / Gift.
- **Anatomy:** a 120px tinted blob with a 52px icon, an H2, one line of text, and one CTA.

### 4.18 Toast (`21:1807`)
A toastr skin: radius 16 and `shadow-overlay`.
- **Types:** Success (emerald, with a "عرض السلة" sun link), Error (red, `role=alert`), Info (ink).
- **Placement:**
  - Mobile: bottom-centre, 16px above the bottom bar.
  - Desktop: top-end.
- **Timing:** auto-dismiss after 4s.

### 4.19 Modal (`21:1844`)
- **Desktop:** 448–520 wide, radius 24, black/50 scrim.
- **Mobile:** a bottom sheet with a top radius of 24, a drag handle, and the close button at inline-end.
- **Uses:**
  - Stock request (`data-sr-modal`).
  - Address modal (`#address-modal`).
  - Home popup (`data-popup`).
- **Accessibility:** `role=dialog` with `aria-modal`, a focus trap, and Esc to close.

### 4.20 Header (`20:1319`)
Variants: Desktop RTL, Desktop RTL with the mega menu open, Desktop LTR, Mobile, and Mobile while searching. See §2.
- **Mega menu:** a full-width white panel with `shadow-overlay`. It has four sub-category columns (H4 plus links) and a pop promo tile. It opens on hover and focus-within, closes on Esc, and uses `aria-expanded` on the root link.
- **Account dropdown:** unchanged items (orders, wishlist, logout / login, register, affiliate).
- **Language menu:** unchanged.

### 4.21 Mini cart (`20:1377`)
A 340px dropdown containing:
- Lines: thumb, name, qty × price, remove.
- Subtotal.
- Cart (secondary) and Checkout (primary) buttons.

Hooks: `data-mini-cart`, `data-mini-subtotal`, `data-mini-cart-foot`.

### 4.22 Mobile drawer (`21:1699`)
- **Layout:** 320px (max 85vw), slides in from the start side.
- **Content:**
  - Logo and close button.
  - Violet gift-finder CTA.
  - Category tree with expandable roots (`aria-expanded`).
  - Account links and language.
- **Hooks:** `data-drawer`, `data-drawer-open`, `data-drawer-close`.

### 4.23 Footer (`21:1374`)
- **Trust band (brand-800):** fast delivery, COD, authentic, gift wrapping.
- **Body (brand-950):**
  - Logo on a white plate.
  - `seo_default_desc`.
  - Socials.
  - About column: `footer.pages` plus the blog.
  - Customer service column: orders, cart, affiliate, gift finder.
  - App badges: a "قريباً" state when there is no URL.
  - Payments: `x-payment-icon`.
- **Copyright:** an ink bar.
- **Mobile:** columns stack; 72px is reserved for the bottom bar.

### 4.24 Bottom bar (`21:1627`)
- **Items:** six (five when the gift finder is off).
- **Gift finder:** a raised 52px sun circle in the middle; pop when active.
- **Active item:** brand icon on a brand-50 pill, with `aria-current=page`.
- **Cart:** pop count badge (`data-cart-count`).
- **Categories:** opens the drawer.

### 4.25 Commerce blocks
- **Address card (`21:1878`):**
  - Radio `address_id` with `data-city`.
  - Pin icon, label and a Default badge.
  - Name · phone (`<bdi dir=ltr>`), city - address.
  - Edit (`data-address-edit`); on the account page this becomes Delete (`data-delete`).
- **Order timeline (`21:1935`):**
  - The flow PENDING → CONFIRMED → PROCESSING → SHIPPED → DELIVERED.
  - Done steps: emerald check. Current step: pop dot with a ring. Upcoming steps: grey.
  - Cancelled or returned orders show a single Danger badge instead.
  - Horizontal from 768px, vertical on mobile.
- **Order totals (`21:1971`):**
  - `<dl data-totals>`: subtotal, discount (emerald), shipping / free, packaging, tax, COD fee, payment fee, and the total in Price/L brand.
  - Optional mint block with the prepaid / COD split.
- **Cart line (`21:2005`):** Default / Unavailable states. Bundle lines are grouped under a gold "باقة: …" header.
- **Packaging option (`21:2019`):**
  - Selected: gold-soft fill with a brand check.
  - The checkbox "تغليف خاص" (`data-packaging-toggle`) reveals the options.
- **File upload (`7:366`):** a dashed brand box for `proof`. Keep `data-max-kb` and the client messages.
- **Budget range (`7:360`):** `#gf-range`; the fill grows from the start side.
- **Stat card (`21:2040`):** tones Brand / Mint / Sun / Violet, for the affiliate dashboard.
- **Review item (`21:2061`):** avatar initial, name, stars and body.

---

## 5. Pages: section order and layout

Desktop main is 1280 wide unless noted. "Two-col" means start column first (right in RTL).

| Page | 390 | 1440 |
|---|---|---|
| **Home** (`home.blade`) | Hero slider → category row (title "تسوق حسب القسم" + circles Swiper) → promo tiles Swiper → flash-sale rail (pop block) → gift-finder CTA → bundles (header + 2-col) → rails: recommended, most sold, most reviewed, category rails → brand strip (white panel + Swiper) → popup modal (state) | Same order: hero 1280×440 with arrows; categories 8 across; promo 3-up; flash rail in a rounded pop block; bundles 4-up; rails 5-up with arrows; brands 7-up |
| **Catalog** (category / search / brand / tag landing, `catalog.blade`) | Breadcrumbs → category header (pop block: H1 + count + sub-category chips) → toolbar (Filters button with count, Sort select) → active filter chips + reset → 2-col grid → pagination. Filters open as a bottom sheet (state) | Breadcrumbs → category header → two-col: filters sidebar 296 (brand accordion, tags chips, colour swatches, price from/to, Apply / Reset) + results (count, active chips, sort, 4-col grid of 221 cards, pagination). Search uses the same layout with H1 "نتائج البحث عن …" |
| **Product** (`product.blade` + `_product-gallery`) | Breadcrumbs → gallery Swiper (counter, share, badge, thumbs incl. video thumb) → info: brand chip, H1, rating, Price L + save pill, flash timer, stock + SKU/barcode, option groups (chips, colour swatches, `data-variant-status`, combo error), qty + Add to cart + wishlist, trust trio → description → reviews (score block, review list, write-review form) → related rail. Sticky add-to-cart bar (price + button) above the bottom bar | Two-col: gallery (vertical thumbs + 600 main with arrows) + info 600 → underline tabs (Description / Reviews / Shipping & returns) → description panel → reviews panel → related rail |
| **Gift finder** | Violet header (H1, intro) → form panel (budget input + range + preset chips, occasion, recipient, type chips, category, sort, "خيارات أكثر" accordion with restrictions, submit) → results area (`aria-live`) with idle empty state | Header → two-col: form 440 (sticky) + results |
| **Gift finder results** (state) | Summary chips (answers + Edit) → title + count + Share → grid of picks (card + reason chip + select checkbox) → near-budget block → bundles in budget → fixed "add selected" ink bar | Same, 5-col picks, 4-col bundles, inline bar |
| **Cart** | H1 + count → flash note (sun) → lines (bundle group header, unavailable line) → continue shopping → summary panel (packaging toggle + options Swiper, totals, Proceed to checkout L, trust line) | Two-col: lines (flex) + summary 420 (sticky) |
| **Empty cart** (state) | H1 → Cart empty state → recommended rail | same |
| **Checkout** | H1 → numbered steps: ① shipping address (address cards, "+ إضافة عنوان" dashed, notes) ② shipping method (choice cards with ETA + price) ③ payment (choice cards; COD with prepay-delivery mint block; online with fee; wallet with transfer-to + proof upload) → order summary (lines, discount code + apply + success message, packaging, totals, Place order L, policy note). Address add/edit = bottom sheet | Two-col: steps (flex) + summary 420 (sticky). Address modal is centred, 520 wide |
| **Checkout errors** (state) | Error summary alert → address step with red border + `store.address_required` → "no shipping for this city" warning → proof file-type error → invalid discount code field | — |
| **Order** (success and tracking, `order.blade`) | Mint success hero (check, H1, thank-you, order number pill with copy, bookmark note, actions: Print invoice / PDF / Continue shopping) → timeline + history → shipping and payment (status badge, prepaid/COD amounts, instructions, transfer-to, view proof, Pay now / upload-proof form) → order summary totals → items (bundle groups, returned qty) → cancel section (reason select + Danger button) | Hero → timeline (horizontal) → two-col: shipping/payment + cancel / summary + items |
| **Account** | Brand header (avatar, H1, greeting, logout) → pill tabs (Orders / Addresses / Profile) → orders list (number, date, status badge, total, chevron) + pagination → addresses (cards with Delete) + add-address form → profile form (name, email, phone, password + hint, confirmation, current password + hint, Save) + wishlist link | Header → two-col: orders + addresses / profile aside 420 |
| **Wishlist** | H1 + count → 2-col grid (hearts filled) → empty state (state) | 5-col grid |
| **Brands** | Sun header (H1 + count) → 2-col brand tiles → empty text `store.brands_empty` | 6-col tiles |
| **Bundles** | Gold header (H1 + intro) → 2-col bundle cards → `store.bundles_empty` | 4-col |
| **Bundle** | Breadcrumbs → gold collage media → info: Bundle badge, H1, price + save, description, "تحتوي هذه الباقة على" list (thumb, linked name, × qty), stock note (≤5), Add to cart (`data-add-bundle`) or sold out, trust trio | Two-col: media 600 + info |
| **Login** (`auth/login`, lite layout) | Brand-800 backdrop with blobs → card: logo, title, email, password, remember, Login L, "أو" divider, Google button (`_google-button`), register link | Card on the start side + display "عالرف" tagline on the end side |
| **Register** | Same card: name, email, phone, password, confirmation, Register L, Google, login link | same |
| **Content page template** (`page.blade` + `custom-page.blade`) | Breadcrumbs → article panel (sun icon + H1 + text) → custom page: H1 (pop) + GET filter form (price min/max, sort, Apply) + product grid + pagination | Article 880 → custom page 5-col grid |
| **Sitemap** (`sitemap.blade`) | XML only, no UI. Nothing to design | — |
| **Blog index** | Mint header (H1 + intro) → post cards (tinted cover band with icon, date, title, excerpt, "اقرأ المزيد") → pagination | 3-col cards |
| **Blog post** | Breadcrumbs → cover band (pop) → H1, date → rich text (H2, paragraph, sun quote, list) → back to blog + share | Centred 760 article column |
| **Affiliate login** | Card: violet sparkle icon, H1, email, password, remember, Login, join link | Centred card 480 |
| **Affiliate register** | Card: violet program hero + sun bonus benefit (`data-bonus-benefit`) → name, email, phone, password, confirmation → Register → login link | Centred card 560 |
| **Affiliate dashboard** | Violet nav pill (Dashboard / My links + logout) → 2×2 stat cards → 2×2 commissions (pending, approved, paid, **available** violet card with Request payout) → bonus card (rule, progress bar `role=progressbar`, earned) → bonuses table → commissions table → payouts table. Tables scroll horizontally on mobile | Stats 4-up, commissions and payouts side by side |
| **Affiliate products** | Nav → intro → link cards (product thumb, name, price, QR download, barcode, readonly URL + Copy (pop), variant links accordion) | 3-col cards |

---

## 6. Key states (Figma page 4)

- Empty cart (390, 1440)
- Search suggestions while typing (390)
- Search with no results (390, 1440)
- Gift-finder results (390, 1440)
- Checkout errors (390)
- Add-address sheet with field errors (390) and the desktop address modal
- Catalog filters sheet (390)
- Drawer open (390)
- Added-to-cart toast (390)
- Stock-request sheet (390)
- Popup banner (390)
- Wishlist empty and no orders (390)
- Mega menu open and mini cart dropdown (1440)

The order success page itself is the Order frame on page 3.

---

## 7. Motion and accessibility

- **Motion:**
  - Durations: 150ms for hover and press, 250ms for dropdowns, drawer and sheet, 400ms for slides.
  - Easing: `cubic-bezier(.2,.8,.2,1)`.
  - Under `prefers-reduced-motion: reduce`, disable:
    - Swiper autoplay; set `speed: 0`.
    - Hover scale and lift.
    - Countdown blink.
    - Smooth scrolling.
- **Contrast:** see §1.2. Never put white text on pop-500, sun or mint-400.
- **Focus:** every interactive element shows the 3px pop ring (`focus-visible`). The skip link stays.
- **Touch targets:** at least 44px. Chips are 40px high inside 44px rows; S buttons are desktop only.
- **Labels:**
  - Icon buttons need an `aria-label` (store.prev/next/close/wishlist/menu).
  - The cart count reads "السلة، 3 منتجات".
  - Countdowns get `aria-label` text, not a live region.
- **Live regions:** gift finder status, cart updates and toasts (`role=status`).
- **Forms:**
  - Labels are tied to inputs.
  - Errors are linked with `aria-describedby`; the first invalid field gets focus.
  - The phone field is `dir=ltr` with `inputmode=tel`.
- **Language and direction:** `lang` and `dir` on `<html>`. Mixed content (phones, codes, SKUs, URLs) goes in `<bdi>`.

---

## 8. Frame index (Figma node ids)

Base URL: `https://www.figma.com/design/9ToQZAqy6EBNsLI4ufNq59?node-id=` followed by the id with `:` → `-`.

**Cover / foundations**
- Cover `33:5760`
- Foundations board `22:2`

**Components** (page 2 · Components)
| Component | Node |
|---|---|
| Icons | `5:2` |
| Button | `5:308` |
| Icon button | `5:369` |
| Badge | `5:420` |
| Chip | `5:446` |
| Field | `7:138` |
| Search field | `7:167` |
| Checkbox & Radio | `7:195` |
| Choice card | `7:233` |
| Quantity stepper | `7:252` |
| Price | `7:277` |
| Rating | `7:306` |
| Breadcrumbs | `7:315` |
| Pagination | `7:332` |
| Tabs | `7:352` |
| Budget range | `7:360` |
| File upload | `7:366` |
| Product card | `8:454` |
| Bundle card | `8:527` |
| Category tile | `8:538` |
| Brand tile | `8:546` |
| Slider controls | `19:354` |
| Hero banner | `19:477` |
| Promo tile | `19:526` |
| Gift finder CTA | `19:558` |
| Rail header | `19:639` |
| Rail | `19:1272` |
| Header | `20:1319` |
| Search suggest | `20:1340` |
| Mini cart | `20:1377` |
| Footer | `21:1374` |
| Bottom bar | `21:1627` |
| Mobile drawer | `21:1699` |
| Accordion | `21:1724` |
| Empty state | `21:1787` |
| Toast | `21:1807` |
| Modal | `21:1844` |
| Address card | `21:1878` |
| Order timeline | `21:1935` |
| Order totals | `21:1971` |
| Cart line | `21:2005` |
| Packaging option | `21:2019` |
| Stat card | `21:2040` |
| Review item | `21:2061` |
| Image assets | `3:2` |

**Pages** (page 3)
| Screen | 390 | 1440 |
|---|---|---|
| Home | `23:2` | `23:1059` |
| Catalog | `24:2876` | `24:3318` |
| Product | `24:3881` | `24:4395` |
| Gift finder | `25:4691` | `25:4968` |
| Cart | `25:5240` | `25:5569` |
| Checkout | `25:5878` | `25:6255` |
| Order success & tracking | `26:6311` | `26:6655` |
| Account | `26:6992` | `26:7331` |
| Wishlist | `26:7667` | `26:7963` |
| Brands | `27:8105` | `27:8337` |
| Bundles | `27:8602` | `27:8853` |
| Bundle | `27:9176` | `27:9415` |
| Login | `28:9480` | `28:9520` |
| Register | `28:9563` | `28:9617` |
| Content page template (page · custom-page) | `28:9674` | `28:10039` |
| Blog index | `28:10444` | `28:10691` |
| Blog post | `28:10973` | `28:11184` |
| Affiliate login | `30:11013` | `30:11208` |
| Affiliate register | `30:11408` | `30:11617` |
| Affiliate dashboard | `30:11831` | `30:12134` |
| Affiliate products & links | `30:12437` | `30:12728` |

**Key states** (page 4)
| State | Node |
|---|---|
| Empty cart 390 | `31:3` |
| Empty cart 1440 | `31:422` |
| Search suggestions 390 | `31:980` |
| Search no results 390 | `31:1100` |
| Search no results 1440 | `31:1202` |
| Gift finder results 390 | `31:1480` |
| Gift finder results 1440 | `31:1832` |
| Checkout errors 390 | `32:2080` |
| Add address sheet 390 | `32:2147` |
| Add address modal 1440 | `32:2269` |
| Catalog filters sheet 390 | `32:2442` |
| Mobile drawer open 390 | `32:2570` |
| Added to cart toast 390 | `32:2710` |
| Stock request modal 390 | `32:2821` |
| Popup banner 390 | `32:2923` |
| Wishlist empty 390 | `32:2994` |
| No orders yet 390 | `32:3111` |
| Mega menu open 1440 | `32:3228` |
| Mini cart dropdown 1440 | `32:3435` |

**English LTR** (page 5)
- Home 1440 EN `33:3`
- Product 1440 EN `33:3369`

PNG previews of the main frames are saved outside the repo, in the design session's scratchpad folder (`…/scratchpad/design/*.png`: cover, foundations, product card set, home 390/1440, product 390/1440, catalog, cart, checkout, gift finder, order, gift results, home EN).

---

## 9. Notes for the frontend rounds

- Product photos in Figma are the demo seed images from `public/media` (they contain English placeholder text). The real catalogue images replace them, so keep the square media and the tinted background.
- Hero, promo and popup art is direction only. The Canva step delivers the real banners in the palette, in the two crops given in §4.12.
- This is a visual refactor only. Keep every `data-*` hook, form `name`, route, tracking partial, JSON-LD block and the SEO head exactly as they are today.

---

## 10. Mobile app (Flutter)

The Flutter app (`app/`) gets the same bold-and-colourful look as the web storefront. Everything is on the Figma page **6 · Mobile app** (`38:2`): the **App components** board (`42:2`) at the top, then ten labelled rows of 390-wide screens. Screens are Arabic RTL with the real ARB copy, except row 10, which holds mirrored English LTR versions of Home and Product. Demo photos are the seed stock photos in `backend/database/seeders/assets/stock`, uploaded as JPEG to the frame **Mobile / Image assets** (`38:3`).

**Owner decisions (2026-11-14), applied everywhere:**
1. The bottom navigation has **five** items: الرئيسية · الأقسام · raised الهدايا button (centre) · السلة · حسابي. Wishlist (المفضلة) is **not** in the bottom bar. It is reached from the Account menu (the second row of the first group) and from the drawer (المجموعات والعروض group).
2. The top app bar shows the **logo only**. There is no "عالرف" text next to it.

Components are built on the storefront variables (`Color`, `Spacing`, `Radius`), text styles and effect styles, and they reuse the web components wherever these fit: Button `5:308`, Badge `5:420`, Chip `5:446`, Quantity stepper `7:252`, Rating `7:306`, Choice card `7:233`, Checkbox & Radio `7:195`, Field (Select) `7:138`, Address card `21:1878`, Order timeline `21:1935` (vertical), Order totals `21:1971`, Cart line `21:2005`, Packaging option `21:2019`, File upload `7:366`, Budget range `7:360`, Stat card `21:2040`, Review item `21:2061`, Brand tile `8:546` and Icons `5:2`.

### 10.1 Colours → `ThemeData`

Replace `AppColors` with the full palette from §1. Keep the hex values exactly as written there; the existing `brand` `#A8456C` stays brand-600. Use `useMaterial3: true` and set `surfaceTintColor: Colors.transparent` on cards, app bars and sheets, so the tonal overlay never tints the colours.

**`ColorScheme` (light):**

| Role | Token | Hex |
|---|---|---|
| `primary` / `onPrimary` | brand-600 / white | `#A8456C` / `#FFFFFF` |
| `primaryContainer` / `onPrimaryContainer` | brand-50 / brand-800 | `#FBEAF0` / `#78304C` |
| `secondary` / `onSecondary` | pop-600 / white | `#C80F66` / `#FFFFFF` |
| `secondaryContainer` / `onSecondaryContainer` | pop-50 / pop-700 | `#FFE8F2` / `#A10B52` |
| `tertiary` / `onTertiary` | violet-500 / white | `#6C4BF4` / `#FFFFFF` |
| `tertiaryContainer` / `onTertiaryContainer` | violet-100 / violet-700 | `#ECE7FF` / `#4B2BC9` |
| `error` / `onError` | red-600 / white | `#C8384F` / `#FFFFFF` |
| `errorContainer` / `onErrorContainer` | red-50 / red-700 | `#FCE8EB` / `#A82D42` |
| `surface` / `onSurface` | slate-50 (page) / slate-950 | `#FFF9F7` / `#2F2129` |
| `surfaceContainerLowest` | white (cards, sheets, inputs) | `#FFFFFF` |
| `surfaceContainerHighest` | slate-100 (search field, skeleton bones) | `#F7ECEF` |
| `onSurfaceVariant` | slate-600 (secondary text) | `#6A5560` |
| `outline` / `outlineVariant` | slate-300 / slate-200 | `#DFC3CF` / `#F0DDE4` |
| `inverseSurface` / `onInverseSurface` | slate-950 / white | `#2F2129` / `#FFFFFF` |
| `scrim` | black | `#000000` at 50% |

**`AppExtraColors` (`ThemeExtension`).** Extend the existing class with these slots:

| Slot | Hex | Use |
|---|---|---|
| `brandStrong` (brand-800) | `#78304C` | hero / profile header / Brand app bar / trust band |
| `brandDeep` (brand-950) | `#3F1828` | splash gradient end (optional) |
| `brandSoft` (brand-50) | `#FBEAF0` | active nav pill, soft icon circles, selected cards |
| `popBright` (pop-500) | `#E5177A` | focus ring, decorative blobs only (never as a text background) |
| `sun` / `sunSoft` | `#FFC940` / `#FFF3C4` | gift button, countdown units, kicker, Sun button, flash note |
| `mint` / `mintStrong` / `mintSoft` | `#3CCFAE` / `#0B7560` / `#D5F5EC` | success hero, COD prepay block, promo tile |
| `violet` / `violetSoft` / `violetText` | `#6C4BF4` / `#ECE7FF` / `#4B2BC9` | gift finder, affiliate |
| `gold` / `goldSoft` / `goldText` | `#D7B987` / `#F4E8D5` / `#7A5A22` | bundles, packaging |
| `ink` | `#2F2129` | snackbar Info, out-of-stock badge, add-selected bar |
| `success` / `successSoft` / `successText` | `#2F806A` / `#E3F4EE` / `#25705C` | status |
| `warning` / `warningSoft` / `warningText` | `#D99A36` / `#FDF1DC` / `#8A5A12` | status, low stock |
| `textMuted` | `#756069` (slate-500) | placeholders, meta |
| `cardTints` | `[mintSoft, popSoft #FFE8F2, sunSoft, violetSoft]` | product media background, rotated by index |
| `ringColours` | `[brand-600, pop-600, sun-400, mint-400, violet-500]` | category circle rings, rotated by index |

**Dark mode.** The app already has a theme toggle (فاتح / داكن / حسب النظام). Keep the existing dark scheme and add dark values for the new extension slots: soft fills become 12–16% tints on `#1F1519`, and the strong colours stay as they are. The Figma frames are light only.

**Contrast rules from §1.2 still apply:**
- Never put white text on pop-500, sun or mint-400.
- Text on sun and mint-400 is slate-950.

### 10.2 Typography → `TextTheme`

Bundle both fonts in `assets/fonts/`:
- `Cairo` (already present).
- `BalooBhaijaan2` 600, 700 and 800 (from Google Fonts, OFL).

Rules:
- Set `fontFamily: 'Cairo'` globally. Only the display, heading and price styles set `fontFamily: 'BalooBhaijaan2'`.
- `height` is the line height divided by the size.
- Letter spacing is 0 everywhere. Arabic is never letter-spaced.
- Digits are Western (`intl` `NumberFormat('#,##0')`). Prices read `1,250 ج.م` in Arabic and `EGP 1,250` in English.

| Figma style | Flutter role | Font / weight | Size / line (height) | Used for |
|---|---|---|---|---|
| Display/L | `displayMedium` | Baloo 800 | 44/52 (1.18) | review score on the Reviews page |
| Display/M | `displaySmall` | Baloo 800 | 32/40 (1.25) | page H1 (السلة، المفضلة), success title, step question, hero numbers |
| Heading/H1 | `headlineMedium` | Baloo 700 | 28/36 (1.29) | product / bundle name, auth card title |
| Heading/H2 | `headlineSmall` | Baloo 700 | 22/30 (1.36) | section and rail titles, sheet titles, hero headline |
| Heading/H3 | `titleLarge` | Cairo 700 | 18/28 (1.56) | app bar title, card titles, category names |
| Heading/H4 | `titleMedium` | Cairo 700 | 16/24 (1.5) | sub-sections |
| Body/L | `bodyLarge` | Cairo 400 | 16/26 (1.63) | descriptions, field values, articles |
| Body/M | `bodyMedium` | Cairo 400 | 14/22 (1.57) | default text |
| Body/M Strong | `titleSmall` | Cairo 600 | 14/22 | product names, field labels, list tile titles |
| Body/S | `bodySmall` | Cairo 400 | 12/18 (1.5) | meta, help text |
| Label/L · M · S · XS | `labelLarge` · `labelMedium` · `labelSmall` · extension `labelXs` | Cairo 700 | 16/24 · 14/20 · 12/16 · 11/14 | buttons, chips, badges, nav labels (XS) |
| Price/L · M | extension `priceL` · `priceM` | Baloo 800 | 30/36 · 18/24 | product / bundle price, card price, totals |
| Price/Old | extension `priceOld` | Cairo 400, `TextDecoration.lineThrough` | 12/16 | struck old price, muted |

The extension styles live in a `ThemeExtension<AppTextStyles>`.

### 10.3 Spacing, radii, elevation

- **Spacing (4px grid):** 4, 8, 12, 16, 20, 24, 32, 40, 48.
  - Screen gutter 16.
  - Gap between home sections 24.
  - Gap between inner-page sections 16–20.
  - Card padding 8 for product and bundle cards, 12–16 for list cards, 20 for colour blocks.
  - Grid gap 12.
- **Radii (`BorderRadius.circular`):**
  - 999 (`StadiumBorder`): buttons, chips, badges, search, nav indicator, steppers.
  - 6 / 8: checkbox, small boxes.
  - 12: inputs, product media, thumbnails.
  - 16: cards, list groups, cart lines, snackbars.
  - 20–24: colour blocks, hero slide, auth card.
  - 24 on the top corners only: sheets and bottom nav.
  - 32: onboarding art.
- **Elevation.** Use `BoxShadow`, not Material elevation:
  - `shadowCard`: no shadow; cards use a 1px `outlineVariant` border instead.
  - `shadowRaised` `0 6 16 #2F2129 @8%`: glass buttons over photos, gift circle, steps.
  - `shadowOverlay` `0 16 40 @14%`: sheets, drawer, snackbar, auth card.
  - `shadowSticker` `0 4 0 brand-800`: Primary / Pop / Sun buttons (pressed: offset 2).
  - `shadowBar` `0 -4 16 @8%`: bottom nav and sticky action bars.
- **Focus.** A 3px pop-500 ring at 45% for keyboard / switch access (`FocusableActionDetector` + `BoxDecoration`).

### 10.4 Components (board `42:2`)

Every app component is a Figma component or component set on the board. The description of each one in Figma repeats the spec below.

| Component | Node | Anatomy and sizes |
|---|---|---|
| App/Status bar | `42:17` | System UI placeholder, 44 high. Use `SystemUiOverlayStyle.dark` on light bars, `.light` on brand / colour headers (splash, auth, success, profile). |
| App/App bar | `42:84` | 56 high, white, 1px `outlineVariant` bottom border, `scrolledUnderElevation: 0`. Variants: <br>• **Home** (`42:18`): menu 44 (opens the drawer) at start · logo image 56×46 · spacer · cart 44 with a pop count badge (18 high, 2px white ring) at end. <br>• **Inner** (`42:35`): back 44 (`chevron-right` in RTL, `chevron-left` in LTR; use `BackButtonIcon`/`Directionality`) + title Cairo 18/28 bold + one action. On tab roots (Categories, Cart, Account) the back button is hidden. <br>• **Inner + actions** (`42:44`): share, wishlist toggle, cart. <br>• **Over media** (`42:65`): transparent bar over the gallery with 44 white circles and `shadowRaised`. <br>• **Brand** (`42:79`): brand-800 bar with white title for the affiliate area. |
| App/Search bar | `42:85` | 358×44 pill on slate-100. Hint "ابحث عن المنتجات" (Body/M, muted). A 36 brand circle with the search icon sits at inline-end. On Home it is a tap target that pushes `/search`. On the Search screen it becomes the app bar field: back + 44 field with a 2px pop focus border and a clear (×) button. |
| App/Bottom nav | `42:325` | 64 bar + safe area, white, top radius 24, `shadowBar`. Five equal slots: الرئيسية · الأقسام · **الهدايا** · السلة · حسابي. <br>• The centre gift is a 56 sun circle with a 4px white ring and `shadowRaised`, raised 20px above the bar. When active it turns pop-600 with a white icon. Tapping it pushes `/gift-finder`. <br>• Other items: 24 icon over an 11/14 bold label, 2 gap. The active item shows a 56×30 brand-50 pill behind the brand icon and a brand label. <br>• Cart has a pop count badge at the top-end of the icon. <br>• Build it as a custom widget in `ShellScaffold`, not `NavigationBar`. Semantics: each item is a `Semantics(selected:)` button; the cart label reads "السلة، 3 منتجات". |
| App/Product card | `43:322` | `Size=Grid` 173 wide (2 columns, gap 12) or `Size=Rail` 156 wide. `Type=Default / Sale / Out of stock / Variable`. <br>• Card: white, 1px `outlineVariant`, radius 16, padding 8, gap 6. <br>• Media: square, radius 12, rotating soft tint, photo `BoxFit.cover`. The badge sits at top-start (Badge `5:420`: خصم 26٪ / جديد / غير متوفر). A 32 white wishlist circle with `shadowRaised` sits at top-end; when filled it is a pop circle with a white heart. <br>• Name: Cairo 14/22 semibold, `maxLines: 2`, ellipsis. <br>• Rating S (`7:306`) is hidden when the product is out of stock. <br>• Price row: Price/M (pop-600 on sale), then Price/Old struck, then "ابتداءً من" before the price for variable products. <br>• Optional flash countdown pill (pop-50 / pop-600, clock 14) and a low-stock line "متبقي 3 فقط" (Label/XS, warning text). <br>• CTA: full-width 44 button. Primary "أضف إلى السلة" + cart icon, Secondary "اختر الخيارات" (opens the variant sheet), or Sun "أعلمني عند التوفر" (opens the notify sheet). <br>• Tapping the card opens the product; the image is a `Hero` with tag `product-{id}`. |
| App/Bundle card | `43:358` | 173 wide. Gold-soft media square with a collage of up to 3 photos (radius 12, 3px white ring), Bundle badge at top-start. Below: count line ("3 منتجات", gold text), 2-line name, Price/M brand + struck regular total, and a sun pill "وفّر X ج.م". The `Sold out` state shows an ink pill "غير متاح حالياً" with the photos at 50%. The whole card is one tap target to `/bundle/{slug}`. |
| App/Category circle | `43:379` | 76-wide item: a 72 solid ring (colour by index from `ringColours`) around a 64 photo circle. Label Cairo 12/16 bold, centred, 2 lines. Pack the items with 8 gaps in a horizontal `ListView` with 16 padding; about 4.6 items are visible, so the next one always peeks. Fallback without a photo: the first letter on brand-50. |
| App/Hero slide | `46:273` | 358×184, radius 24, clip. Themes: Rose (brand-800), Sun (sun-400) and Violet (violet-500), plus decorative blob and ring. <br>• A 132 photo circle with a 4px white ring sits at inline-end. <br>• The copy column (186 wide) sits at inline-start: kicker pill (Label/XS), headline H2, subline Body/S and Button S "تسوق الآن". <br>• CMS banners use the uploaded mobile crop as a full `BoxFit.cover` image and hide the copy. |
| App/Carousel dots | `46:284` | Active dot is a 20×6 brand-600 pill; inactive dots are 6×6 slate-300; gap 6. The width animates over 250ms. Use `smooth_page_indicator` `ExpandingDotsEffect` (already a dependency). The first slide is on the right in RTL. |
| App/Hero carousel | `46:285` | Slide + dots, 12 apart, inside the 16 gutter. See §10.5. |
| App/Section header | `46:329` | Default: H2 title + "عرض الكل" Label/M brand + `chevron-left` 16 (mirrors in LTR); 32 high. <br>Flash: a full-bleed pop-600 block (padding 12/16) with a white sparkle, white title, three 34-wide sun countdown units (ساعة / دقيقة / ثانية, Label/M over Label/XS) and a white link. Give the countdown a `Semantics(label:)`; do not use a live region. |
| App/Product rail | `46:330` | Header (16 gutter) + a 390-wide track of 156 cards, gap 12, start/end padding 16, then a 4px progress track (brand fill grows from the start side). See §10.5. |
| App/Trust strip | `46:497` | **Band:** brand-800, radius 24, padding 16, 2×2 items. Each item is a 40 sun icon circle + Label/S title + Body/S line, white text. Used at the end of Home. <br>**Row:** white card, radius 16, four 36 brand-50 icon circles + Label/XS captions. Used under the buy box on Product and Bundle. <br>Items: شحن سريع · الدفع عند الاستلام · منتجات أصلية 100% · تغليف هدايا مميز. |
| Buttons (reused `5:308`) | — | Sizes: L 52 for every primary screen CTA, M 44 default, S 36 only inside banners. Types: <br>• **Pop** (pop-600 `#C80F66`, white, sticker shadow): checkout-critical and promo CTAs (Pay now, gift finder next / search, copy link). <br>• **Primary** (brand-600 + sticker): add to cart, continue, save, login. <br>• **Secondary** = outline (white, 1px slate-300). <br>• **Ghost** (brand text). <br>• **Sun** (ink text): notify me, add selected, hero CTA. <br>• **Danger** (red-50 / red-700): cancel order. <br>States: pressed = translateY 2 and a 2px shadow; disabled 45% opacity with no shadow; loading = a 18 spinner replaces the icon and the label stays. Build them as `FilledButton`/`OutlinedButton` with `StadiumBorder` plus a wrapper that paints the sticker shadow. |
| Badges / chips / stepper (reused) | `5:420`, `5:446`, `7:252` | Badge: 22 high, Label/XS. Chip: 40 high pill; the selected chip is a brand fill with a check; the swatch variant has a 14 colour dot. Wrap chips with `Wrap(spacing: 8, runSpacing: 8)`; Flutter orders the runs RTL. Stepper: M 48 / S 36, with 40 brand-50 circles for − and +. |
| App/Text field | `47:536` | Label above (Body/M Strong, 6 gap). The control is 48 high, radius 12, white, with a 1px slate-300 `OutlineInputBorder`. <br>• Focus: 2px pop-500. <br>• Error: 2px red-600 plus an error row (alert 14 + Body/S red-600). <br>• Disabled: slate-100 fill at 60%. <br>Types: <br>• Text. <br>• Password: visibility toggle at inline-end. <br>• Phone: `textDirection: TextDirection.ltr`, `+20` prefix block on slate-100 at the left, `keyboardType: phone`, "اختياري" help line. <br>Select uses web Field `7:138` (`Type=Select`): it opens a searchable bottom sheet (city list with "ابحث عن مدينة"). |
| App/List tile | `47:558` | 56 high, padding 16, gap 12. A 36 brand-50 icon circle + title (Body/M Strong) + trailing `chevron-left` 20 muted. `Value` adds a secondary value before the chevron (language, theme). `Danger` uses a red-50 circle and red text with no chevron. Tiles are grouped in white radius-16 cards with 1px dividers. |
| App/Snackbar | `47:576` | Floating, 358 wide, radius 16, `shadowOverlay`, padding 16, icon 20 + message (Body/M Strong white) + action. <br>• Success: emerald-600 with a sun pill action "عرض السلة". <br>• Error: red-600 with "إعادة المحاولة". <br>• Info: ink. <br>It sits 16 above the bottom nav and shows for 4s. |
| App/State block | `47:630` | Centred in the body: 120 tinted blob + 52 icon, H2 title, one Body/M line, one button. <br>• `Empty`: cart / wishlist (heart, pop) / orders (box) / addresses. <br>• `No results`: violet. <br>• `Error`: red. <br>• `Offline`: sun. |
| App/Offline banner | `47:631` | 40 high, full-width sun bar under the app bar: globe icon + "أنت غير متصل. نعرض بيانات محفوظة." + a "إعادة المحاولة" link. |
| App/Skeleton | `47:656` | Bones use slate-100 with the same radii as the real element. Types: Product card, List row, Hero (slide + category circles). Use `shimmer` (already a dependency) with a 1.2s sweep in the reading direction. Under reduced motion the bones are static. Never show a spinner on a first load. |
| App/Bottom sheet | `47:657` | `showModalBottomSheet(isScrollControlled: true, useSafeArea: true, showDragHandle: false)`: <br>• White, top radius 24, `shadowOverlay`. <br>• 40×4 slate-300 handle. <br>• Header: H2 title + close 44 at inline-end. <br>• Content padding 16. <br>• Sticky actions row: Primary L + Secondary L, equal width. <br>• Scrim black at 50%. <br>Used for: filters, sort, variant picker, notify me, add / edit address, city picker, cancel / return order, proof source (المعرض / التقاط صورة), language. |
| App/Checkout steps | `47:716` | Four steps (العنوان · الشحن · الدفع · المراجعة; التغليف is inserted when packaging is on). Each is a 28 circle over Label/XS: done = emerald-600 + check, current = brand-600 number, upcoming = slate-100 + muted number. |
| App/Sticky action bar | `47:750` | White, padding 16, `shadowBar`, sits above the system inset. <br>• **Product**: price (Price/M, pop on sale, + old price) · 52 outline wishlist circle · Primary L "أضف إلى السلة" (flex). Out of stock: Sun L "أعلمني عند التوفر". <br>• **Cart**: الإجمالي + total · Primary L "إتمام الطلب". On the Cart tab it stacks above the bottom nav. <br>• **Checkout**: total · Primary L "متابعة" / "تأكيد الطلب". |

### 10.5 Carousels and scroll rows

All of these are horizontal `PageView`/`ListView` widgets. They follow `Directionality`: in RTL the first item sits at the right and swiping left moves forward. Do not set `reverse`.

| Row | Widget / settings | Peek | Autoplay |
|---|---|---|---|
| Hero banners | `carousel_slider` (dependency) or `PageView`: `viewportFraction: 1.0`, slide 358×184 inside 16 padding, `enableInfiniteScroll: true`, `autoPlayInterval: 5s`, `autoPlayAnimationDuration: 400ms`, `Curves` cubic(.2,.8,.2,1). Use `ExpandingDotsEffect` dots 12 below. | none (full slide) | **Yes**, only here. Pause on touch / drag. **Off** when `MediaQuery.of(context).disableAnimations` (reduced motion) or when the app is in the background. |
| Category circles | `ListView.separated`, `scrollDirection: horizontal`, item 76, separator 8, padding 16 | about 4.6 items visible, so the 5th peeks | no |
| Promo tiles | `Row` of two 173×120 tiles (radius 24) when there are ≤2 tiles; with more, use a rail with item 300 (`viewportFraction ≈ 0.8`) | about 0.2 of the next tile | no |
| Product rails (flash, recommended, best sellers, most reviewed, related, results rows) | `ListView` with item 156, gap 12, padding 16, `PageScrollPhysics`-style snapping via a custom `SnapScrollPhysics(itemExtent: 168)` or `PageView(viewportFraction: 168/390 ≈ 0.43, padEnds: false)` | **2.3 cards**: the 3rd card shows about 38px | no |
| Bundles row (gift results) / brand strip | same as product rails (bundle 173, brand tile 144) | about 2.2 | no |
| Product gallery | `PageView` full width 390×390, `PageController` linked to a 60-thumb row (gap 8, selected = 2px brand border). Video slides autoplay muted when they become active and pause when they leave (same rule as the web). | none | video only (muted, never under reduced motion) |
| Packaging options (cart) | `ListView` item 150, gap 10 | about 2.2 | no |
| Sub-category / summary chips | `ListView` or `Wrap`; chip height 40 (32 for summary chips) | natural | no |

Snapping is always to the item start edge, which is the right edge in RTL. The rail progress bar (4px, `outlineVariant` track, brand fill) shows the scroll fraction and grows from the start side.

### 10.6 Screens: section order

Frame names follow `App / <Screen> / ar`. A screen is the status bar + app bar + scrolling body + bottom nav or sticky action bar.

- **Splash** (`50:485`): brand-800 full screen, blobs, 160 white logo plate, 3-dot loader, version at the bottom.
- **Language picker** (`50:502`): logo · "اختر اللغة" (Display/M) · subtitle · two option cards (العربية selected: brand-50 + 2px brand border + check; English) · spacer · Primary L "متابعة".
- **Onboarding** (`50:533`): "تخطي" link · pop-soft art panel (radius 32) with a product circle · title Display/M · body · dots · Primary L "التالي" ("ابدأ الآن" on the last page).
- **Login** (`50:598`) / **Register** (`50:669`): brand-800 backdrop with blobs · 96 white logo circle · white auth card (radius 24, padding 20, `shadowOverlay`) holding the title H1, subtitle, fields, "نسيت كلمة المرور؟", Primary L, the "أو" divider, Secondary L "المتابعة باستخدام Google" and the switch link · "المتابعة كزائر" (white link, login only).
- **Forgot password** (`50:695`) · **Verify email** (`50:741`) · **Reset password** (`50:787`): Inner app bar · 120 tinted icon blob · lead text · fields. Verify uses six 56-high digit boxes in LTR order (focus = pop border), Primary L "تأكيد", "إعادة إرسال الرمز (0:45)" and "لاحقًا".
- **Home** (`51:623`):
  1. App bar (logo only).
  2. Search bar.
  3. Hero carousel.
  4. Categories (header "الأقسام" + circles row).
  5. Promo tiles (2-up).
  6. Flash sale (full-bleed pop block: Flash header + rail of Sale cards).
  7. Gift finder CTA (violet block: sun gift circle, "اعثر على الهدية المثالية", "أخبرنا بميزانيتك والمناسبة", white arrow circle).
  8. Bundles (header + 2 bundle cards).
  9. Rails: مقترح لك, الأكثر مبيعاً, الأكثر تقييماً.
  10. Brands strip.
  11. Trust band.
  12. Bottom nav.
  Pull to refresh. The section gap is 24.
- **Categories** (`52:1119`): Inner app bar (no back) · search · one card per root category (60 ring + photo, name H3, count, chevron). The expanded card (brand border) shows sub-category chips, with "كل …" selected first.
- **Catalog listing** (`52:1252`): Inner + actions app bar (title = category) · pop header block (Display/M title, count, sub-category chips) · toolbar ("تصفية" outline pill with a pop count badge, sort pill "ترتيب حسب: …") · active filter chips (brand-50 with ×) + "إعادة ضبط التصفية" · 2-column grid · infinite scroll loader ("جارٍ التحميل…"). The same layout serves brand, tag, custom page and search results; the header colour is pop for categories, sun for brands and violet for search.
- **Filter sheet** (`52:1527`): bottom sheet "تصفية" with sort radios, price من/إلى, brand checkboxes with counts, and the "عرض النتائج" / "إعادة ضبط" actions.
- **Search** (`52:1885`): the search app bar field (typing) · suggestions card (52 thumb, name, pop price, chevron) · "بحث عن \"…\"" · recent searches header with "مسح" + chips.
- **Search no results** (`52:1975`): "نتائج \"…\"" · State block `No results` · "قد يعجبك أيضاً" rail.
- **Product** (`54:1852`):
  1. Gallery 390×390 with the Over media app bar, counter pill "1 / 5" (ink) at the bottom-end and the sale badge at the bottom-start.
  2. Thumbs row (the video thumb has an ink scrim + play icon).
  3. Info: brand chip · H1 name · rating · Price/L + old price + sun "وفّر" pill · stock badge + flash timer pill · variant picker (labelled chip groups "الحجم: 50 مل", colour swatch chips "اللون: وردي") · quantity row · trust row.
  4. Description card (text + SKU).
  5. Reviews card: 4.6 score, 5→1 sun bars, two review items, Secondary "اكتب تقييماً", "عرض الكل".
  6. Related rail.
  7. Sticky Product bar.
  An invalid combination shows "هذه التشكيلة غير متاحة." under the chips (red Body/S) and disables the CTA.
- **Reviews** (`55:2091`): summary card (Display/L score, stars, count, bars) · review items · sticky "اكتب تقييماً" (opens a sheet with stars + text + "إرسال التقييم").
- **Notify me sheet** (`55:2560`): sheet over the product with the name, phone (LTR), note and Sun L "إرسال الطلب".
- **Brands** (`55:2234`): sun header block · 2-column brand tiles.
- **Bundles** (`55:2334`): gold header block · 2-column bundle cards (the last one sold out).
- **Bundle** (`55:2457`): gold collage media 390×300 · H1 · price row · description · "يتضمن هذا العرض" list (48 thumb, name, × qty) · low-stock line · trust row · sticky bar.
- **Gift finder** (`56:2940` budget, `56:3044` occasion, `56:3149` recipient & extras):
  - Violet header: gift circle, "مرشد الهدايا", "الخطوة n من 4", "ابدأ من جديد", a 4-segment sun progress bar and the question in Display/M.
  - Form: budget field + range + preset chips; occasion chips; recipient chips + restrictions field.
  - Actions: "رجوع" outline + Pop L "التالي" / "ابحث عن هدايا".
  - The الهدايا nav item is active.
- **Gift results** (`56:3230`): violet summary chips + "تعديل" · rows (Swiper-like rails): "اقتراحات لك (12)" (card + select checkbox + sun reason chip), "العروض المجمّعة", "أعلى قليلاً من ميزانيتك" · ink bottom bar showing the selected count and total, with Sun L "أضف المحدد (n)". Empty: State block "لم نجد هدايا".
- **Cart** (`58:2898`): title row (Display/M + count pill) · sun flash note · cart lines (`21:2005`) · gold bundle group header · unavailable line + red notice · summary card (packaging checkbox + options row, discount code + "تطبيق" + success line, Order totals with the mint COD split, secure-payment line) · sticky Cart bar stacked over the bottom nav.
- **Cart empty** (`58:3140`): State block Empty + "مقترح لك" rail.
- **Checkout · address & shipping** (`58:3306`):
  - Steps (current 1).
  - Card ① "العنوان": address cards with a radio (selected = brand border) and a dashed "+ إضافة عنوان" row.
  - Card ② "الشحن": choice cards with ETA and price; a disabled card for a city that is not covered.
  - Sticky "متابعة".
- **Checkout · payment & review** (`58:3448`):
  - Steps (current 3).
  - Card ③ "الدفع":
    - COD choice card (selected, + fee) with the mint "ادفع الشحن مقدمًا" block.
    - Wallet choice card with the "حوّل إلى" number and a Pop "نسخ".
    - Dashed proof upload (`7:366`; tapping it opens the المعرض / التقاط صورة sheet).
    - Card choice with a % fee.
  - Card ④ "المراجعة": address / shipping / payment rows with "تعديل", the notes field and totals.
  - Sticky "تأكيد الطلب".
- **Payment** (`59:3447`): WebView in a white card with a secure label; the "جارٍ تأكيد الدفع..." overlay card is shown on return.
- **Order confirmation** (`59:3482`): mint success hero (96 white check circle, "تم استلام طلبك", order number pill with a pop copy button, "سنراجع طلبك ونؤكده قريبًا.") · summary card (date, payment, status, total) · Primary L "عرض الطلب" · Secondary L "ابدأ التسوق".
- **Orders** (`60:3472`): order rows (56 thumb, number H3 + status badge, date · count, total, chevron). Status badges: قيد الانتظار warning, تم الشحن default, تم التوصيل success, ملغي danger.
- **Order detail** (`60:3567`): header card (number, status, date, payment badge, "الفاتورة" outline + Pop "ادفع الآن (60 ج.م)") · tracking card with the vertical timeline (`21:1934`) · items card · address card · proof card (status line + upload) · totals · "طلب إرجاع" outline + Danger "إلغاء الطلب" (both open sheets with reasons).
- **Account** (`60:3739`): brand-800 profile header (64 sun avatar initial, name H2, email, "البريد غير مؤكد" badge, edit circle) · groups:
  1. طلباتي, **المفضلة**, عناويني.
  2. تعديل الملف الشخصي, تغيير كلمة المرور, اللغة, المظهر.
  3. برنامج التسويق بالعمولة, حول التطبيق.
  4. تسجيل الخروج, حذف الحساب (danger).
- **Account guest** (`60:3934`): brand hero card (user circle, "أهلًا بك في عالرف", body, Sun "تسجيل الدخول" + outline "إنشاء حساب") · group: تتبع طلب, اللغة, المظهر, التسويق بالعمولة, حول التطبيق.
- **Profile** (`60:4091`): fields name, email (+ hint), phone, current password · Primary L "حفظ".
- **Addresses** (`60:4138`): address cards (default badge, edit) + dashed add row.
- **Address edit sheet** (`60:4204`): sheet "إضافة عنوان" with the label, name, phone (LTR), city select (error state shown), address line, "تعيين كعنوان افتراضي" switch, and Primary L "حفظ".
- **Wishlist** (`60:4284`): Inner app bar with back (opened from Account / drawer; the حسابي tab stays active) · title + count pill · 2-column grid with filled pop hearts.
- **Wishlist empty** (`60:4464`): State block Empty with the heart.
- **Drawer** (`62:4164`): 320 wide from the start side (right in RTL), radius 24 on the inner corners · logo + close · violet gift CTA · "المجموعات والعروض": الماركات, العروض المجمّعة, **المفضلة**, المدونة, التسويق بالعمولة, تتبع طلب · "معلومات": تواصل معنا, حول التطبيق, قيّم التطبيق, المظهر, اللغة · mint WhatsApp pill · version.
- **About** (`62:4397`) · **Blog** (`62:4466`) · **Blog post** (`62:4516`) · **Contact** (`62:4576`) · **Content page** (`62:4676`):
  - About: logo hero + text + policy links.
  - Blog: mint header + post cards (160 cover, date, H3, excerpt, "اقرأ المزيد").
  - Blog post: cover band, H1, date, rich text with H2s and a sun quote, back + share.
  - Contact: violet hero, WhatsApp pill, contact tiles.
  - Content page: one card with a sun icon, H1 and paragraphs (`SafeHtml`).
- **Affiliate** (Brand app bar everywhere):
  - Login (`63:4445`): violet program hero, fields, Primary L, outline "قدّم كمسوّق".
  - Register (`63:4490`): sun bonus benefit, fields, "قدّم كمسوّق". On submit it shows the "تم إرسال الطلب" state.
  - Dashboard (`63:4543`):
    1. "أهلا، سارة" + logout.
    2. Violet tier card with a progress bar.
    3. 2×2 stat cards (`21:2040`).
    4. Commissions card (pending warning, approved success, paid, violet "متاحة للسحب" with Sun "اطلب سحب الرصيد").
    5. Bonus card ("200 ج.م عن كل 10,000 ج.م" + mint progress).
    6. Nav tiles: المنتجات والروابط, المدفوعات, برنامج السفراء.
    7. Recent commissions list.
  - Products & links (`63:4694`): each card has the product thumb, name, price and "عمولتك"; the read-only link (always LTR) with a Pop "نسخ"; "مشاركة"; and "رمز QR", which expands a violet panel with the QR (`qr_flutter`, 168) and "حفظ الصورة". **There is no barcode anywhere in the app.**
  - Payouts (`63:4887`): violet available card + Sun L "اطلب سحب الرصيد" · payout history rows with status badges.
- **Key states:**
  - Home loading (`64:4593`) and Orders loading (`64:4674`): skeletons.
  - Error (`64:4761`).
  - Offline with cache (`64:4822`): sun banner over the cached content.
  - Offline without cache (`64:4962`).
  - Orders empty (`64:5027`).
  - Added-to-cart snackbar (`64:5090`).
  - Maintenance (`64:5284`) and Update required (`64:5303`): gate pages with a 140 blob, Display/M title, body, CTA and a WhatsApp link.
  - Catalog empty-filter, cart empty and wishlist empty are listed above.
- **English LTR** (`65:6759` Home, `65:8314` Product): mirrored previews with English copy. In Flutter nothing is drawn twice:
  - `Directionality` comes from the locale.
  - Use `EdgeInsetsDirectional`, `AlignmentDirectional` and `start`/`end` everywhere.
  - Only chevrons and back arrows mirror (`Icons.chevron_*` with `matchTextDirection: true`).
  - Prices, phone numbers, codes, URLs and the OTP boxes stay LTR (`Directionality(textDirection: TextDirection.ltr)` or `⁦…⁩`).

### 10.7 Frame index (page `38:2`)

| Row | Frames (node) |
|---|---|
| Board | App components `42:2` · Image assets `38:3` |
| 1 Start & auth | Splash `50:485` · Language picker `50:502` · Onboarding `50:533` · Login `50:598` · Register `50:669` · Forgot password `50:695` · Verify email `50:741` · Reset password `50:787` |
| 2 Shop | Home `51:623` · Categories `52:1119` · Catalog listing `52:1252` · Catalog filter sheet `52:1527` · Search `52:1885` · Search no results `52:1975` |
| 3 Product | Product `54:1852` · Reviews `55:2091` · Brands `55:2234` · Bundles `55:2334` · Bundle `55:2457` · Notify me sheet `55:2560` |
| 4 Gift finder | Budget `56:2940` · Occasion `56:3044` · Recipient & extras `56:3149` · Results `56:3230` |
| 5 Cart & checkout | Cart `58:2898` · Cart empty `58:3140` · Checkout address & shipping `58:3306` · Checkout payment & review `58:3448` · Payment `59:3447` · Order confirmation `59:3482` |
| 6 Orders & account | Orders `60:3472` · Order detail `60:3567` · Account `60:3739` · Account guest `60:3934` · Profile `60:4091` · Addresses `60:4138` · Address edit sheet `60:4204` · Wishlist `60:4284` · Wishlist empty `60:4464` |
| 7 Menu & content | Drawer `62:4164` · About `62:4397` · Blog `62:4466` · Blog post `62:4516` · Contact `62:4576` · Content page `62:4676` |
| 8 Affiliate | Login `63:4445` · Register `63:4490` · Dashboard `63:4543` · Products & links `63:4694` · Payouts `63:4887` |
| 9 Key states | Home loading `64:4593` · Orders loading `64:4674` · Error `64:4761` · Offline (cached) `64:4822` · Offline `64:4962` · Orders empty `64:5027` · Added to cart snackbar `64:5090` · Maintenance `64:5284` · Update required `64:5303` |
| 10 English LTR | Home / en `65:6759` · Product / en `65:8314` |

**Notes for the developer:**
- The Figma frames show the full scroll length. Frames with sheets, the drawer or a snackbar are clipped to 844.
- The EN frames are detached mirror copies made for review. Build the widgets once and let `Directionality` mirror them.
- The Change-password, Delete-account, Track-order dialog, Launch and Ambassador screens are not drawn. Build them from the same parts:
  - Change password: a fields + Primary L page like Reset password.
  - Delete account: a Danger L button and a red-50 warning card.
  - Track order: an `AlertDialog` with radius 24 and a text field.
  - Launch and Ambassador: card + fields like Affiliate register.
