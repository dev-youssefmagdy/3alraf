# Product AI tools on FreeLLMAPI — plan

Status: in progress (2026-11-27). Owner: PM/BA. Team runs **one agent at a time**, in the order of section 6.

## 1. Goal

Add [FreeLLMAPI](https://github.com/tashfeenahmed/freellmapi) to the VPS as a private LLM gateway: one OpenAI-compatible endpoint
in front of the free tiers of Gemini, Groq, OpenRouter, Mistral and other providers. Then build three admin actions on
**Admin → Products → Edit**, run as the logged-in admin, with permission `products.edit`:

| # | Action | Input | Output |
|---|--------|-------|--------|
| A1 | **Price list by image** | product/variant primary image, or an uploaded photo | the product the vision model identified, then A2 run on that identification |
| A2 | **Marketplace prices** | product/variant search text (prefilled like *Find images*) | a table of marketplaces selling the same product: store, listing title, price, currency, stock, link, source of the price |
| A3 | **Title ideas** | product (and each variant): names, brand, category, attributes, description, optional competitor titles from A2 | 5 Arabic and 5 English title suggestions per target. The admin picks one and **Save** writes it at once, with an activity-log entry |

Non-goals: automatic repricing, scheduled scraping, storefront price comparison, the marketer panel and MCP (possible follow-up).

## 2. Facts and constraints

- FreeLLMAPI: Node 20+, Docker image `ghcr.io/tashfeenahmed/freellmapi:latest`, port 3001, SQLite in volume `freellmapi-data`
  (`/app/server/data`), required `ENCRYPTION_KEY`, `HOST_BIND` (default 127.0.0.1). Client auth: `Authorization: Bearer freellmapi-…`
  (the unified key from the dashboard's Keys page). API: `POST /v1/chat/completions`, `GET /v1/models?available=true`, `POST /v1/search`.
  Vision works through OpenAI `image_url` parts (base64 data URL). Returns 422 `no_vision_model` when no vision model is enabled,
  and 429 `quota_exceeded` when every key is capped. Model `auto` follows the fallback chain.
- Upstream calls itself "personal experimentation, single-user". Treat it as **best effort**: every action must fail soft
  with a clear message, and never block saving a product.
- Never use the `curl … | bash` installer from freellmapi.co. Install from the pinned GitHub image with our own compose file.
- Web search: the repo already uses the open-source `ddgs` library (tools/image-picker, free, no key). Add a **text** search
  command to the same script with backends `google,bing,duckduckgo` (config). FreeLLMAPI `/v1/search` is an optional second
  provider, used only when an engine key (Tavily/Brave/Exa) is set in its dashboard.
- **Prices are never invented.** Priority: (1) schema.org `Product`/`Offer` JSON-LD or `product:price:amount` meta on the
  fetched listing page (deterministic); (2) a price that appears literally in the search snippet or page text, extracted by
  the LLM, which must quote the source text. The server checks that the quote really contains the number; (3) otherwise
  `price = null` ("price not shown"). Every row records `price_source` = `structured_data` | `page_text` | `snippet` | `none`.
- Page fetching reuses `App\Support\Http\SafeImageFetcher`'s SSRF rules (a new `SafePageFetcher`: http/https only, public IPs only,
  2 MB cap, 8 s timeout, at most 8 pages per run).
- Market: Egypt first (`config('product_ai.market')`: country `EG`, currency `EGP`, language `ar`). Marketplace allow-list in
  config: amazon.eg, noon.com/egypt, jumia.com.eg, btech.com, 2b.com.eg, raya, carrefouregypt, dream2000 and others, editable.
  Results from other domains are kept but ranked lower and marked "other site".
- Variants have no name today (`ProductVariant::label()` joins attribute values). A3 adds a nullable translatable `name`
  (json) to `product_variants`. `label()` returns it when set, so the cart, orders and the storefront follow without more changes.
- **Automatic model switching (owner request, 2026-11-27).** FreeLLMAPI's `auto` already fails over across providers on
  429/5xx. On top of that, `FreeLlmClient` reads ordered model lists from config (`FREELLM_TEXT_MODELS=auto,auto:reliable,auto:fast`,
  `FREELLM_VISION_MODELS=auto,gemini-2.5-flash,…`). On 429/`quota_exceeded`, 5xx, timeout or 422 `no_vision_model` it tries the
  next model. It caches a "cooling down" flag per model for `Retry-After` seconds (default 60) so later requests skip that model.
  It throws `LlmUnavailableException` only when every model fails. It logs the `X-Routed-Via` header and returns which model answered.
  `llm:check` lists each model as ready, cooling down or failed. The setup guide explains the dashboard fallback chain with several providers.
- Error contract (from the UX spec): JSON `{message, code}`, codes `not_configured|gateway_down|quota_exceeded|no_vision_model|throttled|timeout`.
  Cached price results include `cached_at`. Uploads are JPG/PNG/WebP, 5 MB at most.
- Caching: A1/A2 results 24 h, keyed by query or image hash. A3 is not cached. Throttle: `product-ai` 10/min per admin.
- Requests run synchronously, like *Find images* (60 s ceiling). LLM timeout is set in config.
- **Subdomain (owner request, 2026-11-27).** FreeLLMAPI is served at `https://llm.3alraf.com` (name is a variable in the guide and the
  nginx file). nginx is the only public entry: HTTP→HTTPS, TLS by certbot, `/v1/` public but guarded by the unified key and `limit_req`,
  dashboard `/` and `/api/` behind an optional IP allow-list (no basic auth: it collides with `Authorization: Bearer`). Docker stays on
  `127.0.0.1:3001`. Laravel on the same VPS uses `http://127.0.0.1:3001/v1`; on another server `https://llm.3alraf.com/v1`. First-run
  dashboard setup is done through the SSH tunnel before the site goes public.
- **Settings, not .env (owner request, 2026-11-27).** The Laravel side is configured in **Admin → System settings → AI tools (FreeLLMAPI)**
  (`App\Support\SystemSettings` group `ai`, encrypted `settings` rows applied over `config('product_ai.*')` at boot; the API key is a
  secret field, blank keeps it; a *Test connection* button like the mail test). `env()` in `config/product_ai.php` is only the
  default / first import. The container's own `/opt/freellmapi/.env` (`ENCRYPTION_KEY`, `PORT`) stays a file: it is read before any database exists.

## 3. Architecture (backend)

```
config/product_ai.php                   base_url, api_key, models (vision/text = "auto"), timeouts, market, marketplaces, search engines, cache, throttle
app/Services/Llm/FreeLlmClient.php      chat(), chatJson() (response_format + tolerant JSON parse), vision parts, models(), health(); LlmUnavailableException
app/Services/ProductAi/
  WebSearchService.php                  ddgs text search via tools/image-picker (Process) + optional FreeLLMAPI /v1/search; dedupe and normalise
  SafePageFetcher.php                   SSRF-safe HTML fetch (App\Support\Http)
  PriceExtractor.php                    JSON-LD / meta parse → LLM fallback with quote verification
  ProductIdentifier.php                 A1: image → {brand, name, model, size, search_queries[]}
  MarketPriceService.php                A2: queries → search → filter/rank by marketplace → fetch → extract → rows + stats (min/median/max)
  TitleSuggestionService.php            A3: prompt with product context → validated ar/en lists; apply() saves name + activity log
app/Http/Controllers/Admin/ProductAiController.php   identify, prices, titles, applyTitle
app/Http/Requests/Admin/ProductAi*Request.php
routes/admin.php                        products/{product}/ai/* (can:products.edit, throttle:product-ai)
database/migrations/…_add_name_to_product_variants_table.php
app/Console/Commands/LlmCheckCommand.php `php artisan llm:check`: reachability, key, vision model available, search works
tools/image-picker/image_picker.py      new `text` subcommand (JSON output), shared cache
backend/deploy/freellmapi/              docker-compose.yml, .env.example, nginx/llm.3alraf.com.conf (+ IP allow-list example)
deploy.sh                               --freellm flag: pull image and restart the compose project
app/Support/SystemSettings.php          group `ai` (System settings page, encrypted, applied at boot)
docs/FREELLMAPI_SETUP.md                VPS guide (Docker, DNS, nginx + certbot, providers, fallback chain, settings, checks)
```

Prompts live in the service classes as constants. Every LLM answer is treated as untrusted data and validated: JSON
shape, string lengths, no HTML, prices checked as above.

### 3.1 API for frontend

Built by the AI developer (2026-11-27). All routes are in `routes/admin.php`, need the admin session + CSRF, `can:products.edit`, and
`{product}` = the product id. Use `route('admin.products.ai.<name>', $product)`.

| Route name | Method / path | Throttle |
|------------|---------------|----------|
| `admin.products.ai.status` | GET `admin/products/{product}/ai/status` | 60/min |
| `admin.products.ai.identify` | POST `…/ai/identify` (multipart when uploading) | `product-ai` (10/min per admin, setting) |
| `admin.products.ai.prices` | POST `…/ai/prices` | `product-ai` |
| `admin.products.ai.titles` | POST `…/ai/titles` | `product-ai` |
| `admin.products.ai.titles.apply` | POST `…/ai/titles/apply` | `product-ai` |

Blade: "configured" = `app(\App\Services\Llm\FreeLlmClient::class)->configured()` (base URL and key set). Targets: reuse
`ImagePickerService::targets($product)` (a variant's `label` now returns its own name when set).

**Success** is always `{"success": true, "data": {…}}` (apply also has `message`). **Errors**:

| HTTP | Body | When |
|------|------|------|
| 503 | `{success:false, message, code:"not_configured"}` | base URL / key empty, or the key was rejected |
| 503 | `{…, code:"gateway_down"}` | gateway unreachable, 5xx, or no model gave a usable answer |
| 429 | `{…, code:"quota_exceeded"}` + `Retry-After` | every model out of quota / cooling down |
| 422 | `{…, code:"no_vision_model"}` | identify: no vision model enabled |
| 504 | `{…, code:"timeout"}` | every model timed out |
| 429 | `{success:false, message, code:"throttled"}` + `Retry-After` | the `product-ai` limiter (message has the seconds) |
| 422 | Laravel validation `{message, errors:{field:[…]}}` | bad input; also `errors.image` = *no photo* / *unreadable photo*, `errors.variant_id` / `errors.target_id` = variant not of this product |

`message` is already translated (`admin.product_ai.error_*`, `not_configured`, …).

**GET status** → `data: {configured: bool, market: {country:"EG", currency:"EGP", language:"ar", region:"eg-ar"}, locales: ["ar","en"], upload_max_kb: 5120}`

**POST identify** — fields: `source` = `product|variant|upload` (required); `variant_id` (int, required when `variant`); `image` (file,
required when `upload`; jpg/png/webp, ≤ 5120 KB). `product` uses the product's first image, `variant` the variant's first image
(else the product's).
→ `data: {brand: string|null, name: string|null, model: string|null, size: string|null, search_queries: string[] (0–5, first = best),
found: bool, answered_by: "platform/model", computed_at: ISO-8601, cached_at: ISO-8601|null}`. `found=false` → *identify_empty*.
Cached 24 h by image hash (`cached_at` set when served from cache). Never starts a search.

**POST prices** — fields: `query` (string 2–200, required), `target_type` (`product|variant`, optional), `target_id` (int, optional),
`fresh` (bool, optional: skip the 24 h cache).
→ `data`:
```json
{
  "query": "Philips HD9200", "searched": "Philips HD9200 price Egypt", "currency": "EGP",
  "rows": [{
    "marketplace": "Amazon.eg", "domain": "amazon.eg", "allowed": true,
    "title": "Philips Essential Airfryer HD9200/90", "url": "https://www.amazon.eg/…",
    "price": 1150, "currency": "EGP", "in_stock": true,
    "price_source": "structured_data", "quote": null,
    "snippet": "search result text", "engine": "bing"
  }],
  "stats": {"currency": "EGP", "min": 1150, "median": 1299, "max": 1320, "priced": 4, "counted": 3, "total": 5},
  "errors": {"duckduckgo": "RatelimitException: …", "ai": "quota_exceeded"},
  "pages_failed": 2, "model": "groq/llama-3.3-70b|null",
  "computed_at": "2026-11-27T10:00:00+02:00", "cached_at": null
}
```
- `price`: number or `null` (*Price not shown*); `currency`: ISO code or `null`; `in_stock`: `true|false|null` (null = *Unknown*).
- `price_source`: `structured_data | page_text | snippet | none`; `quote` is set for `page_text` / `snippet` (the verified source text
  containing the price) and null otherwise.
- `allowed=false` → *Other site* badge; `marketplace` is the store name for allow-listed domains, else the domain. Rows come
  allow-listed first, then others, in search order (sort client-side).
- `stats` counts only rows in the market currency (`counted`); `priced` = rows with any price. The UX recomputes it client-side from
  the visible rows; the server copy is for convenience.
- **Partial** state: `errors` not empty (engine names as keys; `ai` = the LLM error code, prices then come only from structured data)
  or `pages_failed > 0`. `rows: []` → *no_results*; rows but no price → *no_prices*.
- Only complete runs are cached (rows found and no `ai` error).

**POST titles** — fields: `target_type` (`product|variant`, required), `target_id` (int, required for `variant`), `use_competitor_titles`
(bool), `competitor_titles` (string[] ≤ 10, each ≤ 300; sent only when the checkbox is on: the visible rows' `title`s).
→ `data: {target: {type, id, label, current: {ar: string|null, en: string|null}}, titles: {ar: string[1–5], en: string[1–5]}, answered_by}`.
Each title is plain text, 10–120 characters. `current.<locale> = null` → *current_none* (show `label`). Not cached.

**POST titles/apply** — fields: `target_type` (`product|variant`), `target_id` (int, required for `variant`), `locale` (`ar|en`),
`title` (string 10–120). Works even when the gateway is down.
→ `{success: true, message: "EN title saved for …", data: {target: {type, id, label, current: {ar, en}}, locale, title}}`. Writes only
that locale of `products.name` / `product_variants.name` and an activity-log entry (`log_name=product_ai`, `product_ai.title_applied`).
After saving a product title, update the `name[<locale>]` input on the page; for a variant, its `[data-label]` (use `target.label`).

**Lang keys already added** (`admin.product_ai.*`, en + ar): `not_configured`, `error_gateway_down`, `error_quota`, `error_no_vision`,
`error_throttled`, `error_timeout`, `error_bad_answer`, `error`, `no_primary`, `bad_variant`, `image_unreadable`, `saved`. The
Frontend developer adds the rest of 7.10 to the same array.

## 4. UX (summary — full spec from the UI/UX designer in section 7)

A new **"AI tools"** card on the product edit page, beside the gallery card, with three buttons. They open one dialog,
built like the `_image-picker` dialog, with tabs: *Prices by image*, *Marketplace prices*, *Title ideas*. Product/variant
target list on the left, as in *Find images*. RTL, ar/en strings in `lang/*/admin.php` under `admin.product_ai.*`.
States: idle, loading (with a step indicator: identifying → searching → reading pages), empty, partial (some engines
failed), error (gateway down or quota). Titles: radio list per language, a character count, *Save* per target, and a
toast when saved.

## 5. Acceptance criteria

1. On a VPS with no FreeLLMAPI, the edit page loads and the AI card shows "AI gateway not configured". Nothing breaks.
2. `php artisan llm:check` reports OK, or names the exact failure (URL, key, no vision model, search).
3. A1 on a product with a photo returns an identification and a price table in under 60 s, or a clear error.
4. No price is shown that does not come from structured data, page text or a snippet that contains it. A unit test proves the quote check.
5. A3 returns 1 to 5 titles per language, each 10–120 characters. Saving updates `products.name` or `product_variants.name` for that locale only and writes an activity log entry. The page shows the new name.
6. The SSRF guard blocks private/loopback URLs (test). The throttle is in place. Only `products.edit` users can reach the routes (test).
7. All new Pest tests pass, and so does the existing suite. Pint is clean. `npm run build` passes.
8. `docs/FREELLMAPI_SETUP.md` takes a fresh Ubuntu VPS to a working setup, with verification commands.

## 6. Team run order (sequential)

1. **Senior UI/UX Designer**: UX spec for the dialog (layouts, states, copy ar/en, a11y) → section 7 of this file.
2. **Senior Graphic Designer**: 3 action icons + a marketplace/price-source badge style, matching the admin icon set → `resources/views/admin/...` partial / icon sprite.
3. **Senior AI Developer**: everything in section 3 (backend, prompts, ddgs text search, deploy files, `docs/FREELLMAPI_SETUP.md`), with Pest tests.
4. **Senior Frontend Developer**: Blade card, dialog and JS (`resources/js/lib/product-ai.js`) on the AI developer's endpoints, plus lang strings.
5. **Senior QA tester**: run the suites, add missing edge-case tests, check the UI in the browser (with FreeLLMAPI absent and with it mocked), report bugs → fixed.
6. **QC**: review the full diff against this plan, the CLAUDE.md rules and the security points. Check the setup doc for accuracy. Gives the final go/no-go.

## 7. UX spec

Author: Senior UI/UX Designer, 2026-11-27. This is the input for the Graphic Designer (7.9), the AI developer (the error codes in 7.4) and
the Frontend developer (all of it). It mirrors *Find images* (`_image-picker.blade.php`, `lib/image-picker.js`) and reuses `x-ui.*`.

### 7.1 Entry points

| Where | Markup | Opens |
|-------|--------|-------|
| **Images** tab (`data-ptab-panel="media"`), a new `<x-ui.form-section :title="__('admin.product_ai.card_title')" :description="__('admin.product_ai.card_hint')" :cols="1" stacked>` directly **after** the Images form-section (inside the form, so it sits with the gallery card). Edit mode only, `@can('products.edit')` | Three `btn btn-secondary` buttons in a `flex flex-wrap gap-2` row, each with `<x-ui.icon>` (7.9) at `size-4` before the label: *Prices by image*, *Marketplace prices*, *Title ideas* | the dialog, on the matching tab, target = product |
| **General** section actions slot (beside the name) | `btn-sm btn-secondary` *Title ideas* (same markup as the *Find images* button in the gallery card) | Titles tab, target = product |
| **Pricing** section actions slot | `btn-sm btn-secondary` *Marketplace prices* | Market tab, target = product |
| `_variant-row.blade.php`, image cell, under the existing *Find image* link, only when `$v['id']` is set | `mt-1 block text-xs font-bold text-panel-accent-text underline` link-button *AI tools* | Market tab, target = that variant |

All buttons are `type="button"` and carry `data-product-ai-open data-tab="image|market|titles" data-target-type="product|variant" [data-target-id]
aria-haspopup="dialog" aria-controls="product-ai"`, the same pattern as `data-image-picker-open`.

**Not configured** (config has no `base_url`/`api_key`, sent as `cfg.configured=false`): the card still renders, the 3 buttons are
`disabled` and an `<x-ui.alert tone="info">` *AI gateway not configured…* (`not_configured`) sits above them. The small shortcut buttons
and the variant links are not rendered. The dialog partial is not included. Nothing else on the page changes.

### 7.2 Dialog shell (shared by the 3 tabs)

Partial `admin/products/_product-ai.blade.php`, included next to `_image-picker` (line ~224). Same frame as the image picker:
`fixed inset-0 z-50 … bg-black/50`, panel `max-w-6xl`, full screen below `md`, `md:h-[calc(100vh-3rem)] md:rounded-2xl`. Config in
`data-config='@json($cfg)'`: endpoint URLs, `targets` (same shape as `ImagePickerService::targets()`), `configured`, `i18n` =
`__('admin.product_ai')`, `locales` = `['ar','en']`.

```
┌──────────────────────────────────────────────────────────────────────────────┐
│ AI tools                                   For [Product: Name (SKU)     ▾]  ✕ │  header (as image picker)
│ Free AI models via the store gateway. Check results before use.              │  subtitle hidden < md
├──────────────────────────────────────────────────────────────────────────────┤
│ [▣ Prices by image]  [▣ Marketplace prices]  [▣ Title ideas]                 │  tablist, underline style
├──────────────────────────────────────────────────────────────────────────────┤
│ (tab panel: scrolls, aria-live region at the top)                           │  min-h-0 flex-1 overflow-y-auto
└──────────────────────────────────────────────────────────────────────────────┘
```

- **Target**: a `<select data-pai-target>` in the header, exactly as the image picker (`Product: …` / `Variant: …`). The plan's "list on the
  left" is replaced by this select on purpose: it matches *Find images* and leaves the full width to the price table. Changing the target
  resets the current tab to idle (results for each target are kept in memory per tab and restored when you return to it).
- **Tabs**: markup and classes of `x-ui.tabs` underline style (`role="tablist"`, buttons `role="tab"`, `min-h-11`, active
  `border-panel-accent text-panel-accent-text`), but with their own `data-pai-tab` attribute (not `data-ptab`, which drives the page tabs).
  Each tab has its icon (7.9, `size-5`) + label; below `sm` the label stays (the strip scrolls, `overflow-x-auto`).
- **RTL**: logical classes only (`ps-/pe-`, `text-start`, `ms-auto`). The close button sits at the end (left in RTL). Numbers, prices,
  SKUs, domains and URLs get `dir="ltr"` + `tabular-nums`. Arabic content always `lang="ar" dir="rtl"`, English `lang="en" dir="ltr"`,
  whatever the UI locale. The `external` icon mirrors in RTL (already in `Icons::mirrors`).
- **Responsive**: `< md` full-screen sheet; header wraps (select goes to its own line, `w-full`). Price table becomes a card list below
  `md` (7.4). Titles: 2 columns (ar | en) from `lg`, stacked below. In RTL the grid puts Arabic on the right automatically.

### 7.3 States (all tabs)

One status area per tab panel (`data-pai-status`, inside the `aria-live="polite"` region). Exactly one state at a time.

| State | What shows | Controls |
|-------|-----------|----------|
| not configured | dialog never opens (7.1) | — |
| idle | the tab's form + a centred `py-10 text-sm text-panel-text-subtle` hint (`*_idle`) | run button enabled |
| loading | `<x-ui.stepper>` with the tab's steps + `<x-ui.spinner :size="16">` and `steps_note`; `x-ui.skeleton kind="tablerow"` ×4 under it on price tabs | run button `disabled`, label `…ing`; target select and tabs stay usable, but a running request is kept per tab and its result is dropped if the target changed |
| success | results | run button label becomes *Search again* / *Suggest again* |
| partial | results + `<x-ui.alert tone="warning">` `partial` listing the failed sources (engine names, "N pages could not be read") above the table | as success |
| empty | `no_results` (no listings) or `no_prices` (listings, none priced: the table still shows, summary hidden) or `titles_empty` | run again |
| error | `<x-ui.alert tone="danger">` with the mapped message + `btn-sm btn-secondary` *Try again* | run button enabled |

**Step indicator.** Requests are synchronous, so the stepper is time-driven and honest: it advances on a timer and the last step stays
current (with spinner) until the answer arrives; it never shows "done" before the response. Steps and timer start:

| Tab | Steps (`step_*` keys) | Timer |
|-----|------|-------|
| Prices by image — identify call | identifying | — (single step) |
| Prices by image — prices call, and Marketplace prices | searching → reading pages → extracting | 0 s, 6 s, 20 s |
| Title ideas | writing | — |

On A1 the stepper shows all 4 steps (identifying ✓ then the 3 price steps) so the admin sees one flow.

**Error mapping** (the AI developer returns JSON `{message, code}`; the client maps `code`, falls back to `message`, then `error`):

| HTTP / `code` | Key | Note |
|---------------|-----|------|
| 503 `not_configured` | `not_configured` | race: config removed after page load |
| 503 `gateway_down` (connection refused, 5xx, timeout to FreeLLMAPI) | `error_gateway_down` | |
| 429 `quota_exceeded` | `error_quota` | |
| 422 `no_vision_model` | `error_no_vision` | + a link-button *Use Marketplace prices* that switches tab with the same target |
| 429 without code (Laravel throttle) | `error_throttled` | `:seconds` from `Retry-After`; the run button stays disabled with a countdown until 0 |
| 504 / `timeout` | `error_timeout` | |
| 422 validation | the first field error (as `errorOf()` in image-picker.js) | |
| network failure / other | `error` | |

### 7.4 Price table (shared by A1 and A2)

```
┌ Summary ────────────────────────────────────────────────────────────────────┐
│ Lowest 1,150 EGP   Median 1,299 EGP   Highest 1,450 EGP   6 of 9 listings show a price │
└─────────────────────────────────────────────────────────────────────────────┘
[x] Include other sites                         Sort by [Price: low to high ▾]
┌────────────┬───────────────────────────┬────────────┬──────────┬──────────────┬──────┐
│ Marketplace│ Listing                   │ Price      │ Stock    │ Price from   │ Link │
├────────────┼───────────────────────────┼────────────┼──────────┼──────────────┼──────┤
│ noon       │ Philips Air Fryer HD9200… │ 1,150 EGP  │ In stock │ (Structured) │ Open↗│
│ jumia      │ قلاية هوائية فيليبس…      │ 1,299 EGP  │ Unknown  │ (Page text)▸ │ Open↗│
│ shop-x.com │ Air fryer HD9200 …        │ 1,320 EGP  │ —        │ (Snippet) ▸  │ Open↗│
│ [Other site]                                                                 │
│ amazon.eg  │ Philips HD9200/90 …       │ Price not shown │ … │ (Not shown) │ Open↗│
└────────────┴───────────────────────────┴────────────┴──────────┴──────────────┴──────┘
```

- **Columns**: marketplace (store name from config, else the domain, `dir="ltr"`; an allow-listed store in `font-semibold`, others get
  `<x-ui.badge tone="neutral" size="sm">Other site</x-ui.badge>` under the name with `title="other_site_hint"`) · listing title
  (`line-clamp-2`, text only via `textContent`, `lang` from the data when given) · price + currency (`dir="ltr" tabular-nums font-bold`,
  formatted `Intl.NumberFormat(locale, {maximumFractionDigits: 2})` with Latin digits like the rest of the admin, currency code after the
  number) · in stock (`In stock` success badge / `Out of stock` danger badge / `Unknown` plain muted text) · price source badge (7.9) ·
  link `Open` + `external` icon, `target="_blank" rel="noopener noreferrer nofollow" referrerpolicy="no-referrer"`, sr-only text
  `open_listing_sr`.
- **Price not shown** (`price === null`): the cell reads `Price not shown` in `text-panel-text-subtle italic` (never `0`, never `—`
  alone, never an empty cell). These rows are always sorted last, are left out of the summary, and their source badge is *Not shown*.
- **Quote**: `page_text` and `snippet` badges are buttons (`aria-expanded`, `aria-controls`) that open a detail row
  (`bg-panel-surface-sunken text-xs`) with *Source text:* and the quoted text, the price in `<mark>`. `structured_data` and `none` are
  plain badges.
- **Sorting**: `<select>` *Sort by*: Price low → high (default), Price high → low, Marketplace A–Z. Column header *Price* is also a button
  toggling asc/desc with `aria-sort` on the `th`. Null prices last in every order. Allow-listed and other sites are mixed by price
  (the badge marks them); when *Include other sites* is unchecked those rows hide and the summary recomputes.
- **Summary**: an `x-ui.kv`-style row above the table (`grid grid-cols-2 sm:grid-cols-4`): Lowest / Median / Highest (same price
  formatting; with mixed currencies, only the market currency `EGP` rows count and a note says so) + `summary_count`. Hidden when no
  visible row has a price. Computed client-side from the visible priced rows so it always matches the table.
- **Cached** result (backend flag `cached_at`): a muted line `cached` with a *Search again* link that sends `fresh: true`.
- **Below `md`**: each row becomes an `li` card: line 1 marketplace + badges, line 2 title, line 3 price (large) + stock, line 4 source
  badge + Open link. Same sort and summary.

### 7.5 Tab A1 — Prices by image

```
┌ Photo ───────────────────────────┐ ┌ Identified product ────────────────────────┐
│ (•) Main photo of the selected item│ │ Brand   Philips                            │
│ ( ) Upload a photo [Choose file]  │ │ Name    Essential Air Fryer                 │
│ ┌──────────┐ JPG, PNG or WebP,    │ │ Model   HD9200/90                           │
│ │ preview  │ up to 5 MB.          │ │ Size    4.1 L                               │
│ └──────────┘                      │ │ Search text [Philips HD9200 air fryer    ] │
│ [Identify product]                │ │ Other searches: (chip) (chip) (chip)        │
└───────────────────────────────────┘ │ [Search prices]                             │
                                      └─────────────────────────────────────────────┘
(price table, 7.4)
```

- Two columns from `lg` (photo `lg:w-80` start side, result the rest); stacked below.
- **Photo source**: `x-ui.radio-group` with *Main photo of the selected item* (uses the target's `thumb`; if none, this option is disabled
  with `no_primary` as hint and *Upload* is selected) and *Upload a photo* (`<input type="file" accept="image/jpeg,image/png,image/webp">`,
  checked client-side: ≤ 5 MB, else `upload_too_big` / `upload_bad_type` inline, `text-panel-danger-fg`). Preview `size-40 object-contain
  rounded-lg border border-panel-border`, `alt` = `preview_alt`.
- **Identify product** (`btn btn-primary`) → stepper step 1 → result card (`rounded-xl border p-4`): Brand / Name / Model / Size as a
  definition list; a missing value shows `not_found` muted. If the model found nothing usable: empty state `identify_empty` + a
  link-button to *Marketplace prices*.
- **Confirm before searching**: the search is never started automatically. *Search text* (`form-control`, maxlength 200) is prefilled with
  `search_queries[0]`; the other queries are `x-ui.chip`s that copy their text into the field when clicked. *Search prices*
  (`btn btn-primary`, disabled while the field is empty) runs A2 with that text; the table appears under the two columns.

### 7.6 Tab A2 — Marketplace prices

```
[ Philips HD9200 air fryer                         ] [Search prices]
Searches marketplaces in Egypt for the same product.
(price table, 7.4)
```

A form row like the image picker's search form (`data-pai-query` + `btn btn-primary`), prefilled with the target's `query`, Enter submits.
Thumbnail of the target at `size-10` before the field (as `data-ip-thumb`). Then the hint and the table. Results are remembered per target
for the Title tab's *use competitor titles*.

### 7.7 Tab A3 — Title ideas

```
[x] Use competitor titles from Marketplace prices        [Suggest titles]
┌ العربية ───────────────────────────────┐ ┌ English ──────────────────────────────┐
│ العنوان الحالي: قلاية هوائية فيليبس     │ │ Current title: Philips Air Fryer       │
│ (•) قلاية هوائية فيليبس 4.1 لتر HD9200  │ │ ( ) Philips Essential Air Fryer 4.1 L  │
│ ( ) …                                  │ │ (•) …                                  │
│ ( ) …   (up to 5)                      │ │ ( ) …                                  │
│ عدّل العنوان المختار                     │ │ Edit the chosen title                  │
│ [قلاية هوائية فيليبس 4.1 لتر HD9200  ]  │ │ [Philips Essential Air Fryer 4.1 L  ]  │
│ 38 / 120                     [حفظ]     │ │ 34 / 120                      [Save]   │
└────────────────────────────────────────┘ └────────────────────────────────────────┘
```

- Target = the header select (product or a variant). Each target keeps its own suggestions in memory.
- **Use competitor titles** (`x-ui.checkbox`): enabled only when the Market (or A1) tab has results for this target in this session; else
  disabled with `use_competitors_none` as hint. When checked, the request sends the listing titles of the visible rows.
- **Suggest titles** (`btn btn-primary`) → loading (single step `step_writing`) → one card per locale (`rounded-xl border p-4`, `lang`/`dir`
  set per 7.2), in the order ar, en.
- **Current title** on top of each card, `text-sm text-panel-text-muted`; for a variant with no name yet `current_none` (shows its
  `label()`).
- **Radio list** (`x-ui.radio`, name `pai-title-{target}-{locale}`), 1–5 items; nothing selected at first and the edit field is hidden
  until one is chosen. Choosing one shows **Edit the chosen title** (`form-control`, maxlength 120) prefilled with it; edits do not change
  the radio label.
- **Counter** under the field, `aria-live="polite"` only when crossing a limit: `:count / 120`, `text-panel-text-subtle`; below 10 or above
  120 it turns `text-panel-danger-fg` with `too_short` / `too_long`, the field gets `aria-invalid="true"` and *Save* is disabled.
  Count = `[...value.trim()].length` (Arabic characters counted correctly).
- **Save** (`btn-sm btn-primary`, end of the card), one per target and locale. No confirm. On click: `disabled`, label `saving`; on
  success `window.toastr.success(saved)` (`:lang` and `:target` filled), the card's *Current title* updates, and the page updates too: for the
  product, the `name[<locale>]` input of the General `x-ui.lang-tabs` (so a later form save does not overwrite it) and the page header; for
  a variant, the `[data-label]` in its row and the option text in the header select. On failure `window.toastr.error(message or
  save_failed)`, the edited text stays, the button is enabled again. Same `toastr` as *Find images*.

### 7.8 Accessibility

- Root `id="product-ai"`, `role="dialog" aria-modal="true" aria-labelledby="product-ai-title"`. Unlike the image picker, use the shared
  focus trap and focus return: build it as `x-ui.modal` does (`data-modal` wrapper, inner `role="dialog"`, `[data-modal-close]` on ✕,
  `[data-modal-backdrop]`) and open with `window.panelModal.open('product-ai')` after setting tab/target, so `ui.js` handles Tab trap, Esc
  and returning focus to the trigger. On open, focus goes to the active tab's first field (search text, the photo radio, or *Suggest
  titles*).
- Tabs: `role="tablist"`, `role="tab"` with `aria-selected`, `aria-controls`, roving `tabindex` (0 on active, -1 others); panels
  `role="tabpanel" aria-labelledby`. ←/→ move between tabs (direction follows `dir`: in RTL → goes to the previous), Home/End to first/last,
  activation on arrow (automatic). Tab key goes from the tablist into the panel.
- Live status: one `aria-live="polite"` region per panel carries state text (loading step names, "6 listings found", empty texts). Errors
  use `x-ui.alert tone="danger"` (`role="alert"`). The stepper keeps `aria-current="step"`. Do not announce every timer tick: announce on
  step change only.
- Buttons in loading state keep their accessible name and get `aria-busy="true"`; disabled controls use the real `disabled` attribute.
- Badges are text, never colour alone (badge component rule). The price column header button exposes `aria-sort`. External links say
  they open a new tab (sr-only).
- Contrast: use the panel tokens only (all AA in light and dark). Do not dim "other site" rows with opacity; "Price not shown" uses
  `text-panel-text-subtle` (AA on `panel-surface`). Focus ring `focus-visible:ring-2 focus-visible:ring-panel-accent-soft` as the close
  button. Touch targets ≥ 44 px (`min-h-11`) on mobile.

### 7.9 Reuse and Graphic Designer deliverables

Reuse: `x-ui.form-section` (actions slot), `x-ui.button` / `btn btn-primary|secondary`, `btn-sm`, `x-ui.tabs` classes, `x-ui.alert`,
`x-ui.badge`, `x-ui.stepper`, `x-ui.spinner`, `x-ui.skeleton`, `x-ui.radio(-group)`, `x-ui.checkbox`, `x-ui.chip`, `x-ui.icon`, `form-control`,
`form-label`; tokens `panel-surface`, `panel-surface-sunken`, `panel-border`, `panel-text(-muted|-subtle)`, `panel-accent(-text|-soft)`,
`panel-{success,warning,danger,info}-{bg,fg,solid}`; `window.toastr`; `ui.js` modal trap.

**Icons** (add to `App\Support\Ui\Icons`, 24 grid, 1.8 stroke, round caps/joins, `currentColor`, not mirrored; used at `size-4` in buttons,
`size-5` in tabs):

| Name | Meaning |
|------|---------|
| `ai-price-image` | Prices by image: a photo frame with a small price tag |
| `ai-market-prices` | Marketplace prices: a shop front / tag with a magnifier |
| `ai-titles` | Title ideas: a text line (T or lines) with a small sparkle (from `sparkles`) |

**Price source badges** (a Blade partial or a `tone` map on `x-ui.badge`, `size="sm"`, soft style, text + dot, `title` = its `*_hint`):

| Variant | `price_source` | Tone | Label en / ar |
|---------|----------------|------|---------------|
| structured | `structured_data` | success | Structured data / بيانات منظّمة |
| page text | `page_text` | info | Page text / نص الصفحة |
| snippet | `snippet` | warning | Search snippet / مقتطف البحث |
| not shown | `none` | neutral (dashed border `border border-dashed border-panel-border-strong`) | Not shown / غير معروض |

The *Other site* marker is the existing neutral `x-ui.badge size="sm"`, no dot, so it does not read as a price-source badge.

### 7.10 Copy — `admin.product_ai.*`

| Key | en | ar |
|-----|----|----|
| `card_title` | AI tools | أدوات الذكاء الاصطناعي |
| `card_hint` | Price checks and title ideas from free AI models. These are suggestions: check them before you use them. | فحص الأسعار واقتراح العناوين بنماذج ذكاء اصطناعي مجانية. هذه اقتراحات، راجعها قبل استخدامها. |
| `open_image` | Prices by image | الأسعار من الصورة |
| `open_market` | Marketplace prices | أسعار المتاجر |
| `open_titles` | Title ideas | أفكار للعناوين |
| `variant_link` | AI tools | أدوات الذكاء الاصطناعي |
| `not_configured` | AI gateway not configured. The tools turn on once it is set up (see docs/FREELLMAPI_SETUP.md). | بوابة الذكاء الاصطناعي غير مُعدّة. ستعمل الأدوات بعد إعدادها (راجع docs/FREELLMAPI_SETUP.md). |
| `title` | AI tools | أدوات الذكاء الاصطناعي |
| `subtitle` | Free AI models through the store gateway. Check results before you use them. | نماذج ذكاء اصطناعي مجانية عبر بوابة المتجر. راجع النتائج قبل استخدامها. |
| `for` | For | لـ |
| `product` | Product | المنتج |
| `variant` | Variant | المتغير |
| `close` | Close | إغلاق |
| `tab_image` | Prices by image | الأسعار من الصورة |
| `tab_market` | Marketplace prices | أسعار المتاجر |
| `tab_titles` | Title ideas | أفكار للعناوين |
| `step_identifying` | Identifying the product | التعرّف على المنتج |
| `step_searching` | Searching marketplaces | البحث في المتاجر |
| `step_reading` | Reading pages | قراءة الصفحات |
| `step_extracting` | Extracting prices | استخراج الأسعار |
| `step_writing` | Writing titles | كتابة العناوين |
| `steps_note` | This can take up to a minute. | قد يستغرق هذا حتى دقيقة. |
| `try_again` | Try again | حاول مرة أخرى |
| `source_label` | Photo | الصورة |
| `source_primary` | Main photo of the selected item | الصورة الرئيسية للعنصر المختار |
| `source_upload` | Upload a photo | ارفع صورة |
| `upload_hint` | JPG, PNG or WebP, up to 5 MB. | JPG أو PNG أو WebP، حتى 5 ميجابايت. |
| `upload_too_big` | This photo is larger than 5 MB. | هذه الصورة أكبر من 5 ميجابايت. |
| `upload_bad_type` | Use a JPG, PNG or WebP photo. | استخدم صورة JPG أو PNG أو WebP. |
| `no_primary` | This item has no photo. Upload one. | هذا العنصر بلا صورة. ارفع صورة. |
| `preview_alt` | Selected photo | الصورة المختارة |
| `image_idle` | Choose a photo and press Identify product. | اختر صورة واضغط «تعرّف على المنتج». |
| `identify` | Identify product | تعرّف على المنتج |
| `identifying` | Identifying… | جارٍ التعرّف… |
| `identified` | Identified product | المنتج الذي تم التعرّف عليه |
| `brand` | Brand | الماركة |
| `name` | Name | الاسم |
| `model` | Model | الموديل |
| `size` | Size | المقاس |
| `not_found` | not found | غير معروف |
| `identify_empty` | The model could not tell what this is. Try a clearer photo, or search by text. | لم يتمكن النموذج من معرفة المنتج. جرّب صورة أوضح أو ابحث بالنص. |
| `use_market` | Use Marketplace prices | استخدم أسعار المتاجر |
| `search_text` | Search text | نص البحث |
| `search_text_hint` | Check the words before searching. Brand + model works best. | راجع الكلمات قبل البحث. الماركة + الموديل تعطي أفضل نتيجة. |
| `other_queries` | Other searches | عمليات بحث أخرى |
| `search_prices` | Search prices | ابحث عن الأسعار |
| `searching` | Searching… | جارٍ البحث… |
| `search_again` | Search again | ابحث مرة أخرى |
| `market_hint` | Searches marketplaces in Egypt for the same product. | يبحث في متاجر مصر عن نفس المنتج. |
| `market_idle` | Check the search text and press Search prices. | راجع نص البحث واضغط «ابحث عن الأسعار». |
| `col_marketplace` | Marketplace | المتجر |
| `col_title` | Listing | الإعلان |
| `col_price` | Price | السعر |
| `col_stock` | Stock | التوفر |
| `col_source` | Price from | مصدر السعر |
| `col_link` | Link | الرابط |
| `in_stock` | In stock | متوفر |
| `out_of_stock` | Out of stock | غير متوفر |
| `stock_unknown` | Unknown | غير معروف |
| `price_not_shown` | Price not shown | السعر غير معروض |
| `open_listing` | Open | افتح |
| `open_listing_sr` | Open the :store listing in a new tab | افتح إعلان :store في تبويب جديد |
| `other_site` | Other site | موقع آخر |
| `other_site_hint` | Not on the store's marketplace list. | ليس ضمن قائمة المتاجر المعتمدة. |
| `source_structured_data` | Structured data | بيانات منظّمة |
| `source_page_text` | Page text | نص الصفحة |
| `source_snippet` | Search snippet | مقتطف البحث |
| `source_none` | Not shown | غير معروض |
| `source_structured_data_hint` | Read from the product data the page publishes. | مقروء من بيانات المنتج التي تنشرها الصفحة. |
| `source_page_text_hint` | Found in the page text. Click to see the quote. | موجود في نص الصفحة. اضغط لرؤية النص. |
| `source_snippet_hint` | Found in the search result text. Click to see the quote. | موجود في نص نتيجة البحث. اضغط لرؤية النص. |
| `source_none_hint` | No price on the page or in the search result. | لا يوجد سعر في الصفحة أو في نتيجة البحث. |
| `quote` | Source text | النص المصدر |
| `include_other` | Include other sites | اعرض المواقع الأخرى |
| `sort_by` | Sort by | ترتيب حسب |
| `sort_price_asc` | Price: low to high | السعر: من الأقل للأعلى |
| `sort_price_desc` | Price: high to low | السعر: من الأعلى للأقل |
| `sort_marketplace` | Marketplace A–Z | المتجر أبجديًا |
| `summary_lowest` | Lowest | الأقل |
| `summary_median` | Median | الوسيط |
| `summary_highest` | Highest | الأعلى |
| `summary_count` | :priced of :total listings show a price | :priced من :total إعلانات تعرض سعرًا |
| `summary_currency_note` | Only prices in :currency are counted. | تُحتسب الأسعار بعملة :currency فقط. |
| `results_found` | :count listings found. | تم العثور على :count إعلانات. |
| `no_results` | No listings found. Try shorter words or the English name. | لم يتم العثور على إعلانات. جرّب كلمات أقل أو الاسم بالإنجليزية. |
| `no_prices` | Listings found, but none shows a price. | تم العثور على إعلانات، لكن لا يعرض أيٌّ منها سعرًا. |
| `partial` | Some sources did not answer: :sources. Results may be incomplete. | بعض المصادر لم تستجب: :sources. قد تكون النتائج ناقصة. |
| `pages_failed` | :count pages could not be read | تعذّرت قراءة :count صفحات |
| `cached` | Saved result from :time. | نتيجة محفوظة من :time. |
| `titles_idle` | Press Suggest titles to get ideas in Arabic and English. | اضغط «اقترح عناوين» للحصول على أفكار بالعربية والإنجليزية. |
| `use_competitors` | Use competitor titles from Marketplace prices | استخدم عناوين المنافسين من أسعار المتاجر |
| `use_competitors_none` | Run Marketplace prices for this item first. | شغّل «أسعار المتاجر» لهذا العنصر أولًا. |
| `suggest` | Suggest titles | اقترح عناوين |
| `suggesting` | Writing… | جارٍ الكتابة… |
| `suggest_again` | Suggest again | اقترح مرة أخرى |
| `lang_ar` | Arabic | العربية |
| `lang_en` | English | الإنجليزية |
| `current` | Current title | العنوان الحالي |
| `current_none` | No name yet (shows: :label) | لا يوجد اسم بعد (يظهر: :label) |
| `choose` | Choose a title | اختر عنوانًا |
| `edit_chosen` | Edit the chosen title | عدّل العنوان المختار |
| `counter` | :count / 120 | :count / 120 |
| `too_short` | At least 10 characters. | 10 أحرف على الأقل. |
| `too_long` | At most 120 characters. | 120 حرفًا على الأكثر. |
| `save` | Save | حفظ |
| `saving` | Saving… | جارٍ الحفظ… |
| `saved` | :lang title saved for :target. | تم حفظ العنوان (:lang) لـ :target. |
| `save_failed` | Could not save the title. Try again. | تعذّر حفظ العنوان. حاول مرة أخرى. |
| `titles_empty` | No titles came back. Try again. | لم تصل أي عناوين. حاول مرة أخرى. |
| `error_gateway_down` | The AI gateway is not answering. Try again in a few minutes. | بوابة الذكاء الاصطناعي لا تستجيب. حاول بعد بضع دقائق. |
| `error_quota` | The free AI quota is used up for now. Try again later. | نفدت الحصة المجانية للذكاء الاصطناعي حاليًا. حاول لاحقًا. |
| `error_no_vision` | No AI model that reads photos is turned on. Enable one in the gateway, or search by text. | لا يوجد نموذج مفعّل يقرأ الصور. فعّل نموذجًا في البوابة أو ابحث بالنص. |
| `error_throttled` | Too many requests. Wait :seconds seconds. | طلبات كثيرة. انتظر :seconds ثانية. |
| `error_timeout` | This took too long. Try again with shorter search text. | استغرق الأمر وقتًا طويلًا. حاول مرة أخرى بنص بحث أقصر. |
| `error` | Something went wrong. Please try again. | حدث خطأ. حاول مرة أخرى. |

### 7.11 Graphic assets (delivered)

Graphic Designer, 2026-11-27. Built with Vite (`npm run build` passes). No dialog, JS or lang strings here.

**Icons** are in `App\Support\Ui\Icons` (`backend/app/Support/Ui/Icons.php`). They use the 24 grid, 1.8 stroke, round caps and `currentColor`, are not
mirrored in RTL, and were checked at 16 and 20 px in light and dark.

| Name | Drawing |
|------|---------|
| `ai-price-image` | photo frame (sun + hill) with a price tag on the corner |
| `ai-market-prices` | shop front with an awning and a magnifier |
| `ai-titles` | three text lines with a 4-point sparkle |

```blade
<x-ui.icon name="ai-price-image" class="size-4" />   {{-- in buttons --}}
<x-ui.icon name="ai-market-prices" class="size-5" /> {{-- in tabs --}}
```
JS (no Blade): copy the markup from `Icons::markup('ai-titles')` into a `<template>` on the page, or render the icons server-side in the tab buttons.

**Price-source badges** are in `backend/resources/css/panel.css` next to the other `.badge-*` classes. Use the base `.badge-src` (sm pill
`text-[11px]/4`, with a dot from `::before`, so you need no dot markup) plus a modifier named after the API `price_source` value:

| Class | `price_source` | Tone | Contrast, light / dark |
|-------|----------------|------|------------------------|
| `badge-src-structured_data` | `structured_data` | success | 5.95 / 9.76 |
| `badge-src-page_text` | `page_text` | info | 6.45 / 9.10 |
| `badge-src-snippet` | `snippet` | warning | 5.79 / 11.25 |
| `badge-src-none` | `none` | neutral: no fill, dashed border, hollow dot | text 7.91 / 10.88; border 5.09 / 6.44 |

- `none` is drawn as an outline only (dashed border, hollow dot, `font-medium` muted text), so it reads as "missing", never as a value or `0`.
  It differs from 7.9 on purpose: the border uses `panel-text-subtle`, not `border-strong`, because `border-strong` is only 1.67:1 and fails the 3:1 non-text contrast rule.
- When the badge is a `<button>` (`page_text`, `snippet` quote toggle), it gets `cursor-pointer`, a border on hover and on `aria-expanded="true"`, and
  the panel's normal `:focus-visible` outline.
- All tokens switch with `[data-theme="dark"]` automatically.

```blade
<span class="badge-src badge-src-{{ $row['price_source'] }}" title="{{ __('admin.product_ai.source_'.$row['price_source'].'_hint') }}">{{ __('admin.product_ai.source_'.$row['price_source']) }}</span>
<button type="button" class="badge-src badge-src-page_text" aria-expanded="false" aria-controls="quote-3">…</button>
```
```js
el.className = `badge-src badge-src-${row.price_source ?? 'none'}`;
```
*Other site* stays the plain `<x-ui.badge tone="neutral" size="sm">` (no dot), as 7.9 says.

## 8. Progress log

- [x] Plan written
- [x] 1 UI/UX (section 7, 2026-11-27)
- [x] 2 Graphic (7.11, 2026-11-27)
- [x] 3 AI dev (backend, deploy files, docs/FREELLMAPI_SETUP.md, tests; API in 3.1, 2026-11-27)
- [x] 4 Frontend (card, dialog `_product-ai.blade.php`, `resources/js/lib/product-ai.js`, lang 7.10, `ProductAiFormTest`; 2026-11-27)
- [ ] 5 QA
- [ ] 6 QC
