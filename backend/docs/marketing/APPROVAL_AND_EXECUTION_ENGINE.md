# Approval and execution engine (P5, MCP.md §16.3, §23, §26)

One reusable engine — `App\Services\Marketing\Suggestions\SuggestionEngine` — for every suggestion type; each type contributes only an explicit
schema + an explicit application handler (`Contracts\SuggestionType`, registered in `SuggestionTypeRegistry`). Handlers never change status,
approvals or logs; the engine never writes store data itself.

## Tables
| Table | Purpose |
|---|---|
| `marketing_suggestions` | the suggestion: type, title, description, reasoning, target (type, id), `payload` (validated per type), `source_data_snapshot` (references the AI used), `target_snapshot` + `target_snapshot_hash` (stale detection), priority, status, source (mcp\|panel\|scheduled), `source_meta` (model, tool, token id…), created_by_token_id / created_by_admin_id, reviewed_by, approved_by, applied_by, `version`, `content_hash`, result entity (type, id), last_error, submitted/reviewed/expires/approved/applied/rejected/rolled_back timestamps. Indexes: (type, status), status, (target type, id), (type, content_hash), created_at. |
| `marketing_suggestion_revisions` | one row per version: title, payload, content_hash, editor (admin\|token\|system + id), diff [{field, from, to}], note |
| `marketing_suggestion_approvals` | decision (approved\|rejected), version, content_hash, admin, note, expires_at, invalidated_at + reason |
| `marketing_suggestion_executions` | kind (apply\|rollback\|publish\|schedule), **unique idempotency_key**, status (running\|succeeded\|failed), version, content_hash, options, before, after, warnings, error, executed_by, rollback_of, started/finished |
| `marketing_suggestion_activity_logs` | append-only action trail (action, actor type/id, meta) |
| `marketing_themes`, `seo_redirects`, `seo_metas` | produced by the theme / SEO handlers (SEASONAL_THEME_MANAGEMENT.md, SEO_MODULE.md) |

## Hashes
- `content_hash = sha256(canonical JSON {type, target: [type, id], payload})` — canonical = assoc keys sorted recursively, list order kept,
  after the handler's `normalize()` (trim, sanitise, lower-case colours, site-relative links, sorted id sets). Title / reasoning are not part of it.
  Used for the approval binding and duplicate prevention.
- `target_snapshot_hash = sha256(canonical snapshot)`; the snapshot holds ONLY the target values the payload touches (e.g. the current meta
  title of the locales being changed), so unrelated edits of the entity never make a suggestion stale.

## Create (`create`)
type exists → target type allowed → `normalize` → target resolved (required ones must exist, soft-deleted = missing) → `check` (business rules;
errors ⇒ 422, warnings returned) → hash → open duplicate ⇒ 409 → rejected < 30 days without `force_reason` ⇒ 409 → snapshot → row + revision v1 +
activity. API requests are validated first by `StoreSuggestionRequest` (type rules + common fields + **unknown-field rejection**).

## Approve / reject
Route permission (`ai_suggestions.approve` / `.reject`) **and** the type's own permission (`ai_seo.approve`, `ai_blog.approve`,
`ai_theme.approve`) checked again inside the engine. Approve re-runs `check` (a suggestion that became invalid cannot be approved), requires
`submitted_at`, not expired; previous approvals are invalidated (`superseded`). Reject stores a `rejected` decision with the note.

## Apply (`apply`)
1. Permission `ai_suggestions.apply` + the type permission; type must be `applicable()` (all types are, since P6).
2. Idempotency key = the form's UUID (`k:{uuid}`) or `apply:{id}:v{version}:{hash16}`. Existing execution with that key ⇒ returned as is.
3. Transaction 1 (suggestion row `FOR UPDATE`): already `applied` ⇒ previous execution; status must be approved/failed; valid approval for the
   current version + hash; not expired; target exists; **stale check** (snapshot hash) ⇒ `409 stale_target` with `{snapshot, current}` and a
   `conflict` activity; then `applying` + execution `running` (unique key — a concurrent duplicate hits the unique index and gets the first one).
