# AI Suggestions Center — architecture (P5, MCP.md §16, §23, §26)

```mermaid
flowchart LR
    C[Claude (Pro connector)] -->|MCP tools| M[mcp-server P7]
    N[n8n / scheduler] -->|service token| A
    M -->|Bearer mkt_… · marketing:read / suggestions:write| A[/api/internal/marketing/suggestions*/]
    A --> E[SuggestionEngine]
    P[Marketer panel /marketer/suggestions] -->|session · ai_suggestions.* perms| E
    E --> R[SuggestionTypeRegistry] --> H[Type handlers]
    H -->|store services only| S[(ProductService · CatalogService · ProductListService · BundleService · BannerService · FlashSaleService · DiscountCodeService · BlogPostService · Category/Brand/StaticPage/CustomPage services · SeoSettingsService · ThemeService · SeoMetaService · SeoRedirectService)]
    E --> T[(marketing_suggestions · _revisions · _approvals · _executions · _activity_logs)]
```

- **Laravel is the source of truth**; the MCP server and n8n only call the API. Claude can create / edit its own drafts / submit; it can never
  approve, reject or apply (no such route — tested). Approve / apply are panel actions guarded by permissions + CSRF.
- **Types** (`App\Enums\Marketing\SuggestionTypeKey`): product, bundle, slider, offer, coupon, seo, blog, seasonal_theme, social_content,
  media_asset — each one class implementing `Contracts\SuggestionType` (schema rules, normalize, check, snapshot, compare, apply, rollback,
  approve permission, edit fields). Adding a type = enum case + handler + registry entry + lang strings + a page mapping.
- **Data model**: APPROVAL_AND_EXECUTION_ENGINE.md (tables, hashes, idempotency, stale detection). Lifecycle: SUGGESTION_LIFECYCLE.md.
- **Untrusted input**: suggestion text is AI output ⇒ stored as plain text or allowlisted HTML (`RichText`), links only to this store
  (`InternalLink`), colours only whitelisted tokens + hex, no SQL / table / path / arbitrary URL field, unknown fields refused
  (`UnknownFields`), payload validated by the type's rules on create AND edit, re-checked at approve AND apply. Panel output is escaped; no
  external resource is loaded in previews.
- **Panel pages** (`routes/marketer.php`, `marketer.suggestions.*`, nav group "الاقتراحات"): overview (`/marketer/suggestions`: KPIs by status,
  counts by type, recent activity, filtered Yajra table — status, type, priority, date range, search, product id, category, sort), one page per
  group (`products`, `bundles`, `banners`, `offers`, `coupons`, `themes` (+ theme versions / revert), `seo` (+ target filter), `blog`, `social`),
  detail `/{id}` (status + badges, actions by permission, warnings / errors, stale banner, compare current vs proposed, rich preview, payload,
  revisions, approvals, executions, activity), edit `/{id}/edit` (per-type fields, AJAX pre-validation), log `/log` (executions + decisions).
  Dashboard card "pending suggestions" counts `status = pending`.
- **Scheduled**: `marketing:expire-suggestions` daily 03:25. Scheduled generation (n8n, MCP.md §25) creates suggestions through the same API
  with `source = scheduled` (out of scope for P5 by owner decision; the API supports it).
- Code map: APPROVAL_AND_EXECUTION_ENGINE.md § Code map; API contract: MCP_SUGGESTION_TOOLS.md.
