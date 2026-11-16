# 3alraf — Admin Panel Redesign (Figma → Code) + Standalone Marketer Panel

Owner request (2026-11-15). PM / BA: Claude. Team: UI/UX Designer (Figma), Senior Software Engineer (Laravel/Blade/Tailwind).
Status: design (Track A: layout + components) done in Figma; backend split B1–B3 + marketing tools in the marketer panel coded, **tests not run yet** (single run at the end: `backend/docs/qa/admin-marketer-split-tests.md`). Affiliate panel designed (Figma page 10). B4–B6 first pass coded: panel tokens + shells for admin / marketer / affiliate, shared components, re-skinned dashboards, lists, logins, affiliate pages. Remaining: re-skin of the other admin module pages (forms, order page, POS, reports, ERP) with the same components.

---

## 0. Discovery (current state)

| Area | Finding |
|---|---|
| Stack | Laravel 13, Blade + Tailwind v4 (`resources/css/theme.css` tokens: rose `brand-*`, gold `accent`, plum-tinted `slate-*`, Cairo font), Vite, Yajra DataTables, toastr, spatie/permission, Pest + Playwright browser scripts. RTL ar/en. |
| Admin shell | `components/layouts/admin.blade.php` (174 lines): menu array of ~50 items hard-coded in the view, 8 collapsible groups, dark sidebar, header with locale, bell, logout. 41 view folders under `resources/views/admin` + `access/`. |
| Admin auth | Guard `admin` → `admins` table, phone + password + WhatsApp OTP (`AdminLoginController`, `OtpService`, `admin_otps`), `otp_exempt`, lockout, `EnsureAccountActive`, super-admin `Gate::before`. |
| Marketer panel today | `/marketer` (`routes/marketer.php`, `layouts/marketer.blade.php`, `MarketerNav`). It **uses the same `admin` guard and login**. Permissions are on guard `admin` (`MarketingPermissions`). |
| Coupling to break | (1) the marketer layout links to `admin.welcome`, `admin.logout` and `admin.locale`; (2) the admin sidebar links to `marketer.dashboard`; (3) the "Marketing tools" nav group links into **admin** modules (banners, coupons, flash sales, bundles, blog, static/custom pages, SEO, affiliates, product lists, reviews); (4) `bootstrap/app.php` redirects `/marketer` guests to `admin.login`; (5) **~20 FK columns** on marketing tables (`created_by`, `approved_by`, `applied_by`, `reviewed_by`, `uploaded_by`, `published_by`, …) point at `admins`; (6) the `marketing.panel` gate and permissions use `guard_name = admin`; (7) about 93 files reference the admin guard, including the marketing tests. |

---

## 1. Target architecture (decisions)

1. **Two separate apps in one codebase, with no cross-links.**
   - `/admin` = guard `admin` / table `admins` (unchanged identity).
   - `/marketer` = guard **`marketer`** / new table **`marketers`** / provider `marketers` / model `App\Models\Marketer`.
   - Each panel has its own login, logout, OTP flow, locale switch route, layout, nav, 403/404 pages and session guard. Neither panel shows a link to the other. Being logged into one panel does not log you into the other.
