# Meta Marketing API integration (P6, MCP.md §7, §8)

The client lives in Laravel only. The MCP server (P7) gets read + draft endpoints; approval and publishing are marketer-panel actions.

## Safety policy (enforced in code)
- Campaign, ad set and ad are always created **PAUSED**. `MetaGraphClient` has two ways to reach Meta: `get()` and `create($edge, …)`, where `$edge` is one of `campaigns|adsets|adcreatives|ads|adimages` under the configured ad account. A status other than `PAUSED` on campaigns / ad sets / ads, an unknown edge, or a forbidden parameter (`spend_cap`, `execution_options`, …) throws before any HTTP call.
- No object id can be targeted by a write, so **no update (activate, budget), and no delete exists**. Activation is manual, in Ads Manager.
- Statuses are read back after creation. If Meta does not report `PAUSED`, the run is marked `failed` with audit action `status_not_paused`, and the app changes nothing in Meta: **check Ads Manager**.
- The token is sent as an `Authorization: Bearer` header, never in a URL, response or log; Meta error messages are scrubbed. `appsecret_proof` is added when `META_APP_SECRET` is set.

## Configuration (`config/meta.php`, `.env`)
`META_ACCESS_TOKEN` (System User, `ads_management` + `ads_read`), `META_AD_ACCOUNT_ID`, `META_PAGE_ID`, `META_APP_ID`, `META_APP_SECRET`, optional `META_INSTAGRAM_USER_ID`, `META_GRAPH_VERSION`, `META_GRAPH_URL`, `META_CURRENCY`, `META_CACHE_MINUTES`, `META_APPROVAL_VALID_HOURS`, `META_MIN_DAILY_BUDGET`, `META_MAX_DAILY_BUDGET`, `META_MAX_LIFETIME_BUDGET`.

**Before going live, verify against Meta's current documentation:** `META_GRAPH_VERSION` (default `v26.0`), objectives, optimisation goals, CTAs, `is_adset_budget_sharing_enabled`, `targeting_automation`. These payloads have only been tested against mocks (`Http::fake`); no real Meta call has ever been made from this code.

## Lifecycle
`draft` → (submit) `pending_approval` → (approve) `approved` → (publish) `publishing` → `published`. `rejected` / `failed` can be edited; `archived` is final.

| Rule | Implementation |
|---|---|
| Hash | `MetaCampaignDraftService::hash`: sha256 of every field sent to Meta, recomputed from the stored row |
| Approval | `meta_campaign_approvals` row bound to (`draft_version`, `content_hash`), expires after `META_APPROVAL_VALID_HOURS` (72 h) |
| Edit | Any content change: version + 1, approvals invalidated, back to `draft` |
| Tamper | Publish recomputes the hash and refuses (`hash_mismatch`) if draft or approval differ |
| Frozen | Once any Meta id is stored, the content cannot be edited |
| Idempotency | One key per approval (`publish:{id}:v{version}:{hash16}`); a succeeded key returns the stored result with no call |
| Resume | Each Meta id is stored as soon as it is returned; a retry skips finished steps |
| Interrupted | A run stuck in `publishing` for 15 min without progress becomes `failed` on the next publish attempt, then resumes |
| Audit | `meta_audit_logs`: draft, approval and publish actions and every Graph call (path + HTTP status only), with admin or token identity |

Residual risk: if the process dies after Meta creates an object but before its id is stored, a retry creates a duplicate (paused, so no spend). Meta offers no idempotency key on these edges.

## What is sent
Campaign (`status=PAUSED`, objective, `special_ad_categories=[]`, AUCTION) → ad set (`status=PAUSED`, daily or lifetime budget in minor units, targeting, schedule) → optional image upload → creative (`object_story_spec` with page id, link data, CTA) → ad (`status=PAUSED`). The show page has a "what will be sent" preview.

Limits of this version:
- Interests and cities are review notes only; they need Meta ids and are not sent as targeting. Countries, age, gender and placements are sent.
- Only images are uploaded. Video creatives are not; the draft warns, and the video is added manually in Ads Manager.
- `special_ad_categories` is always empty (not suitable for housing, credit or employment ads).

## Panel (`/marketer/meta-ads`)
| Route | Permission |
|---|---|
| list + account panels, `insights`, `{id}` | `meta_ads.view` |
| `create`, `validate`, store, `{id}/edit`, update, `{id}/submit`, `{id}/archive` | `meta_ads.draft` |
| `{id}/approve`, `{id}/reject` | `meta_ads.approve` |
| `{id}/publish` (10/min) | `meta_ads.publish` (owner / super-admin; **not** in the preset `marketer` role); also checked by `MetaPublisher` |

Without a connection the pages still work: drafts and approval run, publishing is hidden and refused.

## Internal API
See API_DOCUMENTATION.md §9. Reads are cached (`META_CACHE_MINUTES`). There is deliberately no approve, reject, publish or activate endpoint.

## Tests
`tests/Feature/MetaAdsTest.php`, mocked with `Http::fake` only.
