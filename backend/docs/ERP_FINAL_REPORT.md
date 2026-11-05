# ERP & Accounting Module - Final Report (branch ERP-MODULE)

Source of truth for design: `docs/ERP_PLAN.md` (phases 1-10 + production runbook). Order math: `docs/CALCULATIONS.md`.

## 1. Existing architecture findings
Single shared DB with `tenant_id` + `BelongsToTenant` global scope (central context sees all rows); `Money` (bcmath, HALF_UP); `StockLedger` is the only stock writer (weighted average cost); orders carry `net_profit`/`cogs`; profit is recognised when the admin confirms; domain events (OrderStatusChanged, OrderPaymentUpdated, OrderReturnUpdated, ...) already exist; Spatie permissions with separate `tenant` / `central` guards; Blade + Tailwind + Chart.js; lang in `lang/ar` (+ `resources/lang-erp/en` for the ERP). Risks found: double revenue, order-profit vs ledger drift, COD cash held by carriers, historic data without journals, tenant leaks through ids, closed periods.

## 2. Final ERP architecture
Double-entry ledger. `JournalService` is the only writer of journals and account balances (atomic, bcmath-balanced, period-guarded, idempotent per `(tenant_id, idempotency_key)`, row-locked). Posters (`Services/Erp/Posting/*`) are driven by thin listeners (`Listeners/Erp/*`) on existing events through `PostingGuard` (a ledger failure never breaks the business operation; it is audited as `posting.failed` and shows as an anomaly). Reports (`Services/Erp/Reports/*`) only read posted lines. Central panel: read-only raw `GROUP BY tenant_id` queries, totals only inside one currency. Commands run per tenant inside `TenantContext`.

## 3. Tables and relationships (all carry `tenant_id`)
`erp_accounts` (parent/children, role_key, cached current_balance) - `erp_fiscal_years` -> `erp_periods` - `erp_journal_entries` (period, branch, reverses/reversed_by, source_type/id, idempotency_key) -> `erp_journal_lines` (account, branch, party_type/party_id customer|supplier|owner) - `erp_audit_logs` - `erp_owners` -> `erp_owner_transactions` - `erp_fixed_assets` - `erp_settings` - `erp_profit_distributions` -> `erp_profit_distribution_lines` (owner) - `erp_exchange_rates` (tenant_id NULL = global) - `erp_backfill_runs`, `erp_opening_orders`. `expense_categories.erp_account_id` and `expenses.is_paid/paid_from` map expenses to the ledger.

## 4. Tenant functionality
Hub of cards at `/admin/erp`: dashboard, chart of accounts, manual journals (draft/post/reverse), accounting periods (close/reopen, year-end close), customers and suppliers ledgers (statements, ageing, receipts/payments with advances, credit/debit notes, opening balances), owners and equity (contributions, withdrawals, branch capital), fixed assets (register, monthly depreciation, disposal, branch transfer), controlled profit distribution (calculate, approve, post, pay), branch accounting (+ branch cash transfer), 18 financial reports with CSV/PDF export, reconciliation, migration page (backfill history, post missing, auto-posting switch), settings (reserve %, buffer, tax estimate, approvals, exchange rates).