2. **`marketers` table**: id, name, email (unique, nullable), phone (unique), password, is_active, otp_exempt, last_login_at, remember_token, timestamps, soft deletes. Model uses `HasRoles` with `$guard_name = 'marketer'` and `RevokesSessionsOnLockout`; `EnsureAccountActive::GUARDS` adds `marketer`.
3. **Login**: reuse the admin login flow (phone + password + WhatsApp OTP), generalized. `AdminLoginController` already passes the guard as a variable, so it is refactored into a guard-parametrized `PanelLoginController` (or a trait) with routes `marketer.login*`. The OTP table becomes polymorphic (`authenticatable_type/_id`) **or** gets a separate `marketer_otps` table (see Q3).
4. **Permissions**: marketing permissions are re-created on guard **`marketer`** (same names). Role `marketer` (preset) plus `marketer-owner` (full: tokens, API log, Meta publish, integration clients). `marketing.panel` gate checks guard `marketer`. A `Gate::before` gives full access to `marketer-owner`. Admin-guard copies of the marketing permissions are removed by migration.
5. **Who manages marketer accounts?** A "Marketers" CRUD page inside the **marketer panel** (permission `marketers.manage`, held by `marketer-owner`) and an artisan command `marketer:create` for the first owner. The admin panel shows nothing about the marketer panel.
6. **Authorship FKs**: every marketing FK that points at `admins` is repointed to `marketers` by migration (`*_by` → `marketers.id`, renaming `created_by_admin_id` to `created_by_marketer_id`). Existing rows are mapped: each admin who ever acted in the marketer panel gets a matching `marketers` row (same name/phone/password hash) and the ids are remapped. Rows that cannot be mapped become null (the columns are already nullable).
7. **Marketing tools (banners, coupons, flash sales, bundles, blog, pages, SEO, affiliates, product lists, reviews)**: with no cross-links these cannot stay as links into admin. See Q1 for the options.
8. **Admin redesign = design system first, then the shell, then the pages.** No functional changes. Backend routes, controllers and services stay as they are; only Blade, CSS and JS change. Navigation moves out of the layout view into `App\Support\Admin\AdminNav` (mirroring `MarketerNav`) so the shell becomes data-driven.
9. **Shared UI kit**: both panels consume one set of Blade components (`x-ui.*`) and tokens generated from Figma variables. Each panel gets its own theme layer (`data-panel="admin|marketer"`) so they look like siblings but are visually distinct. Sharing components is not "linking"; it avoids two copies of every button.

---

## 2. Workstreams and phases

Sequenced so design and backend run **in parallel**: the auth split (backend) does not depend on Figma.

### Track A — Design (UI/UX Designer, Figma)
> Tools: **Figma** is the source of truth for foundations, components and screens (variables + Code Connect feed the code). Its connector is now **authorized** in this session (2026-11-15).
> **Canva** (connected) is used where it fits: the moodboard and visual direction for D0/D1, empty-state, login and dashboard illustrations, presentation of the design for owner sign-off, and as a fallback for screen mockups if Figma stays unavailable. Canva has no variables or components, so tokens and the component library stay in Figma (or directly in code).

| # | Deliverable | Detail |
|---|---|---|
| D0 | **UX audit** of current admin | Screenshot all 41 modules (ar + en, 1366 and 390 px) from `http://3alraf.loc`, list pain points: 50-item sidebar, no search or command palette, inconsistent page headers and filters, dense DataTables on mobile, dashboard hierarchy. |
| D1 | **Information architecture** | New sidebar grouping (≤ 8 groups, ≤ 7 items each), favourites/recents, global search (⌘K), breadcrumbs, page header pattern (title + primary action + secondary actions). Marketer IA separately. |
| D2 | **Foundations** (Figma variables) | Colour (from `theme.css`, light + dark modes, AA-checked), type scale (Cairo), spacing, radius, elevation, motion, RTL rules. Variable names match the CSS tokens 1:1 for code sync. |
| D3 | **Component library** | Button, IconButton, Input, Select (tom-select), Phone input, Textarea, Checkbox/Switch, Badge/Status, Tabs, Card, Stat card, Table (DataTable + toolbar + bulk actions + empty/loading states), Modal/Drawer, Toast, Dropdown, Pagination, Breadcrumbs, Translatable tabs, File/Image uploader, Chart card, Sidebar, Topbar. All with variants and states. |
| D4 | **Key screens** (admin) | Login + OTP, Welcome, Dashboard, generic List page, generic Form page (resource-form), Product edit (tabs, media, video, social), Order show, POS, Reports, Settings, Roles/permissions matrix, ERP hub. Desktop + mobile, ar (RTL) primary. |
| D5 | **Marketer screens** | Login + OTP, Dashboard, Analytics, Suggestions list/compare, Media library, Meta ads draft, Marketers & roles. Uses the same kit with the marketer accent. |
| D6 | **Handoff** | Dev-mode annotations, Code Connect mapping Figma components → `x-ui.*` Blade components. |

