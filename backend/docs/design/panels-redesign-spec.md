# 3alraf panels redesign v2: design handoff

Scope: Admin (`/admin`), Marketer (`/marketer`), Affiliate (`/{locale}/affiliate/*`). Arabic RTL first, English LTR second. Widths: mobile 390, tablet 768/1024, desktop 1440.
Status: Figma complete for foundations, component library, and key screens. No application code was touched.

## 1. Figma map

File: https://www.figma.com/design/9ToQZAqy6EBNsLI4ufNq59 (file key `9ToQZAqy6EBNsLI4ufNq59`). Old pages 7-10 (v1 layout + reference screens) are untouched.

| Page | Node | Link |
|---|---|---|
| 11 · Redesign v2 — Foundations | `118:135` (board `121:2`) | `?node-id=121-2` |
| 12 · Redesign v2 — Components | `118:136` (root `122:2`) | `?node-id=122-2` |
| 13 · Redesign v2 — Screens | `118:137` (root `133:2283`) | `?node-id=133-2283` |

Screen groups on page 13 (each group is a row of frames: Desktop 1440, Tablet 768, Mobile 390, plus LTR / dark where noted):

| Group | Node | Frames |
|---|---|---|
| Login + OTP | `133:2284` | Admin D (login, otp), T, M (login, otp); Marketer D, M; Affiliate D, M |
| Dashboard (Admin) | `134:2484` | D, T, M, D-LTR English, D-Dark |
| List (Admin products) | `137:3450` | D, T, M, D-LTR |
| Form (Admin product edit) | `137:4646` | D, T, M, D-LTR |
| Order detail (Admin) | `138:5014` | D, T, M, D-LTR |
| POS (Admin hero) | `138:6053` | D (rail), T, M, M cart sheet |
| Suggestions center (Marketer hero) | `139:6039` | D, T, M, D-LTR |
| Dashboard & earnings (Affiliate hero) | `139:7229` | D, T, M, D-LTR |

Component sets on page 12 (node ids): Button `123:305`, IconButton `123:403`, Link `123:471`, Heading `123:503`, Checkbox `123:549`, Radio `123:579`, Switch `123:611`, Field `124:827`, Uploader `124:939`, LangTabs `124:1041`, Tabs `124:1187`, Badge `125:395`, Chip `125:437`, Avatar `125:490`, Tooltip `125:512`, Popover `125:540`, Alert `125:694`, Toast `125:762`, Progress `125:800`, Spinner `125:814`, Skeleton `125:838`, EmptyState `125:1080`, Pagination `125:1164`, Breadcrumbs `125:1184`, Accordion `125:1214`, Stepper `125:1366`, TimelineItem `125:1422`, KeyValue `125:1452`, ListItem `125:1516`, DropdownMenu `125:1556`, Sparkline `127:404`, StatCard `127:572`, Card `127:634`, ChartCard `127:822`, ProductCard `127:870`, OrderCard `127:974`, SuggestionCard `127:1102`, BalanceCard `127:1144`, ReferralCard `127:1186`, TableCell `129:578`, TableRow `129:774`, FilterToolbar `129:876`, BulkBar `129:952`, DataTable `129:1452`, DatePicker `129:1672`, Modal `129:1874`, Drawer `129:1958`, CommandPalette `129:2098`, NavItem `131:1430`, NavGroup `131:1478`, Sidebar `131:1734`, TopBar `131:1870`, BottomNav `131:1968`, PageHeader `131:2064`, FormSection `131:2200`, OtpInput `131:2236`, AppShell `131:2984`. Icons (61 `icon/*` components) are in section `122:6`.

How to preview a panel: on any screen frame set variable mode `Panel v2 · Brand` = Admin | Marketer | Affiliate and `Panel v2 · Surface` = Light | Dark. All fills/strokes are bound to variables, so the whole frame re-themes.

## 2. Design direction (what changed vs v1)

- Calm warm-neutral canvas (`#F6F4F8`) with white cards, 1px borders and very soft shadows. Brand colour is used for the primary action, the active nav item, selected states and one hero card per page. No colour-only meaning: every status has a dot or icon plus a label.
- Pills for buttons, chips and badges. 12px inputs, 16px cards, 24-28px dialogs.
- Sidebar is a dark brand-tinted column (plum / indigo / deep mint) with one bright active pill. Page area is light.
- Cairo only. Arabic line-heights are generous (1.6-1.75).
- Soft brand gradients appear only on hero cards, login brand panel and empty-state art.

## 3. Tokens (Figma variables ↔ CSS)

Collections: `Panel v2 · Palette` (primitives, hidden from pickers), `Panel v2 · Surface` (modes Light/Dark), `Panel v2 · Brand` (modes Admin/Marketer/Affiliate), `Panel v2 · Scale` (numbers). Existing `Spacing` and `Radius` collections are reused. Figma name `panel/x` maps to CSS `--color-panel-x` (Tailwind v4 `@theme inline` → utilities `bg-panel-x`, `text-panel-x`).

### 3.1 Brand (per `data-panel`)

