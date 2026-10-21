# Plan — QR codes (product / variant / affiliate), local-only CDN, report fixes

## A. Local CDN only (remove S3)
- `config/filesystems.php`: `media` disk is always `local` (root `MEDIA_ROOT`, default `storage/app/media`, URL `MEDIA_URL`, served by the static cdn vhost / `public/media` symlink in dev). Remove the s3 branch, `MEDIA_DRIVER/KEY/SECRET/BUCKET/...` env vars, S3 package if only used for this, and S3 mentions in `.env*.example`, `docs/cdn-nginx.conf` comments, README/PROGRESS notes.
- `config/media.php` keeps `disk/url/private_disk`. `media_url()` stays the only URL builder.

## B. QR codes
Content rules
- Tenant product QR → `{tenant_url}/{default_locale}/product/{slug}`.
- Variant QR → same URL + `?variant={product_variant_id}` (product page must preselect that variant; add if missing).
- Affiliate QR (one per approved affiliate × active tenant product, and × variant for variable products) → product/variant URL + `?utm_source=affiliate&aff={affiliate_uuid}` (the existing attribution cookie flow).
- Image: PNG, error-correction High, 600px, white quiet zone, **3alraf logo centered** (≈20% width, white rounded backing). Logo file: `resources/branding/3alraf-logo.png` (create a clean placeholder with GD if no brand asset exists; path configurable in new `config/qr.php`, replaceable without code change).

Storage / model
- New table `qr_codes`: `id, tenant_id, owner_type, owner_id` (morph: TenantProduct | TenantProductVariant), `affiliate_id` nullable (FK, cascade), `url` (text), `url_hash` (char 40), `path`, timestamps. Unique `(owner_type, owner_id, affiliate_id)` (null-safe via generated key or coalesce 0). Tenant-leading index. BelongsToTenant. Files on the **media disk**: `tenants/{id}/qr/products/{owner}-{hash}.png` (content-hashed, immutable).
- `QrCodeService` (extend): `png(string $data): string` with logo; `QrCodeManager` service: `syncProduct(TenantProduct)`, `syncVariant(...)`, `syncAffiliate(Affiliate, ?TenantProduct)`, `deleteFor($owner)`. Idempotent: regenerate only when `url_hash` changes (slug/locale/domain/variant/affiliate uuid change); **old file is deleted when replaced and when the owner/affiliate is deleted or suspended-hard-deleted** (soft delete of product → keep, force delete / disable → remove).
- Generation triggers: tenant product enabled (bulk add + update), tenant variant created/updated, product slug/variants changed in central catalog (re-sync every tenant listing), affiliate approved (generate for all active products), new tenant product enabled (generate for all approved affiliates), affiliate uuid change/suspend. Heavy fan-out runs in queued jobs (`ShouldQueue`, `SyncQrCodesJob`), small ones inline-after-commit. Artisan `qr:rebuild {--tenant=} {--force}` for backfill (and run for existing data in a migration-free way).
- Delivery: tenant admin product edit page shows product + variant QR with download (PNG) buttons; central product page not needed. Affiliate dashboard (web) and API `products/{slug}/qr` return the stored PNG URL (`media_url`) instead of on-the-fly SVG (keep a graceful fallback: generate on demand if missing). `GET` print sheet optional: skip.
- Authorization: tenant isolation everywhere; affiliate only sees own QRs.

## C. Image replacement hygiene
- Audit every upload path (product/variant images, brand, category, banner, tenant logo, expense receipts, payment proofs, QR): replacing or removing an image deletes the old original + `_thumb` file(s) from disk and DB row; deleting an owner deletes its files. Add tests with `Storage::fake`. Scheduled `media:prune-orphans --dry-run` command is a nice-to-have (list files on disk without DB reference under tenants/).

## D. Reports / money gaps
- Sales report: add `cod_fee` column (daily rows, totals, CSV, central currency conversion); keep payment `fees` separate; update CALCULATIONS.md "Reports as implemented".
- Other report/money gaps from PROGRESS: second COD return with `refund_shipping` must not re-ask the provider for shipping already refunded; Fawry webhook re-query/verification parity with Paymob; soft-deleted discount codes/customers must free their unique code/e-mail (or validate uniqueness ignoring trashed consistently); order create/edit manual COD-fee override is OUT of scope.

## E. COD upfront default test
- Remove `PAYMENTS_COD_UPFRONT_DEFAULT=false` from `phpunit.xml`; the real default (true) must hold in tests. Make the older tests explicit instead: a test helper/factory state (`codUpfront(false)`) that sets `settings.cod_collect_shipping_upfront=false` where those tests assume carrier collects full total. Add tests proving: default tenant (no setting) ⇒ upfront true; explicit false ⇒ full total collected; env fallback respected.

## F. Done criteria
`vendor/bin/pest` green, `php artisan route:list` clean, `npm run build` OK, `php artisan api:docs` regenerated, PROGRESS.md + CALCULATIONS.md updated, QR visually verified (decode the PNG with an available decoder or at least render and inspect it).