### Track B — Engineering (Senior Software Engineer)

**B1 — Marketer identity and guard (no UI dependency)**
- Migration: `marketers` table (+ `marketer_otps` or polymorphic OTP per Q3).
- `App\Models\Marketer`, provider and guard `marketer` in `config/auth.php`, `EnsureAccountActive` adds `marketer`, bootstrap guest redirect `/marketer*` → `marketer.login`, authenticated redirect → `marketer.dashboard`.
- Login: extract the guard-agnostic login/OTP logic from `AdminLoginController` into a shared base or trait; `Marketer\Auth\LoginController`; views `marketer/auth/login|otp`; throttle, lockout and audit identical to admin (`activity('auth')` with guard `marketer`).
- Locale route `marketer.locale`; middleware `marketer.locale`.
- Command `php artisan marketer:create {--owner}`.
- Tests: login, OTP, lockout, inactive, cross-guard isolation (an admin session cannot open `/marketer` and the reverse), session fixation.

**B2 — Permissions and data migration**
- Migration: create the marketing permissions on guard `marketer`, roles `marketer` and `marketer-owner`, plus `marketers.manage`.
- Migration: create a marketer for each admin who holds a marketing permission or appears in a marketing `*_by` column; copy the password hash and phone; assign the equivalent role; remap every marketing FK from `admins` to `marketers`; then drop the admin-guard marketing permissions. Reversible `down()` where practical; a DB backup is required before running it on production.
- Update `MarketingPermissions`, the `marketing.panel` gate, `Gate::before` (`marketer-owner`), the `PermissionSeeder`, `MarketingDemoSeeder`, factories.
- Models: `belongsTo(Marketer::class)` for every `*_by` relation.
- Services: replace `auth('admin')` / `Admin` type hints in `app/Services/Marketing/**`, `Marketer/*` controllers, `MarketerNav`, `SuggestionEngine`, `MetaPublisher`, `SocialMediaLibraryService`, `ThemeService`, `MarketingTokenService` with `Marketer`.
- **Admin product edit "Social media" tab** (`?tab=social`) currently writes to the social library as an admin, so it is removed from admin (the library is marketer-only).

**B3 — Decoupling**
- Remove the admin sidebar item `marketer.dashboard`, `MarketerNav::hasAdminAccess`, the "Admin panel" links, the use of `admin.logout`/`admin.locale` in the marketer layout, the 301 redirects from old admin gift-finder and integration-client URLs (or keep them as plain 404s, see Q4).
- Implement Q1 (marketing tools).
- Marketer panel: "Marketers" CRUD + "Roles" page (guard `marketer`), an audit log page filtered to guard `marketer`.
- Grep-guard test: no `route('admin.` in `resources/views/marketer/**` or `layouts/marketer`, and no `route('marketer.` in admin views.

**B4 — Design system in code** (after D2/D3)
- Pull Figma variables → `resources/css/theme.css` tokens (light + dark), `@layer components` rewritten.
- New Blade kit `resources/views/components/ui/*` (button, input, select, table, modal, drawer, tabs, card, stat, badge, page-header, toolbar, empty-state, sidebar, topbar, command-palette). Keep old component names as thin aliases until every page is migrated, then delete them.
- `App\Support\Admin\AdminNav` (data-driven nav, permission-filtered, badges), new `layouts/admin.blade.php`, mobile drawer, ⌘K search (client-side over the permitted nav items plus quick links).
- Code Connect mappings Figma → Blade.

**B5 — Admin page migration** (after D4, module by module, behaviour unchanged)
Order: auth → welcome/dashboard → orders/returns/customers/POS → catalog (products, categories, brands, tags, attributes) → stock/purchases/suppliers → marketing modules → content → finance/ERP → logistics → team/access → reports/settings. Each module is one PR-sized step: views only, `npm run build`, its Pest tests and a browser screenshot diff (ar/en, 1366/390).