| Figma variable | CSS var | Admin | Marketer | Affiliate |
|---|---|---|---|---|
| `panel/accent` | `--color-panel-accent` | `#C80F66` | `#6C4BF4` | `#0B7560` |
| `panel/accent-hover` | `--color-panel-accent-hover` | `#A10B52` | `#4B2BC9` | `#095E4D` |
| `panel/accent-deep` | `--color-panel-accent-deep` | `#7D0840` | `#3A1FA3` | `#064A3C` |
| `panel/accent-soft` | `--color-panel-accent-soft` | `#FFE8F2` | `#ECE7FF` | `#D5F5EC` |
| `panel/accent-soft-dark` | `--color-panel-accent-soft-dark` | `#1A1020` | `#1B1040` | `#06312A` |
| `panel/accent-text` | `--color-panel-accent-text` | `#A10B52` | `#4B2BC9` | `#095E4D` |
| `panel/accent-text-dark` | `--color-panel-accent-text-dark` | `#FF8DBF` | `#B7A5FF` | `#7ADFC4` |
| `panel/on-accent` | `--color-panel-on-accent` | `#FFFFFF` | `#FFFFFF` | `#FFFFFF` |
| `panel/sidebar-bg` | `--color-panel-sidebar-bg` | `#1A1020` | `#1B1040` | `#06312A` |
| `panel/sidebar-text` | `--color-panel-sidebar-text` | `#E1DDE8` | `#ECE7FF` | `#D5F5EC` |
| `panel/sidebar-muted` | `--color-panel-sidebar-muted` | `#9D97AC` | `#B7A5FF` | `#7ADFC4` |
| `panel/sidebar-active-bg` | `--color-panel-sidebar-active-bg` | `#C80F66` | `#6C4BF4` | `#0E0B14` |
| `panel/sidebar-active-text` | `--color-panel-sidebar-active-text` | `#FFFFFF` | `#FFFFFF` | `#FFFFFF` |
| `panel/stripe` | `--color-panel-stripe` | `#FFC940` | `#B7A5FF` | `#FFC940` |

Contrast: accent on white is 5.6:1 (Admin), 5.2:1 (Marketer), 5.9:1 (Affiliate). White on accent passes AA for 14px bold. `accent-text` on `accent-soft` passes AA.

### 3.2 Surface (Light / Dark)

| Figma variable | CSS var | Light | Dark |
|---|---|---|---|
| `panel/bg` | `--color-panel-bg` | `#F6F4F8` | `#0E0B14` |
| `panel/surface` | `--color-panel-surface` | `#FFFFFF` | `#171420` |
| `panel/surface-raised` | `--color-panel-surface-raised` | `#FFFFFF` | `#272332` |
| `panel/surface-sunken` | `--color-panel-surface-sunken` | `#EEEBF2` | `#0E0B14` |
| `panel/border` | `--color-panel-border` | `#E1DDE8` | `#3B3649` |
| `panel/border-strong` | `--color-panel-border-strong` | `#CBC6D6` | `#544E65` |
| `panel/text` | `--color-panel-text` | `#171420` | `#F6F4F8` |
| `panel/text-muted` | `--color-panel-text-muted` | `#544E65` (8:1) | `#CBC6D6` |
| `panel/text-subtle` | `--color-panel-text-subtle` | `#716B82` (4.9:1) | `#9D97AC` |
| `panel/overlay` | `--color-panel-overlay` | `#0E0B14` (use at 50%) | same |
| `panel/success-bg / -fg / -solid` | `--color-panel-success-*` | `#E7F7EE / #0F6B3A / #15803D` | `#0B2A1B / #86E0AD / #15803D` |
| `panel/warning-bg / -fg / -solid` | `--color-panel-warning-*` | `#FFF4DB / #8A5300 / #D97706` | `#2E1D05 / #FFD07A / #D97706` |
| `panel/danger-bg / -fg / -solid` | `--color-panel-danger-*` | `#FEECEC / #B42318 / #DC2626` | `#321010 / #FFA3A3 / #DC2626` |
| `panel/info-bg / -fg / -solid` | `--color-panel-info-*` | `#E8F1FF / #1D4FB8 / #2563EB` | `#0E1E3F / #9EC2FF / #2563EB` |
| `panel/chart-2 .. chart-5` | `--color-panel-chart-2..5` | `#2563EB #D97706 #15803D #9D97AC` | `#9EC2FF #FFD07A #86E0AD #716B82` |

Chart series 1 is always `--color-panel-accent`. Solid text on `warning-solid` uses `--color-panel-text`, not white.

Palette primitives (`palette/n/0..950`, `rose|violet|mint|sun|green|amber|red|blue`) are in the Figma Palette collection with CSS names `--palette-n-900` etc. Code should alias semantic tokens to them.

Migration from current `panel.css`: `--color-panel` → `-accent`, `-hover` → `-accent-hover`, `-deep` → `-accent-deep`, `-soft` → `-accent-soft`, `-text` → `-accent-text`, `-sidebar` → `-sidebar-bg`, `-active` → `-sidebar-active-bg`, `-stripe` stays. Keep the old names as aliases until every view is migrated. Dark mode: `[data-theme='dark']` (and `prefers-color-scheme` as default) overrides the Surface group only; in dark use `accent-soft-dark` and `accent-text-dark` for soft fills/text.

### 3.3 Scale

| Token | Value | CSS |
|---|---|---|
| Type (Cairo) | display 40/48 bold · h1 28/38 bold · h2 22/32 bold · h3 18/28 semibold · h4 16/26 semibold · body-lg 16/28 · body 14/24 · body-strong 14/24 semibold · small 13/22 · caption 12/20 · caption-strong 12/20 semibold · overline 11/18 bold · button 14/20 bold · stat 32/40 bold · num 20/28 bold | text styles `panel/*` → `.text-panel-*` utilities or `--text-*` |
| Spacing | 4px grid: `space/1..20` = 4, 8, 12, 16, 20, 24, 32, 40, 48, 64, 80 | Tailwind spacing |
| Radius | sm 4, md 8 (checkbox 6), lg 8, xl 12 (inputs, rows), 2xl 16 (cards), 3xl 24 (dialogs, sheets), full | `--radius-*` |
| Elevation | xs (cards), sm (primary button), md (floating add, popovers), lg (dropdown, toast, save bar), overlay (modal) | `--shadow-panel-xs/sm/md/lg/overlay` |
| Motion | fast 120ms (hover, press, focus), base 200ms (tabs, dropdown, toast in), slow 320ms (drawer, sheet, modal); easing `cubic-bezier(.2,.8,.2,1)`; honour `prefers-reduced-motion` | `--duration-fast/base/slow` |
| Sizes | touch 44, sidebar 264, rail 72, topbar 64 (mobile 56), bottom nav 64, content max 1280 | `--size-*` |
| Breakpoints | sm 640, md 768, lg 1024, xl 1280, 2xl 1440 | Tailwind defaults |

