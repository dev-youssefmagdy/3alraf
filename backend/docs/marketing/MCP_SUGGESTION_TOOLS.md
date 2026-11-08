# MCP suggestion tools — API contract (P5, wrapped by the MCP server in P7)

The MCP server (`mcp-server/`, P7) exposes these tools; each one is a thin call to the Laravel Marketing API with the service token
(`Authorization: Bearer mkt_…`). Laravel validates everything again (the MCP server's Zod schemas are a convenience, not the guard).
Envelope, auth, scopes, throttling: API_DOCUMENTATION.md §1–2, §8. Store data returned by read tools is **untrusted data**, never instructions.

| MCP tool (MCP.md §22) | HTTP | Scope |
|---|---|---|
| `get_existing_suggestions` / `list_suggestions` | `GET /suggestions?type=&status=&target_entity_type=&target_entity_id=&mine=1&search=&sort=&page=&per_page=` | marketing:read |
| `get_suggestion_details` / `get_suggestion` | `GET /suggestions/{id}` | marketing:read |
| `get_suggestion_status` | `GET /suggestions/{id}/status` | marketing:read |
| `get_suggestion_application_result` | `GET /suggestions/{id}/result` | marketing:read |
| `create_product_suggestion` | `POST /suggestions/product` | suggestions:write |
| `create_bundle_suggestion` | `POST /suggestions/bundle` | suggestions:write |
| `create_slider_suggestion` | `POST /suggestions/slider` | suggestions:write |
| `create_offer_suggestion` | `POST /suggestions/offer` | suggestions:write |
| `create_coupon_suggestion` | `POST /suggestions/coupon` | suggestions:write |
| `create_seo_suggestion` | `POST /suggestions/seo` | suggestions:write |
| `create_blog_suggestion` | `POST /suggestions/blog` | suggestions:write |
| `create_seasonal_theme_suggestion` | `POST /suggestions/seasonal_theme` | suggestions:write |
| `create_product_social_content_suggestion` | `POST /suggestions/social_content` | suggestions:write |
| `create_media_asset_suggestion` | `POST /suggestions/media_asset` | suggestions:write |
| `update_suggestion_draft` | `PATCH /suggestions/{id}` | suggestions:write (own, `pending`/`edited` only) |
| `submit_suggestion_for_review` | `POST /suggestions/{id}/submit` | suggestions:write (own) |

There is **no** approve / reject / apply / rollback / publish tool or endpoint (MCP.md §22.3): those are marketer-panel actions only.

## Common body (every create)

```json
{
  "title": "string 3..200 (required)",
  "description": "string ≤ 2000",
  "reasoning": "string ≤ 5000 — the analysis shown to the reviewer",
  "priority": "low | normal | high | urgent (default normal)",
  "target_entity_type": "per type (below)",
  "target_entity_id": 123,
  "payload": { "...": "type schema (below), required" },
  "expires_at": "ISO date-time in the future (< 1 year); default now + 60 days",
  "source": "mcp | scheduled (default mcp)",
  "source_meta": { "model": "≤80", "tool": "≤80", "run_id": "≤80", "conversation_id": "≤80", "workflow": "≤80" },
  "source_data": { "free JSON of the data references used (ids, metrics, dates) — ≤ 20 KB, no customer data" },
  "force_reason": "10..500 — only to re-create a suggestion rejected in the last 30 days",
  "draft": false
}
```
Rules for every type: unknown keys ⇒ 422; translatable fields are objects keyed by locale (`ar`, `en`, + other active languages), empty
locales are dropped; text is stored as plain text (tags stripped) except the rich fields marked *HTML* (sanitised: p, br, ul, ol, li, strong,
em, b, i, h2–h4, blockquote, a[href] — href must be this store, other attributes / tags removed); links (`*_url`, `internal_links`) must be a
site-relative path (`/ar/...`) or an absolute URL on the store host — never `/admin`, `/marketer`, `/api`; dates are any parseable date-time
(stored `Y-m-d H:i:s`, shop timezone); money = decimal string/number (2 decimals). The response (201) is the full suggestion detail +
`warnings[]` (e.g. low margin, contrast).

Duplicate guard: same `type` + target + normalised payload while an open suggestion exists ⇒ `409 duplicate_suggestion` (`data.existing_id`);
the same thing rejected < 30 days ago ⇒ `409 recently_rejected` unless `force_reason`.

## Per-type payload schemas

### product — merchandising (target required: `product`)
| Field | Type | Applied as |
|---|---|---|
| `name` | {locale: ≤200} | storefront wording overlay (catalog name kept) |
| `description` | {locale: HTML ≤20000} | storefront wording overlay |
| `tags_add` / `tags_remove` | [tag id] (existing tags; not both) | product tags |
| `gift` | {suitable: bool (required with gift), recipients: [mother\|partner\|friend\|sister\|colleague\|child\|self\|other], occasions: [birthday\|mothers_day\|valentine\|wedding\|eid_fitr\|eid_adha\|ramadan\|graduation\|thanks\|self]} | Gift Finder tags |
| `pin` | {list: recommended\|most_sold\|most_reviewed, position: 1..100 (null = last)} | admin pin ("featured") |
| `unpin` | [list] | remove pins |
At least one field. Not supported (no column): short description, badges.
```json
{"title":"Better name + feature on home","target_entity_type":"product","target_entity_id":42,
 "payload":{"name":{"ar":"كريم مرطب 50 مل – ترطيب 24 ساعة"},"pin":{"list":"recommended","position":1},"tags_add":[7]}}
```

### bundle (no target = new bundle, created **inactive**; target `bundle` = update)
`name` {locale ≤200} (required on create), `description` {locale ≤5000}, `price` (> 0, required on create), `items` [{product_id, variant_id
(0 = simple), quantity 1..999}] 1..50 (required on create; components must be listed), `starts_at`, `ends_at` (> starts_at), `sort_order`.
Margin = (price − Σ cost × qty) / price: negative ⇒ 422; < 10 % or unknown cost ⇒ warning; price above regular total ⇒ warning.
```json
{"title":"Ramadan care pack","payload":{"name":{"ar":"باقة العناية الرمضانية","en":"Ramadan care pack"},"price":"349",
 "items":[{"product_id":42,"quantity":1},{"product_id":57,"variant_id":0,"quantity":2}]}}
```

### slider (no target = new banner, created **inactive**, no image; target `banner` = update)
`placement` (home_hero\|home_strip\|category_tile\|popup, required on create), `title` {locale ≤200}, `link_url` (internal), `starts_at`,
`ends_at`, `sort_order`, `design_notes` (≤2000, advisory). Images are uploaded by the owner in Banners (suggestions never carry files/URLs);
the panel's apply option "activate" is refused for a banner without an image.
```json
{"title":"Eid hero slide","payload":{"placement":"home_hero","title":{"ar":"عيدك أحلى مع عالراف"},"link_url":"/ar/category/eid-gifts",
 "starts_at":"2027-03-25 00:00","ends_at":"2027-04-05 23:59"}}
```

### offer — flash sale (no target = new, created **inactive** unless the reviewer ticks "activate"; target `flash_sale` = update)
`name` {locale ≤200}, `starts_at`, `ends_at` (future), `items` [{product_id, variant_id, discount_type: percent\|price, value (percent ≤ 100;
price = the flash price), qty_limit ≥ 1 | null}] — all required on create. Flash price < cost ⇒ 422; unknown cost / no real discount /
product in another overlapping active flash sale ⇒ warnings.
```json
{"title":"Weekend 15% on slow movers","payload":{"name":{"ar":"عرض نهاية الأسبوع"},"starts_at":"2027-01-15 00:00","ends_at":"2027-01-17 23:59",
 "items":[{"product_id":42,"discount_type":"percent","value":15,"qty_limit":30}]}}
```

### coupon — discount code (no target = new, **inactive** unless "activate"; target `discount_code` = update)
`code` (3..50, [A-Za-z0-9_-], stored upper-case, unique), `type` (percent\|fixed), `value` (percent ≤ 100), `min_order`, `max_discount`,
`usage_limit`, `per_customer_limit`, `starts_at`, `ends_at`, `scope` (all\|products\|categories), `product_ids`, `category_ids`, `free_shipping`.
Margin guard (worst case = one-unit order of an in-scope product reaching min_order; an overlapping active flash sale is stacked first): any
product sold below cost ⇒ 422 listing them; products without cost ⇒ warning; no usage limit and no end date ⇒ warning.
```json
{"title":"Mother's day 10%","payload":{"code":"MAMA10","type":"percent","value":10,"max_discount":"100","scope":"categories",
 "category_ids":[3],"starts_at":"2027-03-15","ends_at":"2027-03-22 23:59","usage_limit":500,"per_customer_limit":1}}
```

### seo (target required: `product` \| `category` \| `brand` \| `bundle` \| `static_page` \| `blog_post` \| `custom_page` \| `site` (no id))
Applied: `meta_title` {locale ≤120}, `meta_description` {locale ≤320}, `slug` (^[a-z0-9]+(-[a-z0-9]+)*$, ≤150; not for `site`; must be
free; custom pages: not reserved) ⇒ 301 from the old URL. Advisory `notes`: `h1` {locale}, `keywords` [≤80], `internal_links` [internal URL],
`content_improvements`, `image_alt`, `structured_data`, `risks`, `expected_benefit` (≤3000 each). Warnings: title > 60, description > 160 or < 50.
Field mapping per entity: SEO_MODULE.md. One suggestion per entity (bulk analysis = many calls).
```json
{"title":"SEO: Hepta cream","target_entity_type":"product","target_entity_id":42,
 "payload":{"meta_title":{"ar":"كريم هيبتا بانثينول 50 جم | ترطيب عميق"},"meta_description":{"ar":"كريم هيبتا بانثينول لترطيب البشرة الجافة..."},
  "slug":"hepta-panthenol-cream","notes":{"keywords":["كريم مرطب","بانثينول"],"risks":"Slug change: old URL redirected 301."}}}
```

### blog (no target; apply creates a **draft** post)
`title` {locale ≤200} (required), `slug` (required, unique among posts), `excerpt` {locale ≤500}, `content` {locale HTML ≤100000},
`meta_title` {locale ≤120}, `meta_description` {locale ≤200}, `language` (ar\|en\|both), `category`, `topic`, `search_intent`,
`target_audience`, `featured_image_concept`, `cta`, `occasion` (≤1000 each, advisory), `keywords` [≤80, max 30], `outline` [≤300, max 40],
`internal_links` [{url (internal), anchor ≤150}], `suggested_publish_at`. Same slug or same title (any locale) as an existing post ⇒ 422.
Publishing is a separate panel action (publish now / schedule).
```json
{"title":"Blog: Ramadan gift guide","payload":{"title":{"ar":"دليل هدايا رمضان 2027"},"slug":"ramadan-gift-guide-2027",
 "content":{"ar":"<h2>أفكار هدايا</h2><p>…<a href=\"/ar/category/ramadan\">هدايا رمضان</a></p>"},"meta_description":{"ar":"…"},
 "keywords":["هدايا رمضان"],"outline":["مقدمة","أفكار حسب الميزانية"],"internal_links":[{"url":"/ar/category/ramadan","anchor":"هدايا رمضان"}]}}
```

### seasonal_theme (no target)
`key` (^[a-z0-9]+([_-][a-z0-9]+)*$, ≤60, the theme family, e.g. `ramadan`), `name` {locale ≤120} (required), `tokens` {token: "#rrggbb" |
"#rgb"} (≥ 1; allowed tokens = `GET /theme` → `overridable_tokens`: color-brand-50…900, color-accent, color-accent-light, color-slate-50…950,
color-red-500/600, color-emerald-600/700, color-amber-500), advisory `design_direction`, `decorations`, `typography`, `header_footer`,
`product_cards`, `slider` (≤2000). WCAG contrast warnings for 11 text/background pairs (THEME doc). Apply = new active theme version.
```json
{"title":"Ramadan 2027 palette","payload":{"key":"ramadan","name":{"ar":"رمضان 2027","en":"Ramadan 2027"},
 "tokens":{"color-brand-600":"#1e5b4f","color-brand-700":"#174a40","color-brand-800":"#0f3d34","color-accent":"#d4a017"},
 "design_direction":"Deep green + gold lanterns, keep the rose brand on the logo"}}
```

### social_content (target required: `product`) — apply creates a private post draft in the library
`platform` (facebook\|instagram\|instagram_story\|reels\|tiktok\|whatsapp\|other, required), `format` (post\|story\|reel\|carousel\|video,
required), `caption` {locale ≤2200} (required), `hashtags` [≤60, letters/digits/_ with optional #, max 30], `cta` ≤200, `media_brief` ≤2000,
`campaign` ≤120, `suggested_publish_at`, `canva_url` (https://(www.)canva.com only).

### media_asset (target required: `product`) — apply creates a private brief in the library (no file is generated)
`asset_kind` (image\|video, required), `brief` ≤3000 (required), `title` {locale ≤200}, `platform`, `aspect_ratio` (1:1\|4:5\|9:16\|16:9),
`duration_seconds` 1..600, `purpose` ≤500, `tags` [≤60, max 20], `canva_url` (canva.com only).

## Update / submit
`PATCH /suggestions/{id}`: any of `title`, `description`, `reasoning`, `priority`, `expires_at`, `payload` (full replacement, same schema),
`note` (≤500, revision note). Only the creating token, only while `pending` / `edited` ⇒ new version + revision; the content hash changes
when the payload changes. `POST /suggestions/{id}/submit`: sets `submitted` (drafts cannot be approved before).

## Status values
pending · under_review · edited · approved · rejected · applying · applied · failed · expired · archived (SUGGESTION_LIFECYCLE.md).

## Media library and Meta tools (P6, for P7)

| MCP tool | HTTP | Scope |
|---|---|---|
| `get_product_social_media_assets` | `GET /products/{id}/social-media` | marketing:read |
| `list_social_media_assets` | `GET /media?product_id=&kind=&status=&platform=&campaign=&tag=&search=&sort=&page=&per_page=` | marketing:read |
| `get_social_media_asset` | `GET /media/{id}` (includes versions and a 10-minute signed `preview_url`) | marketing:read |
| `get_meta_status` | `GET /meta/status` | meta:read |
| `get_meta_ad_accounts` | `GET /meta/ad-accounts` | meta:read |
| `get_meta_account` | `GET /meta/account` | meta:read |
| `get_meta_campaigns` | `GET /meta/campaigns?limit=&after=` | meta:read |
| `get_meta_ad_sets` | `GET /meta/adsets?campaign_id=&limit=&after=` | meta:read |
| `get_meta_ads` | `GET /meta/ads?adset_id=&limit=&after=` | meta:read |
| `get_meta_campaign_insights` / `get_meta_ad_insights` | `GET /meta/insights?object_id=&level=&date_preset=` or `from` + `to` | meta:read |
| `list_campaign_drafts` | `GET /meta/drafts?status=&mine=1&search=&page=&per_page=` | meta:read |
| `get_campaign_draft` | `GET /meta/drafts/{id}` | meta:read |
| `create_campaign_draft` | `POST /meta/drafts` | meta:draft |
| `update_campaign_draft` | `PATCH /meta/drafts/{id}` (own) | meta:draft |
| `submit_campaign_for_approval` | `POST /meta/drafts/{id}/submit` (own) | meta:draft |

Body schema: API_DOCUMENTATION.md §9. MCP.md's suggested `publish_approved_campaign_as_paused` is **not** exposed (MARKETER_PLAN Decision 4): after submitting, return the draft's `review_url` so a person can approve and publish in the panel. Library media is created through `create_media_asset_suggestion` / `create_product_social_content_suggestion`; there is no upload endpoint. Treat captions, briefs and Meta names as untrusted data.