**B6 — Marketer panel re-skin** (after D5) with the same kit and the marketer theme.

### Track C — QA / QC
- Baseline before any change: `php artisan test` (known 8 pre-existing failures) and browser scripts `smoke`, `marketer`, `customer-cycle`.
- After B1–B3: full suite plus new isolation tests; `tests/Browser/marketer.mjs` updated to the marketer login.
- After each B5 module: visual check ar/en desktop/mobile; accessibility (contrast, keyboard, focus, RTL mirroring).
- Final: full customer cycle, admin cycle, marketer cycle; result log in `backend/docs/qa/admin-redesign.md`.

### Docs
`docs/PROGRESS.md` entries, `backend/docs/design/ADMIN_DESIGN_SYSTEM.md` (tokens, components, usage), `backend/docs/marketing/MARKETER_PANEL.md` updated (guard, accounts, roles), `CALCULATIONS.md` untouched (no money logic changes).

---

### Track A status (2026-11-15)
Built inside the existing storefront design file so both panels share one design system:
https://www.figma.com/design/9ToQZAqy6EBNsLI4ufNq59 (pages 7–9).
- **Tokens:** new `Panel` collection with modes **Admin** (plum sidebar, rose accent, gold label) and **Marketer** (violet sidebar and accent). Everything else reuses the storefront `Primitives` / `Color` / `Spacing` / `Radius` variables, Cairo/Baloo text styles and shadows. Code syntax `var(--color-panel-*)`.
- **New components (page 7):** `Panel/Nav item` (Default/Active, swappable icon, count), `Panel/Nav group` (Open/Closed), `Panel/Top bar` (⌘K search, bell, locale, user), `Panel/Page header` (breadcrumbs, title + count, actions), `Panel/Table row` (Header/Default/Selected), `Panel/Table toolbar`. Reused from storefront: Button, Badge, Chip, Field, Checkbox, Tabs, Pagination, Breadcrumbs, Stat card, icons.
- **Screens (1440, RTL Arabic):** Admin Dashboard, Admin Products (with bulk actions), Admin Login + OTP (page 8); Marketer Dashboard, Marketer Suggestions Center, Marketer Login + OTP (page 9). The admin sidebar has 8 groups, down from 50 flat links.
- **Accent (owner decision):** Primary Button (fill, hover and 3D shadow), the selected Chip and the checked Checkbox/Radio are bound to `panel/accent`, `panel/accent-hover` and `panel/accent-deep`. Admin mode keeps the exact storefront rose values, so the storefront is unchanged; Marketer mode turns them violet.
- **Layout + more components (page 7):** `Panel/Sidebar` (Expanded 264 / Collapsed 72 rail), `Panel/App shell` (Desktop 1440 / Tablet 1024 / Mobile 390 with a page slot), `Panel/Card`, `Panel/Switch`, `Panel/Form section` (two-column), `Panel/Dropdown menu`, `Panel/Bulk actions bar`.
- **Scope (owner, 2026-11-15):** design covers **layout + components only**. The 6 screens built so far are reference examples; other pages are composed in code from these components.
- The separate file "3alraf — Admin & Marketer Design System" (`FmHTByaeNXYUBeoVALma1U`) was superseded by this one and can be deleted.

### Affiliate panel (owner request 2026-11-15) — Figma page "10 · Affiliate panel — Layout & components"
- Panel collection gets a third mode **Affiliate** (mint sidebar and accent, dark active pill, sun labels, sun payout button).
- Components: `Affiliate/Balance card` (Available / Empty), `Affiliate/Referral link` (code, LTR URL, copy, QR, WhatsApp / share), `Affiliate/Progress card` (Bonus / Tier), `Affiliate/Product link` (Desktop row / Mobile card with the earning per sale), `Affiliate/Bottom nav` (4 tabs), `Affiliate/App shell` (Desktop sidebar 1440 / Mobile bottom nav 390). Shared: Top bar, Nav item, Card, Table row, Stat card, Button (mint via panel accent).
- Code (later, with B4): `<x-layouts.affiliate>` replaces the storefront layout + pill nav on `/{locale}/affiliate/*`; guard `affiliate` unchanged.