Shadow values: xs `0 1 2 rgb(23 15 41/.06)`; sm `0 1 3 .08 + 0 1 2 .04`; md `0 4 12 .08 + 0 2 4 .04`; lg `0 12 28 .14 + 0 4 8 .06`; overlay `0 24 64 .28`.

## 4. Responsive rules (global)

| Range | Shell | Content padding | Grids |
|---|---|---|---|
| < 768 (mobile 390) | top bar 56 + bottom nav 64 (Staff 5 slots incl. centre quick action; Affiliate 4 tabs); full menu in a 300px drawer from the start edge | 16 | 1 column; stat cards 2-up compact |
| 768-1279 (tablet) | 72px icon rail + top bar 64 | 24 | 2 columns; side columns drop below main |
| ≥ 1280 (desktop) | 264 sidebar + top bar 64 | 24, content max 1280 | 12-col feel: 4 stat cards, 2/3 + 1/3 splits |

Rules that apply to every page:
1. Tables become card lists below 768 (`DataTable` → `OrderCard`/`ProductCard` mobile). Tablet keeps the table but hides low-priority columns (SKU, secondary dates) and swaps the toolbar to its mobile form.
2. Modals become bottom sheets below 768; side drawers become bottom sheets below 768.
3. Page header: breadcrumbs collapse to a back chevron on mobile; the primary action becomes a full-width 52px button under the title; secondary actions go to a kebab.
4. Forms: description column + 2-col grid on desktop, single column below 1024. Save bar is sticky at the bottom on all sizes (mobile: two stacked/full-width buttons).
5. Bulk bar is sticky bottom-centre on desktop, full-width above the bottom nav on mobile (icons only).
6. Charts: min 280px wide; below that show the number + sparkline only. Donut is replaced by a ranked list on mobile.
7. POS: rail sidebar on desktop and tablet to maximise space; tablet = products + 320px cart; mobile = product list + sticky cart bar that opens the cart sheet.
8. LTR is a true mirror: sidebar left, chevrons/arrows flip, progress and sliders fill from the start edge. Numerals, URLs, phone numbers, SKUs and OTP digits stay LTR inside RTL (`dir="ltr"` spans, `unicode-bidi: isolate`).

## 5. Interaction and accessibility

- Touch targets ≥ 44×44 on mobile (buttons MD/LG, icon buttons MD, checkbox/radio rows 44 high, list rows 64, nav items 44, bottom-nav tabs 64). `SM` buttons/icon buttons (36) are desktop-only.
- Focus: 2px ring in `--color-panel-accent` with 2px offset (`:focus-visible` only); inputs get a 2px accent border plus a 4px `accent-soft` halo. Never remove outlines.
- Contrast: body text ≥ 4.5:1, large text/icons ≥ 3:1 (verified for light; dark uses lighter `*-fg` tokens). Disabled controls at 45-50% opacity are exempt but must keep a visible label.
- Colour is never the only signal: badges carry text (+ dot), errors carry an icon + message, required fields carry text.
- Keyboard: Ctrl/⌘+K opens the command palette; ↑↓ Enter Esc inside lists/menus; Esc closes modal/drawer/popover/dropdown; focus is trapped in modal/drawer and restored to the trigger on close; Tab order follows reading order (RTL: right to left).
- ARIA: `role="dialog" aria-modal`, `aria-live="polite"` toast, `role="alert"` for error alerts, `aria-current="page"` on active nav, `aria-sort` on sortable headers, `aria-expanded` on accordions/nav groups, tooltips `role="tooltip"` linked by `aria-describedby`, icon-only buttons require `aria-label`.
- RTL mirroring: mirror directional icons (chevrons, back/forward, arrows, external-link) and layout order; do not mirror logos, play/media icons, charts' value axis, phone/URL/number text, or checkmarks. Chart time axis runs right-to-left in RTL (the design mirrors the plot; legends read right-to-left).
- Motion: ≤ 200ms for micro-interactions; skeleton shimmer 1.2s; `prefers-reduced-motion` disables shimmer and slide transitions (use fade).
- Forms: label above control, helper below, error replaces helper (icon + red text + 2px red border); validate on blur, summarise on submit; keep the save bar visible and show "unsaved changes".
- Toasts: 4s, bottom-centre on mobile and top-end on desktop; offer Undo when the action is reversible; errors persist until dismissed.

## 6. Information architecture (nav groups)

### Admin (sidebar 264; 4 shortcuts + 10 groups; one group open at a time)
Shortcuts: Dashboard, POS, Orders (count), Reports.
Groups: Sales & customers (Customers, Returns, Reviews) · Catalog (Products, Categories, Brands, Attributes, Tags, Product lists) · Stock & purchasing (Stock, Adjustments, Transfers, Requests, Purchases, Suppliers) · Offers & marketing (Coupons, Flash sales, Bundles, Banners, Sliders) · Affiliates (Partners, Tiers, Payouts) · Content (Blog, Static pages, Custom pages, Languages) · Finance (Expenses, Expense categories, Payment methods) · Shipping & branches (Carriers, Cities, Branches, Packaging) · Team & access (Admins, Roles, Activity) · Settings (General, Health, Integrations).
All 51 existing links keep their permissions and badges; this only regroups the current order.

