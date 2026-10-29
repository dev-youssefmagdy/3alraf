# Images: AVIF pipeline

## Architecture
- Every upload goes through `ImageUploadService::store()` (products, variants, categories, brands, banners, bundles, packaging, AI studio). It calls `Images\ImageOptimizer` (GD + Intervention v4) and writes, to the `media` disk:
  `name.avif`, `name_thumb.avif` (+ `name.webp`, `name_thumb.webp` fallback, `IMAGE_WEBP_FALLBACK`).
- Profiles/limits: `config/images.php` (product 1000px/100KB, thumb 400px/40KB, category 700px/80KB, banner 1600x700/100KB, logo 800px/100KB). Encoder walks scale (1.0→0.6) × quality (65→40); if still over the limit the upload is stored but logged (`image.over_limit`), the migration marks it `exceeds_limit` and does **not** switch references.
- Uploads are synchronous on purpose: the DB row is only written after the files exist (no dangling references). Content is validated from bytes (mime allow-list, size and pixel caps); animated GIFs are rejected.
- Not converted: QR codes (`*/qr/*.png`, must stay crisp), SVG (not an accepted upload type), private-disk files (payment proofs, receipts), external URLs.
- Views use `<x-media-img>` (`<picture>` AVIF + WebP fallback, explicit width/height, first/LCP image eager+`fetchpriority=high`, rest lazy). The CDN vhost already sends `Cache-Control: public, max-age=31536000, immutable` (file names are unique per upload).
- `MediaPaths::family()`: avif/webp × main/thumb live and die together (`media:prune-orphans`, soft-delete purge, replace/delete).

## Server requirements
PHP GD built with libavif (`php -r 'var_dump(gd_info()["AVIF Support"]);'` → true). Debian/Ubuntu: PHP ≥ 8.1 packages ship it; else install `libavif-dev` and rebuild GD (`--with-avif`). Queue workers/php-fpm must be the same PHP build and **the web/worker user must be able to write `storage/app/media`** (run the command as that user: `sudo -u www-data php artisan …`).

## Migrating existing images
```
php artisan images:optimize-existing --dry-run --sample=50     # inventory + estimate, writes nothing
php artisan images:optimize-existing --limit=20                # pilot: creates .avif beside originals + ledger; DB untouched
php artisan images:optimize-existing                           # all categories, resumable; add --queue to use workers
php artisan images:optimize-existing --apply                   # verify, then repoint DB references (originals kept)
php artisan images:optimize-existing --retry-failed            # after fixing permissions etc.
php artisan images:optimize-existing --rollback                # references back to the originals
```
Options: `--category=images|brands|categories|bundles|packaging|bundle_ab|banners|ai` (repeatable), `--disk=`, `--chunk=`.
Ledger: table `image_optimizations` (status optimized/exceeds_limit/missing/invalid/skipped/failed, bytes, quality, `applied_at`). Back up `images, brands, categories, bundles, packaging_options, bundle_ab_variants, banners, ai_generations` before `--apply`.

Rollout: dry-run → pilot → check storefront → full run → `--apply` → monitor → keep originals until the rollback window passes; only then delete `.webp` originals (they are the legacy-browser fallback for migrated rows — keep them unless you accept AVIF-only).