## 5. Central functionality (read-only, `erp_central.*`)
Hub, tenant overview (+ CSV, drill-down to a tenant's own statements), performance and consolidated views grouped by currency (optional conversion with stored rates only), cross-tenant transactions + entry detail, receivables/payables, inventory valuation, periods, anomaly detection (+ CSV), audit log viewer, backfill/migration monitor.

## 6. Accounting workflows and journal mappings
See `docs/ERP_PLAN.md` sections 2 and 5: order confirmed (Dr Receivable / Cr Revenue, Shipping, VAT, Fees; Dr COGS / Cr Inventory), payments (Dr cash-like / Cr Receivable), returns (contra revenue + restock or loss), refunds, carrier settlement, purchases (Dr Inventory / Cr Payable), supplier payments, stock adjustments and transfers (zero consolidated effect), expenses (Dr expense / Cr cash or payable), owner flows, distribution (Dr Retained earnings / Cr Distributions payable), depreciation, year-end close (revenue and expense -> Income summary -> Retained earnings).

## 7. Profit methodology
Net profit from posted lines only: gross revenue - returns/discounts = net revenue - COGS = gross profit - operating expenses + other income - other expenses - estimated tax. Distributable = min(profit after prior losses and reserve, distributable cash after liabilities, reserves and buffer). Workflow draft -> approved (optional second approver) -> posted -> paid per owner; unique live run per period; nothing historic is edited (cancel of a posted run = reversal journals). Details: ERP_PLAN section 7.

## 8. Migration and reconciliation
`erp:backfill --tenant= --from= [--dry-run]` (idempotent; documents before `--from` become opening entries), `erp:reconcile` (ledger vs Net Profit report, orders, purchases, stock; exit 1 on mismatch), NEW `erp:verify` (ledger vs itself; exit 1). Pre-ERP tenants get `auto_posting_enabled=false` until the backfill completes.

## 9. Permissions
Tenant: `erp.dashboard.view`, `erp.accounts.view|manage`, `erp.journals.view|create|post|reverse`, `erp.expenses.manage`, `erp.reports.view|export`, `erp.profits.view|distribute`, `erp.owners.manage`, `erp.branches.view`, `erp.settings.manage`, `erp.periods.view|close|reopen`, `erp.assets.manage`, `erp.audit.view`. Central: `erp_central.view|reports|audit`. Branch rule: branch-specific views need `erp.branches.view`, otherwise the branch filter is ignored (consolidated only).

## 10. Reports and dashboards
Trial balance, general ledger, P&L, balance sheet, cash flow, journal entries, receivables, payables, sales, purchases, sales returns, expenses, COGS, inventory valuation, branch profitability, owner equity, VAT/tax, reconciliation; party ageing; profit distribution report and owner statements; executive dashboard (KPIs equal the statements, 6-month trend, branch profit, top expenses, last entries, alerts). Lists are capped (general ledger 5000, journal-entries page 1000, exports 50000).

## 11. Automated test results
ERP suites (ErpCore, ErpPosting, ErpPartiesOwnersAssets, ErpProfitDistribution, ErpReports, ErpCentral, ErpBackfill + new ErpSecurity, ErpPerformance, ErpProductionReadiness), one process, sequential: **109 tests, 109 passed, 1528 assertions** (~97 s). New in phase 10: 13 security/integrity tests, 5 performance tests, 6 production-readiness tests (24 total).
Full application suite (`php artisan test`, single sequential process, includes the ERP suites): **760 tests, 709 passed, 4 failed, 47 errors, 20705 assertions** (367 s). None of the 51 non-passing tests touches ERP code:
- 45 errors: `storage/framework/testing/disks/chat-test` and `.../public/products` are owned by another OS user (`b`, mode 700) and not writable by the test runner (WhatsappChatApi/Ingest, MediaTest) - environment, fix with `chown`/`chmod` on `storage/framework/testing`.
- CitySeederTest (null default record), GrowthOffersTest (`product_variants.barcode` too long for the generated barcode): pre-existing data/schema issues in non-ERP code.
- BlogTest (content sanitising assertion), HardeningTest (Arabic 404 page text), QrCodeTest (logo PNG check), StorefrontTest (sitemap Content-Type `application/xml; charset=UTF-8` vs `application/xml`): storefront/blog/QR/HTTP-header assertions in files not modified by the ERP branch.
Verified by reasoning (no `git stash`): the ERP branch touches only ERP files, Expense/ExpenseCategory, Purchase/StockAdjustment/StockTransfer/CarrierSettlement services, ReportService, AppServiceProvider, permissions config, the admin layout and routes; none of the failing tests exercises those paths.

## 12. Security and performance improvements (phase 10)
Security: tenant-scoped `exists` rules; service-level foreign account rejection; unique-race handling for idempotent posting; locked, idempotent period close/reopen and period re-read under lock when posting; drafts-in-period guard fixed; no appending lines to posted entries; receipts/payments/advances serialised under the tenant lock; one formula-safe CSV cell function for all exporters (incl. titles/labels); tenant context no longer leaks from `PostingGuard`; `erp:depreciate` under `TenantContext`; year parameter validated. New test iterates all 76 tenant/central ERP routes (guest redirect, no-permission 403, tenant admin cannot reach central) and checks IDOR 404s, injection, XSS, CSV, double submit.
Integrity: DB CHECK constraints (line sign / one side, entry status) + `erp:verify`. Raw SQL tampering with amounts is detected by `erp:verify` (and the weekly schedule) but not prevented by triggers.
Performance: 8 composite indexes, general ledger SQL-ordered and capped, bounded query counts asserted on 2000 seeded lines (15 reports, dashboard, hub, central pages) and independent of data volume / tenant count.

## 13. Remaining limitations / optional features
Budgeting, payroll integration, loan amortisation, bad-debt provisioning, scheduled/emailed reports, PDF/Excel polish, global exchange-rate UI, invoice-level receipt matching (FIFO ageing), estimated tax never posted, distribution runs are company-wide, `BelongsToTenant` makes `tenant_id` fillable app-wide (ERP never mass-assigns request data), posted-row protection against raw SQL is policy + `erp:verify`, not triggers.

## 14. Deployment steps and environment
1. Deploy code; `php artisan migrate --force` (also seeds ERP permissions; adds CHECK constraints when existing rows comply, otherwise logs a warning).
2. `php artisan permission:cache-reset`; assign `erp.*` / `erp_central.*` permissions to roles.
3. Per tenant: `erp:backfill --tenant=SLUG --from=YYYY-MM-DD --dry-run`, then real run, then `erp:reconcile --tenant=SLUG` (exit 0) and `erp:verify --tenant=SLUG` (exit 0); confirm auto-posting is on.
4. `php artisan optimize` (config/route/view caches verified safe; no `env()` outside config).
5. Scheduler (`schedule:run` every minute): `erp:depreciate` monthly (1st, 03:20), `erp:verify` weekly (Mon 04:10); optionally schedule `erp:reconcile` daily and alert on non-zero exit.
6. Environment: no new variables. Attachments use the existing private disk (`config('media.private_disk')`). Optional `config('erp.large_manual_threshold')` (default 100000) for the large-manual-entry anomaly.
