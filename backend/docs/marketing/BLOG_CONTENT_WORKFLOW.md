# Blog content workflow (P5, MCP.md §20)

1. Claude reads `blog-posts` (+ products, categories, seasonal calendar) through the Marketing API and creates `POST /suggestions/blog`
   (schema: MCP_SUGGESTION_TOOLS.md). Duplicate checks at create, approve and apply: same `slug` as an existing post, or the same title (any
   locale, case-insensitive) ⇒ refused. Content HTML is sanitised by `App\Support\Marketing\RichText` (allowlist p, br, ul, ol, li, strong,
   em, b, i, h2–h4, blockquote, a[href internal]; everything else removed; scripts / iframes / images / event handlers / external links dropped).
2. Marketer panel → **اقتراحات البلوجز** (`/marketer/suggestions/blog`): open, preview the full article per language (sanitised render, outline,
   keywords, internal links), edit title / content / SEO / slug / links (new version each save), approve (`ai_suggestions.approve` +
   `ai_blog.approve`). Blog categories do not exist in this store: `category` is kept as advisory text.
3. **Apply = save as blog DRAFT**: `BlogPostService::create` with `is_active = false`, `published_at = null` (+ `meta_title` in `seo_metas`).
   Approving or applying never publishes. The post id is stored on the suggestion (`result_entity_type = blog_post`).
4. Separate explicit actions on the applied suggestion (perms `ai_suggestions.apply` + `ai_blog.approve`):
   - **Publish now** (confirmation dialog): `is_active = true`, `published_at = now` — execution `publish`.
   - **Schedule**: `is_active = true`, `published_at` = the chosen future date — execution `schedule`; `BlogPost::scopePublished` shows the post
     from that moment (no job needed).
5. Rollback deletes the post only while it is still a draft; a published / scheduled post must be unpublished in Blog first.
The storefront blog page now renders bodies with `RichText::render` (internal links keep their `href`; other markup as before).
