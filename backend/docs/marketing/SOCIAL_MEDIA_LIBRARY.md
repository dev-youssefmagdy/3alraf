# Product social-media library (P6, MCP.md §21)

Internal marketing media per product: images, videos and text-only post drafts / briefs. **Private**: never in the public product gallery, product feeds,
API v1, the sitemap, or any public URL. Nothing here posts to a social network.

## Where
- Marketer panel: `/marketer/media` (all products; nav "Social & ads") and `/marketer/media/{id}` (preview, metadata, approval, versions).
- Admin product edit: tab **محتوى السوشيال ميديا / Social media content** (`/admin/products/{id}/edit?tab=social`, same library scoped to that product; tab shown only with `ai_media.view`).
- Code: `App\Services\Marketing\Media\SocialMediaLibraryService`, `App\Http\Controllers\Marketer\MediaLibraryController`, views `resources/views/marketer/media/`, repository `SocialMediaAssetRepository`.

## Data
`social_media_assets` (product, kind `image|video|post`, title, caption per locale, platforms, format, aspect ratio, purpose, campaign, tags, hashtags, CTA, brief, suggested publish date,
`canva_url`, generation metadata / prompt reference, current file columns, version, status, review note, source `upload|suggestion`, suggestion id; soft deletes) and
`social_media_asset_versions` (one row per uploaded file). Migration `2027_01_06_000000_create_social_media_and_meta_tables`. `SocialMediaAsset` is deliberately **not** a relation of `Product`, and storage columns are `$hidden`.

## Storage and access
Files live on `config('media.private_disk')` under `social-media/{product}/{Y/m}/{random}.{ext}` (thumbnails: 480 px WebP, images only).

| Route | Permission | Notes |
|---|---|---|
| `GET marketer/media`, `marketer/media/{id}` | `ai_media.view` | list / detail |
| `GET marketer/media/{id}/file[?v=]`, `/thumb`, `/download[?v=]` | `ai_media.view` | streamed; `Cache-Control: private, no-store`, `nosniff`, `X-Robots-Tag: noindex`, sandbox CSP; range requests on local disks |
| `POST marketer/media`, `POST marketer/media/{id}/version` | `ai_media.upload` | throttled 60/min |
| `PUT marketer/media/{id}`, `POST …/submit`, `POST …/archive` | `ai_media.edit` | metadata, send to review, archive |
| `POST …/approve`, `POST …/reject` | `ai_media.approve` | checked by route and again by the service |
| `DELETE marketer/media/{id}` | `ai_media.delete` | soft delete; files stay on the private disk |
| `GET marketer/media-preview/{id}?v=&thumb=` | signed URL (10 min) | `marketing-media.preview`, for API consumers; valid for that version only; no session |

## Upload validation
FormRequest (`mimetypes`, size) plus `SocialMediaLibraryService::inspect`: MIME read from the file content, images must decode (`getimagesize`, 50..10000 px), videos need the container signature (MP4/MOV `ftyp`, WebM EBML).
Allowed: JPEG, PNG, WebP (15 MB); MP4, WebM, MOV (200 MB). Limits: `config/marketing.php` → `media`, env `SOCIAL_MEDIA_IMAGE_MAX_KB`, `SOCIAL_MEDIA_VIDEO_MAX_KB`. **PHP `upload_max_filesize` / `post_max_size` (currently 20M / 80M locally) must be raised to allow large videos.**
Originals are kept as uploaded (not re-encoded). `canva_url` is allow-listed to `https://(www.)canva.com`. All text is reduced to plain text.

## Lifecycle
`draft` (no file yet, e.g. from a suggestion) → `pending_review` (any upload) → `approved` / `rejected`; `archived` is read-only. A new file, or a change to title / caption / brief / hashtags / CTA / Canva link on an approved item, sends it back to `pending_review`. Campaign, tags and similar organisational fields do not.

## Suggestions
Approved `social_content` / `media_asset` suggestions apply to a private `post` item (status `draft`, source `suggestion`, result entity `social_media_asset`), never published. Rollback archives it. Uploading a file later turns it into an image / video item at version 1.

## Permissions (`config/permissions.php`)
`ai_media.view|upload|edit|delete|approve`: owner / super-admin and the preset role `marketer` hold all of them.

## Known gaps
- No ffprobe on the server: video dimensions are not read and duration is entered by hand; videos have no generated thumbnail.
- Deleted items keep their files on the private disk (no purge job).
- No media generation provider is configured: `media_asset` suggestions produce briefs, not files (MCP.md §21.4).
