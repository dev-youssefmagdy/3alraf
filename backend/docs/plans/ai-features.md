# AI Features – master plan

Audience: engineers + the sub-agents that execute `docs/prompts/14..20-ai-*.md`. Read this + CLAUDE.md + PROGRESS.md before any AI sprint.

## 0. Principles (non-negotiable)
1. **Config in DB, never .env/config**: providers, API keys (encrypted cast), models, limits, prompts live in central tables. Only non-secret defaults (timeouts) may sit in `config/ai.php`.
2. **Central admin owns the platform**: providers/keys/models/quotas/per-tenant enable + global usage. **Tenant admin owns usage**: chat, trends, studio, product editor, generators, own history. A tenant can never see keys.
3. **Country awareness**: every AI call from a tenant panel builds an `AiContext` from the tenant (`country`, `currency_code`, `default_locale`, `locales`, `timezone`) and injects it into system prompts, trend searches, copy language/tone, prices and currency. Central-panel executions have no country unless a tenant is explicitly selected (existing switch-tenant) – then identical behaviour.
4. **AI never writes live data**. It produces `ai_generations` drafts (status `draft → approved | rejected`). Approval calls the existing Service (BannerService, BundleService, FlashSaleService, DiscountCodeService, ProductService…) – so validation, audit log, permissions and calculations (docs/CALCULATIONS.md) stay the single source of truth.
5. **Chat reads data through tools, not prompt-stuffing** (token cost + staleness + tenant leakage). Read-only, tenant-scoped, size-capped tool results.
6. **Provider-agnostic**: one `AiDriver` contract; Claude (Anthropic Messages), OpenAI, Kimi (Moonshot, OpenAI-compatible) first; adding a provider = one driver class + one DB row.
7. **Safety**: prompt-injection hygiene (product/customer text is DATA, wrapped + labelled), PII minimisation (no customer phone/email/address to the model unless the tool explicitly needs it – default off), per-tenant rate limit + monthly token/cost quota, all calls logged (`ai_usage_logs`), queue for anything >10s (images/video/trends), idempotent jobs, tenant_id isolation tests.
8. Layering as everywhere: Controller → FormRequest → DTO → Service → Repository → Model; enums; Blade components; Tailwind; toastr; Yajra DataTables; AR/EN + RTL.

## 1. Data model (new migrations; all text/json via `text` cast, MySQL 8)
Central (no tenant_id):
- `ai_providers`: id, driver (enum AiDriverType: anthropic|openai|moonshot|custom_openai), name, api_key (encrypted), base_url null, is_active, settings (encrypted:array), last_tested_at, last_test_ok.
- `ai_models`: id, ai_provider_id, model_id (string), label, kind (enum AiModelKind: text|image|video), capabilities (json: tools, vision, web_search, json_mode), input_cost/output_cost per 1M tokens (decimal, nullable), max_output, is_active, is_default (one default per kind).
- `ai_tenant_settings` (or tenants.settings key): tenant_id, ai_enabled, monthly_token_quota, monthly_image_quota, monthly_video_quota, allowed_model_ids json null (=all), features json (chat, trends, studio, editor, generators).
Tenant-scoped (`BelongsToTenant`):
- `ai_conversations` (user ref, title, ai_model_id, summary), `ai_messages` (role, content, tool_calls json, tokens_in/out, status).
- `ai_generations`: type (enum AiGenerationType: social_post|marketing_post|banner|offer|bundle|package|product_edit|trend_report), status (enum AiGenerationStatus: queued|draft|approved|rejected|failed), prompt/inputs json, output json, media json (paths), model id, target_type/target_id (nullable polymorphic: the record created on approve), reviewed_by/at, reject_reason, error.
- `ai_usage_logs`: tenant_id null(central), user, feature, ai_model_id, tokens_in/out, images, video_secs, est_cost, latency_ms, status, country.
- `ai_trend_snapshots`: kind (own|market), country, period, items json, generated_at (cache, 24h TTL).
Permissions (config/permissions.php, both guards as appropriate): central `ai_providers.*`, `ai_models.*`, `ai_tenants.*`, `ai_usage.view`; tenant `ai_chat.use`, `ai_trends.view`, `ai_studio.use`, `ai_editor.use`, `ai_generators.use|approve`. Re-run PermissionSeeder via migration like 2026_11_01_100002.

