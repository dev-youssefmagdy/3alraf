# Plan — 60-day soft-delete purge + unique affiliate e-mail

## 1. Automatic purge of soft-deleted records after 2 months
- Config `config/retention.php`: `soft_delete_days` (env `SOFT_DELETE_RETENTION_DAYS`, default 60) and the list of purgeable models.
- Inventory every model using SoftDeletes (brands, categories, products, variants, cities, branches, suppliers, shipping companies, payment methods, orders, customers, discount codes, tenants, …). For each, decide the purge rule by inspecting FKs:
  - Unreferenced → `forceDelete()` plus its files (images via ImageUploadService, QR via QrCodeManager, private files) and dependent rows (cascade or explicit).
  - Referenced by retained business records (e.g. product/variant in order_items, stock movements, purchase items; customer on orders; payment method on orders; branch on stock) → NEVER break history or reports: keep the row, and if it holds personal data (customers) anonymize it (name/phone/email → tombstone, addresses removed) instead; log it as "retained". Document each model's rule in a table in CALCULATIONS-neutral docs (docs/PROGRESS.md + this file).
  - Soft-deleted tenants: purge only after the window AND only by an explicit safe path (all tenant data + media dir `tenants/{id}/` + QR); if too risky, keep tenants out of auto-purge and say so.
- Implementation: `Services\Retention\SoftDeletePurger` (one method per model rule, chunked, transactional per row, idempotent, uses `deleted_at < now()-days`), artisan `retention:purge {--dry-run} {--days=} {--model=}` printing counts (purged / anonymized / retained), scheduled daily off-peak (`withoutOverlapping`, `onOneServer`), audit-log entry per run. Cross-tenant safe: purge uses `withoutGlobalScopes()` but always respects each row's own tenant files.
- Tests: boundary (59 vs 61 days), unreferenced purge removes files + QR + children, referenced product/customer retained/anonymized and reports unchanged, dry-run changes nothing, tenant isolation, schedule registered, unique-tombstone rows (`FreesUniqueOnDelete`) purge fine.

## 2. Affiliate e-mail must be unique
- Decide scope: unique per tenant (affiliates are tenant-scoped; the same person may promote on two stores) — confirm by reading the schema/REQUIREMENTS; if the DB has no unique index add `unique(tenant_id, email)` with case-insensitive collation and a data-fix step in the migration that detects existing duplicates and fails loudly with a clear message (or de-dupes deterministically if safe: keep the oldest, tombstone the rest).
- Normalise e-mail (trim + lowercase) at write time in one place; validation rule `unique` scoped to tenant (ignoring self on update) in storefront register, API register, and any admin create/edit; the email must also not collide case-insensitively. If affiliates are soft-deletable apply the same `FreesUniqueOnDelete` tombstone.
- Login lockout/validate endpoints must keep working with normalised e-mail. Tests: duplicate (case variants) rejected on web + API + admin, same email allowed in another tenant, update-self allowed, migration duplicate handling.

## 3. Done criteria
`vendor/bin/pest` green, `route:list` clean, `schedule:list` shows the purge, PROGRESS.md updated, summary lists rules per model.