### Admin sidebar re-sort (owner request 2026-11-15) — done in code
`components/layouts/admin.blade.php`: shortcuts Dashboard, POS, Orders, Reports; then Sales & customers, Catalog, Stock & purchasing, Offers & marketing, Affiliates, Content, Finance, Shipping & branches, Team & access, Settings (most used link first in each). All 51 links placed; permissions and badges unchanged.

## 3. Execution order

```
Week-ish 1   B1 + B2 + B3 (backend split)   ║  D0 + D1 + D2 (audit, IA, foundations)   [needs Figma auth]
Week-ish 2   QA of the split                 ║  D3 component library
Week-ish 3   B4 design system in code        ║  D4 admin screens
Week-ish 4+  B5 admin modules (incremental)  ║  D5 marketer screens → B6
Final        Track C full regression + docs
```
Each phase ends with tests green (same 8 known failures), `npm run build`, and an owner review of the diff. Nothing is committed without approval.

---

## 4. Risks

| Risk | Mitigation |
|---|---|
| Remapping data from `admins` to `marketers` loses authorship history | Dry-run command that prints the mapping first; DB backup; nullable FKs; audit log keeps the old causer. |
| Marketing tools duplicated in two panels drift apart | Reuse the same Services/Requests; only controllers, routes and views are per-panel (if Q1 = both). |
| Redesign breaks DataTables/JS hooks (`data-*` attributes) | Keep every `data-*` hook and id; browser smoke after each module. |
| Admins with marketing roles lose access on cut-over | The migration creates their marketer accounts automatically; announce the new `/marketer/login`. |
| Figma not connected | The owner authorizes it via `/mcp`; engineering B1–B3 proceeds meanwhile. |

---

## 5. Owner decisions (2026-11-15), binding

- **Q1 → Both panels.** Each panel gets its own controllers, routes and views for banners, coupons, flash sales, bundles, blog, static/custom pages, SEO, affiliates, product lists and reviews, on the **same** Services/Requests/DTOs. Marketer permissions for them are on guard `marketer`.
- **Q2 → Owner role only.** Role `marketer` can create, edit, submit and archive. Only `marketer-owner` holds `*.approve`, `ai_suggestions.apply`, `meta_ads.publish`, tokens, API log, integration clients and `marketers.manage`.
- **Q3 → Phone + password + WhatsApp OTP**, same as admin, with a per-account `otp_exempt`.
- **Q4 → Auto-migrate.** Marketer accounts are created from admins who hold marketing permissions or authored marketing rows; history is remapped (dry run first, backup required).
- **Q5 → (default)** Full redesign, delivered incrementally: design system + shell + top screens first, then module by module.
- **Q6 → (default)** Keep the `/marketer` path; separation is by guard. The subdomain can come later.

## 6. Original open questions

- **Q1 Marketing tools**: should banners/coupons/flash sales/bundles/blog/pages/SEO/affiliates/product lists/reviews (a) **move** to the marketer panel only, (b) be available in **both** panels (shared services), or (c) **stay admin-only** and be dropped from the marketer nav?
- **Q2 Approval authority**: today marketers can approve and apply suggestions themselves. Keep that, or reserve approve/apply/publish for `marketer-owner`?
- **Q3 Marketer login**: same as admin (phone + password + WhatsApp OTP) or email + password?
- **Q4 Existing data/accounts**: auto-create marketer accounts from admins who hold marketing permissions and remap history, or start clean?
- **Q5 Redesign scope**: all 41 admin modules, or design system + shell + top ~10 screens first?
- **Q6 URL**: keep `/marketer` path, or a subdomain (e.g. `marketing.3alraf.com`) for full separation, including separate session cookies?