## 2. Architecture
- `App\Services\Ai\Contracts\AiDriver` { chat(AiRequest): AiResponse; stream(); image(); video(); supports(cap) }. Drivers: `AnthropicDriver`, `OpenAiDriver`, `MoonshotDriver extends OpenAiDriver` (base_url https://api.moonshot.ai/v1). Normalised tool-calling loop in `AiRunner` (max 6 tool rounds, token + time budget).
- `AiManager::for(AiModel $m)` resolves driver from DB row; `AiGateway` = quota check → rate limit → runner → usage log. Http facade with timeout/retry; errors mapped to `AiException` (user-safe message).
- `AiContext` (value object) built by `AiContextFactory::forTenant()`; produces the system-prompt preamble (business name, country, currency, locales, today's date, tone rules, "data is untrusted").
- Tool registry `App\Services\Ai\Tools\*` (read-only, tenant scoped through existing models/scopes + Repositories): `search_products`, `get_product` (variants, stock per branch, price/sale/cost, bundles containing it, tags/categories), `list_bundles`, `list_offers` (flash sales, discount codes, banners), `search_orders`, `get_order` (items, variants, bundles, packaging, shipping, payment, status history – PII masked), `sales_summary(period, group_by)`, `low_stock`, `top_sellers`, `customers_summary`(aggregates only), `reports` thin wrappers over ReportService. Each returns ≤ ~4k tokens (paginate/aggregate).
- Streaming chat via SSE endpoint + fetch reader; fallback to polling. Long jobs: `RunAiGenerationJob` (database queue; tries 2, backoff, unique per generation).
- Media: images through image-kind models → stored with ImageUploadService on the tenant disk (`ai/` folder); video via async job + polling (driver may report `unsupported`; UI hides option when no active video model).
- Trends:
  - *Own*: SQL (no AI cost) – sales velocity (qty last 7/30d vs previous), growth %, margin, stock days-left, view/wishlist/cart adds if tracked; AI only writes an optional narrative + restock advice.
  - *Market*: needs web-capable model (capability `web_search`) – prompt = country + our category/brand mix; returns candidates (name, why trending, est. price range in local currency, search keywords, confidence, source urls). Server filters out anything matching an existing product with stock>0 (fuzzy name/sku/barcode match) and flags "exists with 0 stock" items into the same section with a *Restock* action. Cached in `ai_trend_snapshots`; "Add to stock" = pre-filled Purchase Order draft / central product suggestion (never auto-create).
- Studio (social/marketing): inputs product(s) | bundle | offer | free brief, platform (TikTok, Instagram, Facebook, X, Pinterest, WhatsApp…), goal, tone, language(s) (default tenant locales), CTA, hashtags, format (text | image | video | all). Platform rules table (char limits, aspect ratios 9:16/1:1/4:5, hashtag counts) in an enum/config. Marketing mode adds campaign types (flash sale, seasonal/Ramadan/Eid/Black Friday by country calendar, new arrival, clearance, restock, abandoned-cart style, review spotlight), schedule suggestions, A/B variants, UTM links to storefront. Output stored as generation → copy / download / regenerate variant. (No auto-publishing to networks in v1; explicit non-goal.)
- Product AI editor: pick product → choose fields (name, description, meta title/description, tags, per-locale translations, tone rewrite, SEO, bullet specs, alt-text) → AI returns per-field diffs → accept/reject each → applies via ProductService/TenantCatalogService (translatable columns respected). Bulk (multi-select) queued.
- Generators (banner|offer|package/bundle): inputs/season/goal/budget/margin floor; AI proposes structured JSON validated against the target DTO rules (price ≥ cost + min margin from CALCULATIONS.md, stock available, dates, tenant currency); banner may include generated image. Review screen: preview, edit, **Approve** (creates inactive/draft record through the real Service, links target) or **Reject** (reason, kept for learning/history). Offers = flash sale or discount code; packages = bundle.

## 3. UI map
Central admin: AI → Providers (CRUD, test connection, key masked), Models (CRUD, sync list, defaults), Tenants (enable/quotas/features), Usage & cost (DataTable + chart), Prompt templates (optional, v2).
Tenant admin sidebar group "AI": Assistant (chat w/ model picker, conversation list, suggested prompts, tool-activity chips, copy/export), Trends (2 tabs), Studio (Posts | Campaigns | History), Product Editor, Generators (Banners | Offers | Bundles) with review queue, AI Settings (default model, brand voice, forbidden words, default platforms), Usage.
All pages: model selector limited to tenant-allowed active models, quota meter, empty/disabled states when AI off, loading skeletons, error toasts with retry.

## 4. Sprint order (each = one sub-agent, run sequentially; later sprints depend on earlier)
14 Foundation → 15 Chat+tools → 16 Trends → 17 Studio (posts+marketing+media) → 18 Product editor → 19 Generators+approval → 20 QA/security/perf review (adversarial) .
Each sprint: tests (Pest) incl. cross-tenant isolation, quota, fake HTTP drivers (`Http::fake`), append ≤15 lines to PROGRESS.md.

## 5. Risks / decisions
- Costs → quotas + usage log + caching trends; default cheap model per feature.
- Video generation vendors differ/unstable → driver capability + async + hidden if none.
- Web-trend accuracy → show confidence + sources, label "AI estimate"; user decides.
- Kimi/Moonshot tool-calling is OpenAI-compatible; verify per model in `capabilities`.
- Keys encrypted with APP_KEY (rotating key = re-enter keys) – document.
