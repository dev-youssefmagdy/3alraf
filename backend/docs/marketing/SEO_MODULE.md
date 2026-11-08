# SEO module (P5, MCP.md §19)

SEO suggestions (`type = seo`) — one reviewable suggestion per entity (bulk analysis = many suggestions; nothing is bulk-updated silently).
Handler `App\Services\Marketing\Suggestions\Types\SeoSuggestionType`; approval needs `ai_suggestions.approve` **and** `ai_seo.approve`.
Read side for Claude: `GET /api/internal/marketing/seo-data?entity_type=…` (P4).

## What is applied vs advisory
| Payload | Applied? |
|---|---|
| `meta_title` {locale}, `meta_description` {locale} | yes — only the locales sent; other locales keep their value |
| `slug` | yes — through the entity's own service; old URL ⇒ 301 |
| `notes.h1` | advisory: the H1 is the entity name (change it with a product suggestion / in the admin) |
| `notes.image_alt` | advisory: the `images` table has no alt column (storefront uses the product name) |
| `notes.content_improvements`, `structured_data`, `internal_links`, `keywords`, `risks`, `expected_benefit` | advisory (shown in compare / preview) |

## Field mapping per entity
| target_entity_type | meta title | meta description | slug (service) | storefront URL |
|---|---|---|---|---|
| `product` | `products.content.meta_title` (storefront wording overlay; catalog `meta_title` untouched) — CatalogService::updateContent | `products.content.meta_description` | ProductService::changeSlug | `/{locale}/product/{slug}` |
| `blog_post` | `seo_metas.meta_title` (SeoMetaService) | `blog_posts.meta_description` (BlogPostService::update) | BlogPostService::update | `/{locale}/blog/{slug}` |
| `category` | `seo_metas` | `seo_metas` | CategoryService::update | `/{locale}/category/{slug}` |
| `brand` | `seo_metas` | `seo_metas` | BrandService::update | `/{locale}/brand/{slug}` |
| `bundle` | `seo_metas` | `seo_metas` | BundleService::update (BundleData `slug`) | `/{locale}/bundle/{slug}` |
| `static_page` | `seo_metas` | `seo_metas` | StaticPageService::update | `/{locale}/page/{slug}` |
| `custom_page` | `seo_metas` | `seo_metas` | CustomPageService::update (reserved slugs refused) | `/{slug}` (no locale) |
| `site` (no id) | setting `seo.home_title` — SeoSettingsService::update | `seo.home_description` | — | `/{locale}` |

`seo_metas` (entity_type, entity_id unique, meta_title / meta_description per locale) is read by `<x-layouts.storefront>` through
`SeoMetaService::forRequest()` for the current route (category / brand / bundle / page / blog / custom page) — one indexed query on those pages
only; when present it replaces the page title (the site suffix is still added) and the meta description. Product overlays are already applied
by the product page (`Product::applyContent`).

## Slug changes and redirects
- Validation: `^[a-z0-9]+(-[a-z0-9]+)*$`, ≤ 150; must not exist on another row of the same table (soft-deleted rows included); custom pages:
  not a reserved path (`App\Support\ReservedSlugs`). Re-checked at apply (a slug taken meanwhile ⇒ apply fails, nothing changed).
- `seo_redirects` (from_path unique, to_path, localized, entity, hits, last_hit_at, suggestion_id, created_by): localized paths are stored without
  the locale (`product/old`), so every language redirects. Chains are collapsed (A→B, B→C ⇒ A→C, B→C); a path that becomes live again loses
  its redirect.
- `App\Http\Middleware\SeoRedirects` (alias `seo.redirects`, on the storefront route group) acts **only when a GET/HEAD storefront page answers
  404**: looks the path up and answers `301` to the current path with the query string kept. No lookup on normal pages (and none at all while the
  table is empty — cached count).
- Rollback restores the slug and undoes the redirect (older redirects point back to the restored path).

## Revision log of old / new values
Every apply stores `before` / `after` (meta per locale + slug + redirect id) on `marketing_suggestion_executions`; the suggestion keeps every
version in `marketing_suggestion_revisions`; product overlay changes are also audited by the Product activity log.

## Avoiding accidental deindexing
SEO suggestions never touch `robots` / `indexable` (setting `seo.indexable` stays an admin setting); slugs always get a 301; the seasonal theme
preview pages are `noindex,nofollow`.