### Marketer (sidebar 264)
Shortcuts: Overview, Suggestions (count of pending), Campaigns.
Groups: AI & insights (Gift finder, Performance, Analytics) · Content (Blog, Banners, Sliders, Media) · Offers (Coupons, Flash sales, Bundles) · SEO & pages (SEO, Static pages, Custom pages) · Affiliates (Partners, Tiers, Payouts) · Owner tools, visible to `marketer-owner` only (Tokens, API log, Integration clients, Accounts).

### Affiliate (sidebar 264 desktop · rail 72 tablet · bottom nav 4 tabs mobile)
Sidebar: Dashboard, My links, Products, Earnings, Payouts, Account (flat).
Bottom nav (mobile): Home, Links, Earnings, Account. Products and Payouts move into Links/Earnings sub-tabs on mobile. Locale switch lives in the top bar and the account page.

## 7. Component inventory (Figma → Blade)

Convention: all shared components live in `resources/views/components/ui/*` as `<x-ui.name>`. "Exists" means a Blade component exists today under another name (path given); it should be moved or aliased to the `x-ui.*` name and restyled to the new tokens. Props use camelCase in Blade (`:variant="..."`). Variants map to Figma property values (lower-cased).

Best-option rationale is one line per pattern.

### 7.1 Actions and text

| Figma | Blade | Props / slots | Variants | Status | Why this option |
|---|---|---|---|---|---|
| Button | `x-ui.button` | `variant`, `size`, `href`, `loading`, `icon`, `iconPosition`; slot default | variant primary/secondary/ghost/danger/sun · size sm/md/lg · states default/hover/disabled/loading | Exists `x-button` (restyle) | Pill + solid accent is the clearest primary; one primary per view; `sun` only for payout. |
| IconButton | `x-ui.icon-button` | `icon`, `label` (aria), `variant`, `size`, `badge` | ghost/soft/filled/outline · sm/md | New | Square-round keeps 44px targets in dense rows. |
| Link | `x-ui.link` | `href`, `style`, `external` | inline/standalone/subtle | New | Underline in body copy keeps links distinguishable without colour. |
| Heading | `x-ui.heading` | `level`, `eyebrow`, slot subtitle | h1/h2/h3 | New | Single source for type scale. |
| Icon | `x-ui.icon` | `name`, `class` (size) | 61 icons, 24 grid, 1.8 stroke | New (replaces inline SVG paths in layouts) | One registry avoids copy-pasted paths. |

### 7.2 Forms

| Figma | Blade | Props / slots | Variants | Status | Why |
|---|---|---|---|---|---|
| Field | `x-ui.field` (wrapper) + `x-ui.input` / `x-ui.number` / `x-ui.phone` / `x-ui.password` / `x-ui.search` / `x-ui.textarea` / `x-ui.select` / `x-ui.multiselect` / `x-ui.date` | `label`, `name`, `help`, `error`, `required`, `disabled`, `value`; `x-ui.select` takes `options`, `searchable`; `x-ui.multiselect` wraps Tom Select | Type × state default/focus/error/disabled | Exists `x-input`, `x-textarea`, `x-select`, `x-phone-input` (merge under the wrapper) | Label above, helper below is the most scannable pattern in RTL; 44px control height everywhere. |
| Checkbox | `x-ui.checkbox` | `label`, `checked`, `indeterminate` | unchecked/checked/indeterminate/disabled | Exists `x-checkbox` | Indeterminate needed for bulk-select header. |
| Radio | `x-ui.radio` / `x-ui.radio-group` | `options` | off/on/disabled | New | Use only for ≤ 5 options; otherwise Select. |
| Switch | `x-ui.switch` | `label`, `checked` | off/on/disabled | Exists `x-ui.switch` | Instant settings; thumb mirrors in RTL. |
| Uploader | `x-ui.uploader` (dropzone, single) · `x-ui.image-picker` (grid, multi, reorder, main = first) | `name`, `accept`, `max`, `multiple`, `value`; slot hint | dropzone default/dragover/uploading/error · imagegrid | Exists `x-image-dropzone` (extend with grid and states) | Drag-and-drop plus visible "browse" and thumbs with reorder is the proven pattern; first image = main avoids a separate flag. |
| LangTabs | `x-ui.lang-tabs` | `locales`, `name`; slot per locale; marks locales with missing required values | AR/EN × error | Exists `x-translatable-tabs` | A segmented switch (not full tabs) keeps one field visible and shows a red dot where a translation is missing. |
| FormSection | `x-ui.form-section` | `title`, `description`; slot fields | desktop (info + 2-col) / mobile (stacked) | Exists in `x-resource-form` (extract) | Description column explains intent; stacks below 1024. |
| OtpInput | `x-ui.otp-input` | `length=6`, `error` | empty/filled/error | New (inline today in `panel.auth`) | Six boxes with auto-advance and paste beat a single field for WhatsApp codes. |
| Form actions | `x-ui.save-bar` | `dirty`; slots actions | sticky, mobile stacked | Exists `x-form-actions` (restyle) | Sticky bar with unsaved indicator prevents lost edits. |
| DatePicker | `x-ui.date-range` | `from`, `to`, `presets` | desktop panel / mobile sheet | New (flatpickr theme) | Range with presets is the fastest for reports; week starts Saturday. |

### 7.3 Feedback and overlays