4. Transaction 2: `handler->apply()` through the store services; it re-validates (`check`) and throws on errors.
5. Success ⇒ execution `succeeded` with `before` / `after` / warnings, suggestion `applied` (+ result entity). Any exception ⇒ the handler's
   transaction is rolled back, execution `failed` with the error, suggestion `failed` (+ `last_error`); never `applied`. Retry = apply again
   (failed → applying) while the approval is valid; editing the payload needs a new approval.
Stale conflicts are resolved by **re-snapshot** (panel button, perm `ai_suggestions.edit`): new snapshot, version + 1, approvals invalidated,
status `edited` ⇒ review the compare again and re-approve.

## Rollback (`rollback`)
Only `applied` suggestions of types with `supportsRollback()`; restores the execution's `before` through the services; one `rollback`
execution per apply (key `rollback:{apply id}` ⇒ idempotent); suggestion → `archived` + `rolled_back_at`.

| Type | Apply | Rollback |
|---|---|---|
| product | storefront wording overlay (CatalogService::updateContent), tags (ProductService::syncTags), gift tags (ProductService::updateGiftTags), list pins (ProductListService::pin/unpin) | restores overlay, tags, gift tags, exact pin order |
| bundle | create INACTIVE bundle (BundleService::create) or update proposed fields (BundleService::update) | created ⇒ soft-deleted; updated ⇒ previous values |
| slider | create INACTIVE banner (BannerService) or update; option *activate* (refused without image) | created ⇒ deleted; updated ⇒ previous values |
| offer | create flash sale (FlashSaleService) inactive or active with option *activate* (runs from starts_at); update | created ⇒ deleted (or deactivated if units were sold); updated ⇒ previous |
| coupon | create discount code (DiscountCodeService) inactive / *activate*; update | created ⇒ deleted (or deactivated if used); updated ⇒ previous |
| seo | meta per entity mapping + slug through the entity service + 301 (SEO_MODULE.md) | meta + slug restored, redirect undone |
| blog | create DRAFT post (BlogPostService) + meta_title (SeoMetaService); then panel *publish now* / *schedule* (executions `publish` / `schedule`) | draft deleted; refused once published |
| seasonal_theme | new `marketing_themes` version, activated (ThemeService) | the previously active version (or defaults) |
| social_content, media_asset | private draft item in the social-media library (`social_media_asset`), never published | archive that item |

## Permissions (guard admin)
| Action | Route permission | + engine |
|---|---|---|
| view pages / detail / log | ai_suggestions.view | — |
| edit, start review, re-snapshot | ai_suggestions.edit | — |
| approve | ai_suggestions.approve | type permission (seo/blog/theme) |
| reject | ai_suggestions.reject | — |
| apply, rollback | ai_suggestions.apply | type permission |
| blog publish / schedule | ai_suggestions.apply + ai_blog.approve | — |
| archive | ai_suggestions.archive | — |
| theme activate / revert / off | ai_theme.approve | — |
Owner / super-admin and the preset role `marketer` hold all of them. The API (MCP) holds none: tokens can only create / edit own drafts / submit.

## Code map
`app/Services/Marketing/Suggestions/{SuggestionEngine, SuggestionTypeRegistry, SuggestionStateMachine, SuggestionHasher, SuggestionActor,
SuggestionException, ApplyResult, EntityDtos, Contracts/SuggestionType, Types/*}`, `app/Models/MarketingSuggestion*`,
`app/Repositories/*/MarketingSuggestionRepository*`, `app/Http/Controllers/Marketer/SuggestionController`, `app/Http/Controllers/Api/Marketing/SuggestionController`,
`app/Console/Commands/ExpireMarketingSuggestions`. Config `marketing.suggestions.*`. Tests `tests/Feature/MarketingSuggestions{Api,Engine}Test.php`.