| Figma | Blade | Props / slots | Variants | Status | Why |
|---|---|---|---|---|---|
| Badge | `x-ui.badge` | `tone`, `style`, `dot`, `size` | neutral/success/warning/danger/info/accent × soft/solid × dot × md/sm | Exists (class `.badge`) → component | Soft pills with dot + text keep status legible without colour alone. |
| Chip | `x-ui.chip` | `selected`, `removable`, `href` | filter/removable × default/selected | New | Chips for filters are faster than a select for ≤ 6 values. |
| Avatar | `x-ui.avatar` | `name`, `src`, `size`; `x-ui.avatar-group` | initials/image/group × sm/md/lg | New | Initials fallback avoids broken images. |
| Tooltip | `x-ui.tooltip` | `text`, `placement` | top/bottom | New | Hint only, never essential info. |
| Popover | `x-ui.popover` | slots trigger/content | one | New | For rich help or inline confirms (≤ 2 actions). |
| Alert | `x-ui.alert` | `tone`, `title`, `dismissible`, `kind` | info/success/warning/danger × inline/banner | New (replaces flash markup) | Always icon + text; banner for page-level state. |
| Toast | `x-ui.toast` + JS `toast()` | `tone`, `message`, `action` | success/danger/info | New | Non-blocking confirmation with Undo. |
| Progress | `x-ui.progress` | `value`, `kind`, `label` | bar/ring × 30/70/100 | Exists `x-ui.progress` (add ring) | Text value always shown. |
| Spinner | `x-ui.spinner` | `size` | 16/24/40 | New | Inline only (buttons, table refresh). |
| Skeleton | `x-ui.skeleton` | `kind` | text/card/tablerow/stat | New | Mirrors layout, avoids layout shift; preferred over page spinners. |
| EmptyState | `x-ui.empty-state` | `kind`, `title`, `message`, `action` | nodata/noresults/error/forbidden × desktop/mobile | Exists `x-empty-state` (restyle, add kinds) | One illustrated pattern with one primary action. |
| Modal | `x-ui.modal` | `size`, `title`, slots body/footer | confirm/form × desktop dialog/mobile sheet | Exists `x-modal` (add sheet behaviour) | Bottom sheet on mobile keeps actions in thumb reach. |
| Drawer | `x-ui.drawer` | `side=end`, `title` | one | New | Filters/quick edit without leaving the list; end side. |
| DropdownMenu | `x-ui.dropdown` | trigger slot, items | one (danger last) | Exists `x-ui.dropdown` | 40px items, danger after divider. |
| CommandPalette | `x-ui.command-palette` | `groups`, `shortcut` | desktop/mobile | Exists in `panel.shell` (`data-cmdk`) → extract | Palette beats deep menus for 50+ pages; extends current client-side filter with quick actions. |

### 7.4 Data display

| Figma | Blade | Props / slots | Variants | Status | Why |
|---|---|---|---|---|---|
| Card | `x-ui.card` (+ `.header`, `.body`, `.footer`) | `variant`, `padding` | basic/withheader/media/hero | Exists `x-card` | Hero only once per page. |
| StatCard | `x-ui.stat-card` | `label`, `value`, `trend`, `delta`, `icon`, `spark`, `size` | up/down/flat × default/compact | Exists `x-stat-card` (restyle) | Number first, delta chip second, sparkline last. |
| ChartCard | `x-ui.chart-card` | `title`, `range`, slot chart | line/bar/donut | New (wrap current Chart.js use; theme via CSS vars) | Line for trend, bar for compare, donut only for ≤ 5 shares. |
| Sparkline | `x-ui.sparkline` | `points`, `trend` | up/down | New | Tiny trend, no axes. |
| ProductCard (panel) | `x-ui.product-card` | `product`, `size` | desktop tile/mobile row | New (different from storefront `x-product-card`) | Tile for POS grid, row for lists. |
| OrderCard | `x-ui.order-card` | `order` | pending/shipped/cancelled | New | Mobile replacement for an order table row. |
| SuggestionCard | `x-ui.suggestion-card` | `suggestion`, `status` | draft/pending/approved | New (marketer) | Status + one action per card. |
| BalanceCard | `x-ui.balance-card` | `amount`, `paid`, `action` | available/empty | New (affiliate) | One hero number and one action. |
| ReferralCard | `x-ui.referral-card` | `code`, `url` | one | New (affiliate) | Copy + WhatsApp share are the top actions. |
| KeyValue | `x-ui.kv` | `label`, `value`, `kind` | text/money/status | New | Detail rows read faster than 2-col tables. |
| ListItem | `x-ui.list-item` | `title`, `subtitle`, slots lead/trail | basic/avatar/action | New | 64px tappable row. |
| TimelineItem | `x-ui.timeline-item` | `title`, `time`, `state` | done/current/upcoming | New | Order history and activity. |
| Stepper | `x-ui.stepper` | `steps`, `current` | step 1-3 | New | Multi-step import/checkout flows. |
| Accordion | `x-ui.accordion` | `title`; slot | closed/open | New | Hides advanced settings (SEO). |
| Tabs | `x-ui.tabs` | `tabs`, `active`, `style` | underline/pill | Exists in `datatable` markup (extract) | Underline for page sections, pill for list filtering with counts. |
| Breadcrumbs | `x-ui.breadcrumbs` | `items` | desktop/mobile back | Exists `x-breadcrumbs` | Collapses to back + current on mobile. |
| Pagination | `x-ui.pagination` | `paginator` | desktop/mobile | Exists in DataTables skin | Numbered + range + page size on desktop; prev/next + "page x of y" on mobile. |

### 7.5 Tables

| Figma | Blade | Props / slots | Variants | Status | Why |
|---|---|---|---|---|---|
| DataTable | `x-ui.data-table` (wraps existing `x-datatable`; keeps every `data-*` hook and id) | `columns`, `ajax`, `bulk`, `mobileCard` (partial) | desktop/mobile | Exists `x-datatable` | DataTables stays for server-side paging/sorting; skin it and add the mobile card transform (`responsive` + custom renderer). |
| TableRow | CSS in `app.css` (unlayered, as today) | n/a | header/default/hover/selected | Exists (CSS) | Rows are DataTables output; style only. |
| TableCell | column renderers `ui.cell.*` (partials) | `text`, `money`, `status`, `image-text`, `actions`, `check` | 6 kinds | New (partials/helpers) | Consistent cell kinds make every list look alike. |
| FilterToolbar | `x-ui.table-toolbar` | `search`, `filters`, slots actions | desktop/mobile | Partly in `datatable` | Search + chips + primary action; filters open a Drawer. |
| BulkBar | `x-ui.bulk-bar` | `count`, slots actions | desktop/mobile | Exists `x-ui.bulk-bar` | Inverse surface makes selection mode unmistakable. |

### 7.6 Shell and navigation

| Figma | Blade | Props / slots | Variants | Status | Why |
|---|---|---|---|---|---|
| AppShell | `x-panel.shell` | `panel`, `nav`, `user`, slots | desktop/tablet/mobile/mobiledrawer | Exists `x-panel.shell` (extend rail + bottom nav) | One shell for all three panels. |
| Sidebar | `x-panel.sidebar` | `nav`, `expanded` | expanded 264 / rail 72 / drawer 300 | Inline in shell → extract | Rail at tablet keeps space. |
| NavItem / NavGroup | `x-panel.nav-item` / `x-panel.nav-group` | `label`, `icon`, `href`, `badge`, `active` | default/hover/active · open/closed | Inline in shell → extract | One group open at a time; active group auto-open. |
| TopBar | `x-panel.topbar` | slots search/end | desktop/tablet/mobile | Inline in shell → extract | ⌘K pill + locale + theme + bell + user. |
| BottomNav | `x-panel.bottom-nav` | `items` | staff 5 / affiliate 4 | New | Thumb-reach navigation. |
| PageHeader | `x-ui.page-header` | `title`, `count`, `subtitle`, `breadcrumbs`, slot actions | desktop/mobile | Exists `x-ui.page-header` | Primary action always at the end side. |
| Auth layout | `x-panel.auth` | `step` | desktop split / tablet / mobile | Exists `x-panel.auth` (restyle) | Brand panel + form; hero strip on small screens. |

## 8. Prioritised implementation order

1. **Tokens**: add the Surface/Brand/Scale variables to `theme.css`/`panel.css` (Section 3), keep old names as aliases, add `[data-theme='dark']`. Add shadow, radius, duration tokens. Load Cairo weights 400/500/600/700.
2. **Primitives**: `x-ui.icon`, button, icon-button, badge, chip, avatar, link, heading.
3. **Forms**: field + input kinds, checkbox, radio, switch, select/multiselect skin, uploader/image-picker, lang-tabs, otp-input, save-bar.
4. **Shell**: sidebar (expanded/rail/drawer), nav-item/group, topbar, bottom-nav, command palette, page-header, breadcrumbs. Migrate the three layouts to the shell.
5. **Auth**: login + OTP for the three panels.
6. **Data display + tables**: card, stat-card, key-value, list-item, tabs, pagination, data-table skin + mobile card transform, table-toolbar, bulk-bar, empty-state, skeleton.
7. **Overlays and feedback**: modal (sheet), drawer, dropdown, alert, toast, popover, tooltip, progress.
8. **Page migrations**, in order: Admin dashboard → Admin list (products) → Admin form (product edit) → Order show → POS → Marketer suggestions → Affiliate dashboard/earnings → remaining modules reusing the same kit.
9. **Charts and pickers**: chart-card theming from CSS vars, sparkline, date-range, stepper, timeline, accordion.
10. **Dark mode QA** and RTL/LTR QA on the 8 reference pages, then roll out to the other pages.

## 9. Known limits and open questions

- Figma components use variant property `Dir=RTL|LTR`; there is no native RTL mode, so mirroring is baked into the variants. In code, rely on logical properties (`ms-*`, `ps-*`, `start-*`) and `dir`.
- Number inputs and phone rows in Figma are drawn LTR inside RTL; confirm the final bidi handling for money (`1,250 ج.م`).
- Sidebars on screens are built per panel from NavItem/NavGroup pieces (IA differs); `Sidebar` component shows the Admin IA only.
- Charts are static vector drawings; the live library (Chart.js vs ApexCharts) is not decided. Either must read colours from the CSS variables above.
- The nested plot inside `ChartCard` has fixed widths (520). Narrow layouts rebuild the plot (mobile) or leave it centred (tablet); code should size charts responsively.
- Dark mode: Brand accent and sidebar stay per panel. Confirm whether the sidebar should also lighten in dark.
- Open: keep DataTables (recommended for server-side tables) or move to a Livewire/Alpine table? Open: should Marketer and Admin share the same Affiliate pages? Open: is the Affiliate tablet layout meant to use the rail (as drawn) or the mobile bottom nav?
- Not designed: the remaining ~40 module pages (they compose these components), print styles for invoices, email/WhatsApp templates, offline/error pages (500/404), the review drawer for a single suggestion.

## 10. Frontend progress (phase 1, 2026-11-20)

Owner decisions applied: DataTables kept (skinned + mobile card transform), Chart.js kept (colours from CSS tokens), Affiliate uses the 72px rail on tablet, dark-mode sidebar stays in the panel colour. Dark mode is opt-in (top-bar toggle, remembered in `localStorage.panel_theme`), not `prefers-color-scheme`, so unmigrated pages never flip by themselves.

### Done
- **Tokens** (`resources/css/panel.css`): spec 3.1 / 3.2 / 3.3 as `--p-*` + Tailwind `--color-panel-*`, shadows `shadow-panel-*`, type `text-panel-*`, `--size-*`, `--duration-*`; per-panel brand via `data-panel`; dark via `[data-theme='dark']` (slate / `bg-white` remap so old pages stay readable). v1 names kept as aliases (`bg-panel`, `bg-panel-soft`, `bg-panel-sidebar`, `bg-panel-active`). Breaking rename done: `text-panel-text` (v1 accent text) is now `text-panel-accent-text` everywhere; `text-panel-text` is the body text token.
- **Kit** `resources/views/components/ui/*` (living guide: `/admin/_ui`, local + testing only, panel + theme switcher): icon (+ registry `App\Support\Ui\Icons`), button, icon-button, link, heading, badge, chip, avatar, avatar-group, tooltip, popover, alert, toast (+ region, JS `toast()`, toastr routed to it in panels), progress (bar, ring, legacy card), spinner, skeleton, field, input, number, password, search, textarea, select, multiselect, date, date-range (native inputs, no flatpickr), phone, checkbox, radio, radio-group, switch, uploader, image-picker, lang-tabs, form-section, otp-input, save-bar, empty-state, modal (bottom sheet < 768), drawer, dropdown, command-palette, card, stat-card, chart-card, sparkline, product-card, order-card, suggestion-card, balance-card, referral-card, kv, list-item, timeline-item, stepper, accordion, tabs, breadcrumbs, pagination, data-table, table-toolbar, bulk-bar, page-header. Old names (`x-button`, `x-card`, `x-input`, `x-select`, `x-textarea`, `x-checkbox`, `x-phone-input`, `x-modal`, `x-stat-card`, `x-datatable`, `x-form-actions`, `x-translatable-tabs`, `x-image-dropzone`, `x-affiliate.balance-card`) are thin wrappers over `x-ui.*`. Storefront `x-empty-state`, `x-breadcrumbs`, `x-product-card` are untouched.
- **Shell**: `x-panel.shell` + sidebar (264 / rail 72 with flyout groups / drawer 300), nav-item, nav-group (one group open), topbar (palette pill, locale, theme, bell, user menu), bottom-nav (staff 5 slots with centre quick action, affiliate 4 tabs), command palette (Ctrl/Cmd+K, data from nav), toast region. Admin nav moved to `App\Support\Panels\AdminNav` (4 shortcuts + 10 groups); marketer already data-driven (`MarketerNav`). Layouts: admin, marketer, affiliate, panel.
- **Auth**: `x-panel.auth` (brand side 640 / hero strip) for admin + marketer login + OTP (six-box `x-ui.otp-input`, same `code` field) and Affiliate login (moved from the storefront layout to the panel auth layout; same routes / fields).
- **Tables**: DataTables skin, mobile card transform (`data-mobile-card`, labels from column titles), filter toolbar (sheet on mobile), paging simplified on mobile, `.dt-hide-md` helper class for tablet column hiding (columns must opt in), bulk bar, ranked list instead of donut on mobile.
- **Dashboards**: Admin (`admin/dashboard` + `reports/_dashboard`, `_cards`, `_chart`, `_filter`), Marketer (`marketer/dashboard`), Affiliate (`storefront/affiliate/dashboard`, list rows instead of sideways-scrolling tables).

### Remaining (phase 2)
- Admin: list pages should pass `data-table` + `x-ui.table-toolbar` / `x-ui.page-header` explicitly (they work through the wrappers but still sit inside an `x-card`, so card-in-card); product edit / all forms to `x-ui.form-section` + `x-ui.save-bar` (sticky bar now works inside old forms but the 2-column product form is not restructured); order show (timeline, kv), POS (rail layout, cart sheet), reports pages, erp pages, settings pages, whatsapp inbox, crud views, categories tree.
- Marketer: suggestions center (use `x-ui.suggestion-card`), analytics, gift finder, media, meta ads, tokens / api log / integration clients / accounts.
- Affiliate: products / links page (uses old markup with `slate-*` classes), register page, account page (not in the product yet; bottom nav keeps Home, Links, Commissions, Payouts).
- Dashboard data gap: Figma Admin dashboard shows "recent orders" and "low stock" lists; `DashboardController` does not provide them (controllers frozen in phase 1).
- Mobile page overflow still present on pre-existing page markup: `/admin/pos`, `/admin/categories`, `/admin/audit`, `/marketer/api-tokens`.
- Replace remaining hard-coded `slate-*` / `bg-white` classes in views with panel tokens, then drop the dark-mode slate remap and the v1 colour aliases.
- Dark-mode QA and RTL / LTR QA on the 8 reference pages; `prefers-reduced-motion` already honoured.
- Pest suite could not run here (`storage/framework/testing/disks/media` permission denied for this user), so no PHP tests were executed; `tests/Feature/QaE2eErpI18nPermsTest.php` and `SystemSettingsPageTest.php` rely on `class="side-link ... active"` which the new nav items keep.

## 11. Frontend progress (phase 2, admin, 2026-11-20)

### Done
- Dashboard: `reports/_dashboard` now renders `recentOrders` (links gated by `orders.view`, "view all" gated) and `lowStockItems` (links gated by `products.view`, "view all" by `stock.view`) with `x-ui.card` + `x-ui.list-item` + `x-ui.badge`; lang keys `reports.recent_orders|low_stock_items|view_all|stock_left|no_*` (ar + en).
- All admin / access / reports / whatsapp views: legacy tags (`x-card`, `x-button`, `x-input`, `x-select`, `x-textarea`, `x-checkbox`, `x-phone-input`, `x-modal`, `x-stat-card`, `x-datatable`, `x-form-actions`, `x-translatable-tabs`, `x-image-dropzone`) replaced by their `x-ui.*` implementations (mechanical, props unchanged).
- Hard-coded `slate-*` / `bg-white` removed from every admin / access / reports / whatsapp / health view (mapped to `panel-*` tokens). `_ui` style guide untouched.
- Mobile overflow: POS grid children get `min-w-0` (+ sticky summary card above the bottom nav), categories tree wraps its actions, `#main` clips stray overflow and datatable card cells break long words (`panel.css`). Categories and audit got `x-ui.page-header`.

### Done (continued, 2026-11-20 second pass)
- `x-ui.page-header` on every admin / access / reports / whatsapp / shared-panel view (no page uses the fallback h1 any more); list-page "add" buttons moved into the header.
- Datatable pages: the extra `x-ui.card` is gone, `x-ui.data-table` now carries its own surface (md and up; plain cards on mobile). `.dt-hide-md` opted in for products (barcode, brand, category, sale price, cost), orders (payment), stock movements (branch, unit cost, note).
- Forms on `x-ui.form-section` (+ save-bar): product edit (single column of sections incl. variants / SEO / organization / media), categories, languages, roles (name + permissions matrix), settings general / system / seo sections / cod-fees / notifications, brands, tags, attributes, admins, ERP settings and journal form. ERP raw inputs use `.form-control`.
- Order show: history is `x-ui.timeline-item`, customer block is `x-ui.kv`. ERP hub uses `x-ui.empty-state`.
- Colour tokens: brand / red / emerald / amber / indigo utilities in admin views and admin JS libs (`pos`, `product-form`, `whatsapp`, `orders-form`, `image-picker`, `growth-form`, `image-search`) mapped to `panel-*`.
- Real POS mobile overflow fixed (`#pos-products` had no `grid-cols-1`, tiles clipped); the clip safety net stays.
- Crawl (Playwright, super admin, ar + en, 390 / 768 / 1440, ~60 sidebar links + create forms): all 200, no JS console errors, no horizontal overflow.

### Remaining (phase 2)
- Product edit has no tab bar (sections are stacked; media / video / social partials unchanged in structure). POS cart is a sticky summary card above the bottom nav, not a true bottom sheet. Many ERP report tables and detail pages keep their raw `<table>` / `<label>` markup (only input classes and colours migrated). Order items table in order show is still a raw table.
- DataTables built-in strings ("Search", "entries per page", "Showing ...") are not translated / skinned in ar.
- A few JS-injected colour classes remain in `product-form.js`, `pos.js`, `image-picker.js`, `image-search.js`; `gift-finder.js` / `storefront.js` (marketer / storefront) untouched.
- Dark-mode slate remap stays (marketer + affiliate, phase 3).

## 12. Frontend progress (phase 3, marketer + affiliate + leftovers, 2026-11-20)

### Done
- **Marketer panel**: suggestions centre (overview KPIs as stat cards, filter toolbar on kit fields, themes table, log, show with page-header + alerts + compare table, edit on form-section + save-bar), analytics, gift finder (index + tags), media library (+ show), meta ads (index, insights, show, form), API tokens, API log, integration clients, marketer accounts (index + form). Page-header on every page, no hard-coded slate / bg-white / brand-* left; legacy tags replaced by `x-ui.*`.
- **Affiliate**: products / links page (x-ui.card, accordion, buttons) and register page moved to `x-panel.auth` (new props `description`, `indexable`, `altRoute` write the SEO head: description, robots index, canonical, hreflang; one H1, `data-bonus-benefit` kept). Dashboard / login were phase 1. Commissions / payouts / bonuses are sections of the dashboard (there is no separate route).
- **New kit pieces** (style guide `/admin/_ui` > Tables has `x-ui.table`): `x-ui.table` (static table skin, rows become cards below 768 with `data-label`), `x-ui.tabs panels=true` (page tabs that switch `[data-ptab-panel]` panels, hash `#tab-key`, error reveal in `ajax-form.js`), `x-ui.badge` accepts legacy `badge-*` enum classes, `x-ui.save-bar` forwards `data-action`, `x-ui.field` forwards `data-edit-field`, `x-ui.form-section` grid fixed (`grid-cols-1 min-w-0`).
- **Admin leftovers**: product edit has real tabs (General / Pricing / Variants / Images + video + QR / SEO) over the same partials and field names; POS order form is a bottom sheet below 1024 with a floating cart button (count + total, `pos.js` keeps all logic); DataTables built-in strings come from `ui.datatable.*` (ar + en); order items, ERP reports / detail pages and most show pages use `x-ui.table`.
- **Cleanup**: dark-mode slate / `.bg-white` remap and the v1 colour aliases (`bg-panel`, `-soft`, `-sidebar`, `-active`, `-hover`, `-deep`) removed from `panel.css` (grep: nothing in views / js / app uses them; storefront unaffected, the remap was scoped to `[data-panel]`). DataTable link colours (`text-brand-*` in `app/DataTables`) now use the panel accent.

### Remaining
- ERP `journal-form`, `migration`, `party-ageing`, PDF / invoice tables and the product variants editor table keep raw `<table>` (forms / print output).
- Missing translation key `marketing.tokens.scope_desc.suggestions_write_new_product` (shows the key), QR image files missing on disk in this dev environment (404 on affiliate links page).
