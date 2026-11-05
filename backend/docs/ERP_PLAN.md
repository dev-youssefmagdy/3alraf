# ERP & Accounting Module — Architecture and Implementation Plan

Branch: `ERP-MODULE`. Status of each phase is tracked at the bottom.

## 1. Audit findings (what exists today)

| Area | Finding | Consequence |
|---|---|---|
| Tenancy | ONE shared DB, `tenant_id` column + `BelongsToTenant` global scope (`TenantScope`). Central context (no tenant) sees all rows. | Every ERP table carries `tenant_id` and uses `BelongsToTenant`. Central reads use `forTenant()` / explicit grouping — never mixed. |
| Money | `App\Support\Money` (bcmath, decimal(14,2) strings, HALF_UP). `docs/CALCULATIONS.md` is the single source of truth for order math. | ERP uses `Money` + `decimal(18,4)` ledger columns (scale 2 on display). No floats. The ledger *consumes* order numbers, it never recomputes them. |
| Stock | `StockLedger` is the only stock writer; weighted-average cost per (branch, product, variant); `stock_movements` typed rows. | Weighted Average Cost is kept. Inventory valuation account is driven from stock movements, one journal per movement document. |
| Sales | `orders` (+items, status history, `net_profit`, `cogs`, payment fee, COD fee, shipping cost). Profit recognised when admin confirms (status ∈ confirmed…delivered). Events: `OrderPlaced`, `OrderStatusChanged`, `OrderPaymentUpdated`, `OrderReturnUpdated`. | Revenue is posted on **admin confirmation** (same rule as profit reports) so ERP profit == existing Net Profit report. Cash/receivable posted on payment events. |
| Purchases | `purchase_orders` (+items), `receive`, `cancel`, suppliers. | Journal on receipt (Dr Inventory / Cr AP); supplier payment later. |
| Returns / refunds | `order_returns`, `ReturnService`, `RefundService`. | Sales-returns contra-revenue (never an expense) + COGS/inventory reversal + refund cash leg. |
| Expenses | `expenses` + `expense_categories` (amount, date, branch, attachment). No account, no payment status. | Extended with account mapping, payee, paid/unpaid via the journal — old rows backfilled, not rewritten. |
| Payments | `payment_methods`, `payment_events` (unique provider+event_id, idempotent webhooks). | Each payment method maps to an asset account (cash / wallet / gateway clearing). |
| Branches | `branches`, `stock_levels` per branch, `expenses.branch_id`. | Journal lines carry `branch_id`. |
| Permissions | spatie/permission, matrix in `config/permissions.php` seeded by `PermissionSeeder`; new perms reach existing installs via a seed migration. | Add `erp.*` (tenant) and `erp_central.*` (central). |
| UI | Blade `x-layouts.admin` (sidebar groups defined inline), Tailwind, Yajra DataTables, `lang/{en,ar}`. Sidebar group `admin.nav.finance`. | New sidebar entry **ERP** → hub page of cards; each card is its own page. |
| Layering rule | Controller → FormRequest → DTO → Service → Repository → Model. | Followed for CRUD; the posting engine is a dedicated domain service. |

### Risks identified
1. **Double revenue** if both order-confirm and payment events recognise revenue → revenue only on confirmation; payment only touches cash/receivable.
2. **Existing `orders.net_profit` vs ledger drift** → reconciliation report compares the Net Profit report with the P&L from the ledger.
3. **COD**: carrier holds cash → modelled as *Carrier receivable* asset until settlement (`carrier_settled_at`).
4. **Historical data** has no journals → backfill command with idempotency keys + opening-balance date; no historical row is mutated.
5. **Tenant leak** through ids → every ERP model uses the tenant scope and controllers resolve by scoped query (404 otherwise); central controllers use explicit tenant filters.
6. **Closed periods** → engine refuses posting into locked periods; reopening requires a separate permission and writes an audit row.

## 2. Architecture

```
app/Models/Erp/*            Account, AccountingPeriod, FiscalYear, Journal(Entry), JournalLine, ErpSetting, PostingRule,
                            Owner, OwnerTransaction, ProfitDistribution(+Line), FixedAsset, ExchangeRate, ...
app/Services/Erp/
  Accounting/JournalService       post / draft / reverse / idempotency / period guard / balance check (DB transaction)
  Accounting/AccountResolver      role key ("sales_revenue", "cogs", ...) → account id per tenant (no hardcoded ids)
  Accounting/PeriodService        fiscal years, monthly periods, close / lock / reopen
  Accounting/CoaTemplate          default chart (seeded per tenant, editable)
  Posting/*Poster                 SalesPoster, ReturnPoster, PurchasePoster, ExpensePoster, StockPoster, PaymentPoster
  Reports/*                       TrialBalance, GeneralLedger, ProfitLoss, BalanceSheet, CashFlow, AgingReports, ...
  Equity/ProfitDistributionService
  Assets/DepreciationService
app/Listeners/Erp/*             thin listeners on existing events → Posters (queued-safe, idempotent)
app/Http/Controllers/Tenant/Erp/*   one controller per hub card
app/Http/Controllers/Central/Erp/*  consolidated monitoring (per-tenant boundaries preserved)
```

### Ledger design
* `erp_accounts`: tenant_id, code (unique per tenant), name (translatable), type (asset/liability/equity/revenue/expense), subtype, parent_id, normal_balance, branch_id, currency, opening_balance, is_system, role_key, is_active, archived_at. Balance is **derived** from lines (never edited directly); `current_balance` is a cached column refreshed only by `JournalService`.
* `erp_journal_entries`: tenant_id, number, status (draft/posted/reversed), type (manual/auto/opening/adjusting/closing/reversal), entry_date, period_id, branch_id, currency, exchange_rate, source_type/source_id, **idempotency_key (unique per tenant)**, reverses_id, reversed_by_id, description, created_by/posted_by, posted_at.
* `erp_journal_lines`: entry_id, account_id, branch_id, debit/credit (decimal 18,4, in base currency), original_amount + original_currency, party_type/party_id (customer / supplier / owner), memo.
* Posted rows are immutable: Eloquent model blocks update/delete of posted entries & their lines; corrections = reversal + replacement. DB check `debit=0 or credit=0`.
* Debits = credits enforced in `JournalService::post()` (bcmath) inside `DB::transaction` with the accounts and period rows locked.
* Idempotency: unique `(tenant_id, idempotency_key)`; `post()` returns the existing entry when the key repeats.

### Posting policy (default, configurable via `erp_settings`)
| Event | Entry |
|---|---|
| Order reaches the recognition point (`revenue_recognition`: shipped (default) / delivered / confirmed) | Dr Receivable (total) / Cr Sales revenue (goods net of discount) / Cr Shipping revenue / Cr VAT payable / Cr COD fee income; Dr COGS / Cr Inventory at the shipped cost (see docs/CALCULATIONS.md §ERP revenue recognition) |
| Payment received (online/wallet/COD settled) | Dr Cash/Wallet/Gateway/Carrier clearing / Cr Receivable; gateway fee: Dr Gateway fees / Cr Gateway balance |
| Shipping cost | Dr Shipping expense / Cr Carrier payable (or netted from COD settlement) |
| Return approved | Dr Sales returns (+VAT) / Cr Receivable; Dr Inventory / Cr COGS (restock) or Dr Inventory-loss (damaged) |
| Refund paid | Dr Receivable (or Customer credit) / Cr Cash/Wallet/Gateway |
| Cancel | before confirmation → no entry; after confirmation → reversal of the confirmation entry (+ cancellation-fee entry if any) |
| Purchase received | Dr Inventory (+landed costs) / Cr Accounts payable |
| Supplier payment | Dr AP / Cr Cash/Bank (advances → Supplier advances asset) |
| Stock adjustment / damage / loss | Dr Inventory-loss (shrinkage) / Cr Inventory (gain is the reverse) |
| Stock transfer between branches | Dr Inventory (to-branch) / Cr Inventory (from-branch) → zero consolidated effect |
| Expense (paid/unpaid) | Dr Expense account / Cr Cash or AP |
| Owner contribution / withdrawal | Dr Cash / Cr Owner capital ; Dr Owner drawings / Cr Cash |
| Profit distribution | Dr Retained earnings / Cr Owner distributions payable → payment later Dr payable / Cr Cash |
| Depreciation | Dr Depreciation expense / Cr Accumulated depreciation |
| Year-end close | Revenue & expense → Income summary → Retained earnings |

### Profit distribution safeguards
Distributable = min(retained earnings after prior losses and reserve %, available cash − current liabilities due − restricted/reserve), validated that no draft journals exist in the period, ledger balanced, and no earlier distribution covers the same period (unique `(tenant, period, run_no)` + a lock on retained-earnings consumed).

### Multi-currency
Tenant base currency from `tenants.currency_code`; every line stores base amount + original amount/currency/rate; `erp_exchange_rates` history; FX gain/loss account for settlement differences. Central consolidated views group **by currency** and only sum after converting with the stored rate, clearly labelled.

### Permissions
Tenant: `erp.dashboard.view`, `erp.accounts.view|manage`, `erp.journals.view|create|post|reverse`, `erp.expenses.manage`, `erp.reports.view|export`, `erp.profits.view|distribute`, `erp.owners.manage`, `erp.branches.view`, `erp.settings.manage`, `erp.periods.close|reopen`, `erp.assets.manage`, `erp.parties.view`, `erp.audit.view`.
Central: `erp_central.view`, `erp_central.reports`, `erp_central.audit`, `erp_central.periods.view`.

### Hub navigation (requested UX)
Sidebar item **ERP** → `/admin/erp` hub page = grid of cards, each card links to its own page (permission-gated, lang en/ar):
Dashboard · Chart of Accounts · Journal Entries · Accounting Periods · Expenses · Customers Ledger · Suppliers Ledger · Cash & Bank · Owners & Equity · Profit Distribution · Fixed Assets · Branch Accounting · Financial Reports · Reconciliation · Settings.
Central: **ERP** → hub with Tenant Overview · Consolidated Financials · Transactions · Receivables/Payables · Inventory valuation · Periods · Audit & Anomalies.

## 3. Evaluation of optional items (section 19)
* **Implement (essential):** recurring expenses, expense approvals, credit/debit notes (as journal types), VAT/tax report, bank/cash/gateway reconciliation (basic), attachments, financial audit export (CSV), anomaly flags (unbalanced, unposted sources, backdated into closed period).
* **Implement later / optional:** budgeting, payroll integration (accounts exist, no payroll module in app), loan amortisation, bad-debt provisioning, PDF/Excel polish.

## 4. Phases & status

| # | Phase | Status |
|---|---|---|
| 1 | Audit + plan (this file) | done |
| 2 | COA, periods, settings, permissions, hub UI, nav | in progress |
| 3 | Journal engine (post/reverse/idempotent/period lock) + manual journals UI + tests | in progress |
| 4 | Auto-posting from sales/returns/purchases/stock/expenses/payments | done |
| 5 | Parties ledgers, owners & equity, fixed assets | done |
| 6 | Profit calc + distribution + year-end close | done |
| 7 | Reports + dashboard | done |
| 8 | Central ERP monitoring | done |
| 9 | Backfill + reconciliation command | done |
| 10 | Full test suite, perf, security review | done |


## 5. Phase 4 - automatic posting (done)
Code: `app/Services/Erp/Posting/{Poster,SalesPoster,PaymentPoster,ReturnPoster,PurchasePoster,StockPoster,ExpensePoster,PostingGuard}`; thin subscribers `app/Listeners/Erp/{PostOrderEvents,PostOperationsEvents}` (registered in `AppServiceProvider`); new hook events `app/Events/Erp/*` (PurchaseReceived/Cancelled, StockAdjusted, StockTransferMoved, ExpenseSaved/Deleted, CarrierSettled) fired by the existing services inside their own transaction. Orders/returns use the EXISTING `OrderStatusChanged`, `OrderPaymentUpdated`, `OrderReturnUpdated`. Tests: `tests/Feature/ErpPostingTest.php`.

| Event | Idempotency key | Entry |
|---|---|---|
| Order status -> confirmed..delivered | `order:{id}:confirm` | Dr Receivable / Cr Sales revenue (`revenueGoods`), Shipping revenue (`shipping_net`), VAT payable (`tax+packaging_tax`), Fee income (`payment_fee_charged+cod_fee`), Other revenue (packaging net of VAT); Dr COGS / Cr Inventory; Dr Shipping expense / Cr Shipping payable; Dr Gateway fees / Cr method account; Dr Affiliate expense / Cr Affiliate payable; Dr Packaging / Cr Inventory. Same figures as `OrderProfitService` => ledger net profit == Net Profit report |
| paid_amount grows (proof approved / gateway paid / COD delivered) | `order:{id}:pay:{cumulative paid}` | Dr Gateway / E-wallet / Carrier COD receivable (COD prepaid-shipping leg by its own method) / Cr Receivable |
| Cancel | reversal of `order:{id}:confirm` + `order:{id}:commission:*`; `order:{id}:cancel-refund:{amt}` | before confirmation: nothing; refund leg Dr Receivable / Cr method account |
| Return approved | `return:{id}:goods`, `:ship`, `order:{id}:commission:ret{id}` | Dr Sales returns (net of VAT) + VAT payable / Cr Receivable; restock: Dr Inventory / Cr COGS, damaged: Dr Inventory loss / Cr COGS; return shipping Dr Shipping expense / Cr Shipping payable; affiliate commission delta |
| Return refunded | `return:{id}:refund` | Dr Receivable (+ Shipping revenue for a refunded shipping part) / Cr cash-like account |
| Carrier settled | `order:{id}:carrier-settle` | Dr Cash + Shipping payable / Cr Carrier receivable |
| Purchase received | `po:{id}:receive` | Dr Inventory (items) + Purchase expenses (shipping/other, not capitalised by the stock ledger) / Cr Payable (supplier party); `PurchasePoster::payment()` = supplier payment (Dr Payable / Cr cash) |
| Stock adjustment | `adj:{id}` | decrease: Dr Inventory loss / Cr Inventory; increase: reverse |
| Transfer sent / received | `transfer:{id}:out|in` | Dr Inventory in transit / Cr Inventory (from branch); Dr Inventory (to branch) / Cr In transit => zero consolidated effect |
| Expense saved / deleted | `expense:{id}:{hash}` | Dr category account (`expense_categories.erp_account_id`, fallback `other_expense`) / Cr cash-like (`paid_from`) or Payable (`is_paid=false`); edit = reverse + repost, delete = reverse |

Rules and assumptions: entries are dated on the day of the event (expenses: expense date); a payment before confirmation credits Receivable (negative receivable = customer credit, cleared by the confirmation); a fully returned order keeps its shipping/fee lines (real income/cost) whereas the legacy report drops them, and a damaged return books an inventory loss where the report recovers the COGS - both flagged for the Phase 9 reconciliation; revenue COGS uses `orders.cogs` (snapshot) while stock valuation uses the movement average, so small valuation drift is possible. A posting failure (closed period, missing account) never breaks the business operation: it is reported and written to `erp_audit_logs` as `posting.failed` and can be re-posted (keys are idempotent). The chart is seeded lazily on first posting. No backfill of history yet (Phase 9). Migration `2026_12_11_100001` adds `expense_categories.erp_account_id`, `expenses.is_paid`, `expenses.paid_from` (old rows: unmapped + paid).

## 6. Phase 5 - parties, owners & equity, fixed assets (done)
Code: `Services/Erp/Reports/PartyLedger` (read), `Services/Erp/Parties/PartyService`, `Services/Erp/Owners/OwnerService`, `Services/Erp/Assets/AssetService` (all writes via `JournalService`); controllers `Party|Owner|AssetController`; command `erp:depreciate {--tenant=} {--period=YYYY-MM}` (scheduled monthly on day 1, default = previous month); migrations `2026_12_12_100001` (erp_owners, erp_owner_transactions) and `_100002` (erp_fixed_assets); strings in `erp_ext` (en in `resources/lang-erp`, ar in `lang/ar`). Tests: `tests/Feature/ErpPartiesOwnersAssetsTest.php`. Hub cards customers/suppliers/owners/assets now have routes (`HubController` supports `route:key=value` params).
- **Party ledgers**: derived only from posted/reversed lines with `party_type` customer|supplier on receivable+customer_advances / payable+supplier_advances. Balance is in the party's sense (customer owes us / we owe supplier; an advance is a negative open item). Pages: list (outstanding, advance, net + reconciliation with the control accounts incl. lines without a party), statement (range, running balance), ageing (open items: charges consumed oldest-first by payments/advances/notes; buckets 0-30/31-60/61-90/90+, surplus shown as credit). Reads `erp.accounts.view`; money forms `erp.journals.create`.
- **Settlement**: supplier payment (Dr payable / Cr cash-like) and customer receipt settle the open amount first, any excess (or "advance only") goes to supplier_advances / customer_advances; "apply advance" moves advance against the open balance. Opening balance = journal type `opening` vs `opening_equity`. Credit/debit notes = journal types `credit_note`/`debit_note` with the party (customer credit note / supplier debit note reduce the balance; counter account selectable, defaults sales_returns / other_revenue / inventory / purchase_expenses). Idempotency key `party:{type}:{id}:{kind}:{form token}`.
- **Owners**: `erp_owners` (percent sum <= 100 enforced under lock), `erp_owner_transactions` (contribution Dr cash-like/Cr owner_capital; withdrawal Dr owner_drawings/Cr cash-like; distribution paid Dr retained_earnings/Cr cash-like - the accrual/approval flow is Phase 6), all lines party `owner`. Equity per owner = credit - debit of capital, drawings and retained-earnings lines. Branch capital allocation: Dr branch cash / Cr branch_capital, both tagged with the branch. Permission `erp.owners.manage`.
- **Fixed assets**: registration Dr fixed_assets / Cr cash-like or payable (optional supplier party); depreciation per month from the acquisition month, key `asset:{id}:dep:{YYYY-MM}`, `last_depreciated_period` + key make reruns/catch-up idempotent, capped at cost - salvage (straight line: last month of the life takes the rounding remainder; declining balance: 2/life_months of NBV); disposal catches depreciation up to the disposal month then Dr cash + Dr accumulated / Cr fixed_assets, gain -> other_income, loss -> other_expense; branch transfer reclasses cost + accumulated between branches. `accumulated_depreciation` on the asset is a cache; the report reconciles the register with the ledger accounts. Permission `erp.assets.manage`.
- Gaps / assumptions: no per-document (invoice level) matching - ageing is FIFO per party; guest orders (no customer id) are shown as "without a party"; payments recorded on orders before this phase are not backfilled (Phase 9); distribution flow with approval and distributable-profit check is Phase 6; depreciation of a closed period fails for that asset (reported, other assets continue); only monthly frequency.

## 7. Phase 6 - profit methodology, controlled distribution, year-end close (done)
Code: `Services/Erp/Equity/{ProfitService,ProfitDistributionService,ErpSettings}`, `PeriodService::closeYear|reopenYear`, `LedgerReports` (`balancesByRole`, `residualEarnings`, `openPlBalances`, P&L excludes closing entries), controllers `ProfitController`, `SettingsController`; migration `2026_12_13_100001` (`erp_settings`, `erp_profit_distributions`, `erp_profit_distribution_lines`); strings `erp_dist` (en in `resources/lang-erp`, ar in `lang/ar`). Cards: Profit Distribution (`erp.profits.view`), Settings (`erp.settings.manage`). Tests: `tests/Feature/ErpProfitDistributionTest.php`.

**Net profit (posted + reversed-mirrored lines only, never drafts)** for a period/branch: gross revenue (revenue accounts except contra roles and `other_income`) - returns/discounts (`sales_returns`, `sales_discounts`) = net revenue; - COGS (subtypes cogs/shrinkage) = gross profit; - operating expenses (per account) = operating profit; + other income (`other_income`) - other expenses (`fx_loss`, `other_expense`) = profit before tax; - tax = net profit. Tax = balance of the optional `tax_account_id` expense account if configured, else `tax_rate`% of positive profit before tax as an UNPOSTED estimate (also held back from cash). Year-end `closing` entries are excluded from P&L (and so are their reversals) but included in the balance sheet and trial balance, so a closed year's P&L stays computable.

**Distributable profit** = (retained earnings + income summary + residual unclosed earnings, all before the period start = "brought forward", negative = prior losses). Profit after losses = max(0, net profit + min(0, brought forward)) (if setting `distribute_prior_retained`: max(0, brought forward + net profit)); reserve = `reserve_percent`% of that, rounded to the cent; distributable profit = profit after losses - reserve, floored to cents.
**Distributable cash** (as of now, branch-aware) = cash + bank + pos_cash + gateway + wallet - liabilities due (payable, shipping/affiliate payable, distributions payable, VAT payable, employee payable) - estimated tax - existing reserves balance - this run's reserve - `distribution_min_cash_buffer`, floored at 0. **Distributable = min(profit, cash)**; the UI flags when cash is the limiter (net profit is not cash).

**Workflow** (`erp_profit_distributions`, status draft|approved|posted|cancelled): `create` (calculate + draft) requires: no draft journals dated in the period, trial balance balanced, period inside one fiscal year and not in the future, period closed OR `acknowledge_open`, no overlap with a live run (unique `(tenant, active_key="start|end")`, cleared on cancel, tenant row locked) and distributable > 0. Shares: eligible owners only, by ownership % normalised among the eligible, truncated to the cent, remainder to the largest owner; optional custom amounts per owner (must equal the distributed total); partial distribution via `retain_percent` / `retain_amount` (stored `retained_after`). `approve` needs `erp.profits.distribute` and, if `distribution_requires_second_approver`, a different user than the creator. `approve` and `post` both re-validate against the CURRENT ledger (distributed <= fresh distributable profit and cash; cumulative of live overlapping runs <= distributable profit).
**Posting** (dated today, idempotent keys `profitdist:{id}:*`): if the fiscal year is not closed, the period's booked profit still sits in revenue/expense accounts, so first a `closing`-type transfer `Dr income_summary / Cr retained_earnings` for the ledger profit (total equity unchanged: IS is debit, RE credit, P&L accounts still hold the profit; the year-end close later sweeps only the difference). If the year is closed the profit is already in retained earnings, no transfer. Then `Dr retained_earnings / Cr distributions_payable` per owner (party owner) and `Dr retained_earnings / Cr reserves` for the reserve. `pay` (per owner line): `Dr distributions_payable / Cr cash|bank|pos_cash|gateway|wallet`, refused when the chosen account's balance < the amount. `cancel`: draft/approved just cancelled; posted = reversal of the posting + transfer entries, refused once any share is paid. Owner pages show distributions; printable owner statement + per-owner report at `erp/profits/report`.

**Year-end close** (`closeYear`): refuses a closed year, an earlier open year, drafts in the year, or a closed last period. Entry 1 (type `closing`, last day of the year, per account and branch): clears every revenue/expense balance to `income_summary`; entry 2: `income_summary` balance -> `retained_earnings` (includes profit already transferred by distributions). Then all periods are closed and the year locked. `reopenYear` (`erp.periods.reopen` + reason, audited, latest year first, refused while non-cancelled distributions exist for the year) reopens periods and REVERSES the closing entries; single periods of a closed year cannot be reopened. A re-close gets a new run number in its idempotency keys.

Assumptions / gaps: distribution runs are company-wide (branch filter only for the calculation view); the legacy manual owner transaction type "distribution" (Dr retained earnings / Cr cash) still exists and bypasses the workflow - restrict in Phase 10; estimated tax is never posted as a liability; no bad-debt/receivable quality adjustment in distributable cash; the reserve is appropriated only when a run is posted; posting date is "today" (the current period must be open).

## 8. Phase 7 - financial reports & dashboard (done)
Code: `Services/Erp/Reports/{ErpReportFilter,ReportBase,ReportCatalog,ReportExporter,ActivityReports,CashFlowReport,PartyReports,InventoryReport,TaxReport,EquityReport,BranchReports,ReconciliationReport,DashboardService}`, `Accounting/BranchTransferService`, `CoaTemplate::ensureRoles`; controllers `ReportController` (index/show/export), `DashboardController`, `BranchController`; strings `erp_rep` (en in `resources/lang-erp`, ar in `lang/ar`). Tests: `tests/Feature/ErpReportsTest.php`. Every figure is computed from posted/reversed journal lines (never `current_balance`); reports return one normalized shape `{title, cards, tables[], notes, meta}` that feeds the HTML page, CSV (`reports/{report}/export`, `erp.reports.export`, UTF-8 BOM, formula-safe) and PDF (`?format=pdf`, dompdf + ar-php like the invoice).
* **Routes**: `erp/reports` (index of all reports + links to profit distribution, asset register, party ageing), `erp/reports/{report}`, `.../export`, `erp/dashboard` (`erp.dashboard.view`), `erp/branches` + `POST erp/branches/transfer`. Filters: date range, branch, account, transaction type (entry type or source type), customer, supplier, payment method (orders), currency (entry currency) - each report declares which apply.
* **Reports**: trial balance, general ledger, P&L, balance sheet, cash flow, journal entries, receivables, payables (party balances + ageing, reconciled to the control account with an "unattributed" line), sales, purchases, sales returns, expenses (by account + mapped category), COGS, inventory valuation, branch profitability, owner equity, tax/VAT, reconciliation.
* **Cash flow (direct)**: cash-like = cash, bank, pos_cash, gateway, wallet. An entry's net cash movement is attributed to its non-cash counter lines and classified by counter role: investing (fixed_assets, accumulated_depreciation), financing (owner_capital, drawings, branch_capital, retained_earnings, distributions_payable, reserves, income_summary, loans, opening_equity), internal transfers (`branch_transfer`, cash-to-cash moves) and operating (rest). opening + net change = closing by construction (checked and flagged). With a branch filter the entry's movement is scaled over its counters.
* **Inventory valuation**: ledger `inventory` per branch (+ `inventory_transit` shown separately) vs `SUM(ROUND(on_hand*avg_cost,2))` of `stock_levels`; difference + status per branch. Stock is always "now".
* **Tax**: `vat_payable` by month: collected (order entries), returns (order_return entries, negative), cancellations (reversals of order entries), other (settlements/manual); opening + net = closing = ledger balance.
* **Reconciliation** (expected = business records, actual = ledger, |diff| <= 0.01 -> ok): goods revenue vs Net Profit report revenue; COGS vs order COGS - returned COGS; net profit vs Net Profit report net; receivable (minus customer advances) vs sum over confirmed orders of total - paid + refunded, less returned goods; payable vs received PO totals + non-PO payable movements; inventory vs stock valuation; carrier COD receivable vs delivered-unsettled `cod_amount`. Known, expected divergences (depreciation/other income in net profit, tax-exclusive return VAT, stock set up without documents) show as mismatches instead of being hidden.
* **Branch rule**: branch-specific views need `erp.branches.view`; without it the branch filter is ignored (consolidated only), `branch-profitability` and the Branch Accounting page return 403, and the dashboard hides the branch picker. A branch id of another tenant is a 404.
* **Branch Accounting**: per branch P&L, cash, ledger inventory, stock sent/received (stock_transfer entries on `inventory`), cash sent/received (`branch_transfer` clearing). Cash transfer = Dr `branch_transfer` + Cr cash (source branch), Dr cash (destination) + Cr `branch_transfer`: consolidated cash/profit unchanged, idempotent by form token. Role `branch_transfer` (code 1150) is in `CoaTemplate`; `CoaTemplate::ensureRoles()` creates it lazily for tenants seeded earlier (next free code if 1150 is taken).
* **Dashboard**: KPIs reuse `LedgerReports::profitLoss/balanceSheet/balancesByRole` (total/net revenue, COGS, gross profit, opex, net profit, cash & bank, AR, AP, inventory, owner equity, liabilities) so they equal the statements (tested); monthly revenue vs expense trend (last 6 months to the end date), net profit by branch, top expense accounts, last 10 entries; charts via the existing Chart.js `_chart` partial.
* Gaps: Journal entries page caps at 1000 lines (export is unlimited); no scheduled/emailed reports; PDFs are generic tables.

## 9. Phase 8 - central ERP monitoring (done)
Code: `Services/Erp/Central/{CentralErpService,AnomalyService,ExchangeRates,Csv}`, controllers `Central/Erp/{HubController,FinanceController,AuditController}`, views `resources/views/central/erp/*`, strings `erp_central` (en in `resources/lang-erp`, ar in `lang/ar`), migrations `2026_12_14_100001` (`erp_exchange_rates`) and `_100002` (re-syncs permissions so `erp_central.view|reports|audit` reach existing installs). Sidebar item "ERP" (perm `erp_central.view`) -> `/admin/erp` hub of cards; every card is its own page (`central.erp.*`). Tests: `tests/Feature/ErpCentralTest.php`.
* **Permissions**: hub `erp_central.view`; overview, drill-down, performance, consolidated, transactions, parties, inventory, periods and the overview CSV `erp_central.reports`; anomalies, anomalies CSV and audit log `erp_central.audit`. The hub only shows cards the user may open. Everything is read-only (GET only); tenant admins cannot reach these routes (central guard).
* **Tenant isolation**: all reads are `GROUP BY tenant_id` on raw `DB::table` queries (no model global scopes, no per-tenant loops, bounded query count - asserted in tests). Totals are only ever added within ONE currency group (`tenants.currency_code`); EGP and USD are never summed. The tenant drill-down (`erp/tenants/{id}`, 404 on unknown id) renders that tenant's own trial balance / P&L / balance sheet through `ReportCatalog` inside `TenantContext` (restored afterwards) with a banner naming tenant + currency. Entry detail reads lines by entry id AND the entry's own tenant.
* **Tenant Overview**: per tenant revenue/expenses/net (window `from..to`, closing entries excluded like the tenant P&L), cash (cash,bank,pos_cash,gateway,wallet), AR (`receivable`), AP (`payable`), inventory, equity (equity accounts + residual earnings), last posting date, open/closed period counts, anomaly count (fast checks); filter by name, currency, period status, anomalies; sort by any figure.
* **Performance / Consolidated**: per currency group tenants side by side (margin, share of group revenue, rank by net profit); consolidated page = labelled "Consolidated view - CCY" totals with the per-tenant breakdown beneath. Optional "also show converted to X": uses ONLY stored rates (`erp_exchange_rates`: `tenant_id` NULL = global, else tenant override; row = 1 `from_currency` is `rate` `to_currency` from `effective_date`; latest effective <= today wins; tenant row beats global; the inverse of a stored pair is used when the direct pair is missing). A converted figure is labelled with the rate/date/scope used per tenant; tenants without a rate are named and excluded, the native per-currency subtotal stays the only valid total. Rates are entered per tenant in ERP Settings (permission `erp.settings.manage`, audited as `rate.saved`); global rates are inserted centrally (no UI yet: `ExchangeRate::create(['tenant_id'=>null,...])`).
* **Transactions**: entries across tenants (filters tenant, branch, account code, dates, status, type, source, text; paginated 25) -> read-only entry detail. **Receivables/Payables**: per tenant outstanding customers/suppliers (party lines on receivable/customer_advances/payable/supplier_advances), top 5 debtors/creditors, optional ageing buckets (per tenant via `PartyLedger::ageing` inside TenantContext - opt-in because it is the only per-tenant loop). **Inventory**: ledger `inventory` (+ transit) vs `stock_levels` valuation, diff and status. **Periods**: counts, last closed end, per-tenant period list, reopen history from `erp_audit_logs` (`period.reopened`, `year.reopened`).
* **AnomalyService** (shared; tenant ERP hub shows an "Alerts" panel for its own tenant, central page lists all tenants, filters tenant/severity/check, CSV export): `unbalanced_entry` (high), `negative_cash` (high), `order_without_journal` (high; only orders created after the tenant's first journal, history is Phase 9), `control_mismatch` (high; receivable/payable/advances cached balance vs sum of posted lines), `posting_failed` (medium, last 30 days), `backdated_entry` (medium; posted > 3 days after its period ended, closing entries excluded), `negative_inventory`, `negative_stock`, `period_reopened` (medium, last 90 days), `old_draft` (low, > 7 days), `large_manual` (low; manual/adjusting total >= `config('erp.large_manual_threshold', 100000)` in tenant currency), `profit_mismatch` (low, ledger net profit vs Net Profit report; deep/opt-in because it runs the legacy report per tenant; depreciation/manual revenue legitimately explain small gaps). Each row: tenant, code, severity, count, a sample entry id (link) and detail.
* **Audit log viewer**: `erp_audit_logs` across tenants, filters tenant/action/user/date, paginated 30. **CSV** (UTF-8 BOM, formula-safe): tenant overview (respects the filters) and anomalies.
* Gaps: no global-rate UI; the overview anomaly count uses fast checks only; party ageing and deep checks are per-tenant loops; no scheduled alert emails.

## 10. Phase 9 - historical backfill + reconciliation (done)
Code: `Services/Erp/Backfill/{BackfillService,OpeningState}`, `Services/Erp/Reconciliation/ReconcileRunner`, commands `erp:backfill`, `erp:reconcile`, controller `Tenant/Erp/MigrationController` (card "Migration", perm `erp.settings.manage`), central page `central/erp/migration` (card on the central hub, `erp_central.audit`), tables `erp_backfill_runs`, `erp_opening_orders`. Tests: `tests/Feature/ErpBackfillTest.php`.
* **Backfill** (`erp:backfill --tenant=SLUG --from=YYYY-MM-DD [--dry-run] [--only=orders,...]`): documents dated on/after `--from` are posted through the Phase-4 posters at their OWN business dates with their deterministic idempotency keys, so reruns create nothing and business rows are never modified. Documents before `--from` are carried by opening entries (`opening:{from}:receivable|carrier|payable|inventory`, against `opening_equity`); orders confirmed before the cutover only post their later movement (baseline in `erp_opening_orders`). Cash/bank cannot be inferred: enter a manual opening journal. Closed periods at/after `--from` abort the run with a message. Failures are isolated per document (`backfill.failed` audit row, run = `partial`, exit 1). Every real run writes an `erp_backfill_runs` row.
* **Inventory opening**: plug = (stock valuation - COGS booked for confirmed-but-unshipped orders) - ledger inventory, computed LAST once the documents are in. Stock leaves `on_hand` at shipping while COGS is booked at confirmation, so the Inventory report/reconciliation use `expected = stock valuation - committed COGS` (committed = posted-confirm orders in confirmed/processing with `stock_deducted = false`).
* **auto_posting_enabled** (checked by `PostingGuard`, hence by every Phase-4 listener): migration `2026_12_15_100001` writes `false` for every tenant that exists when it runs (pre-ERP tenants must not post before opening balances exist); tenants created later have no row and default to `true`. A completed full backfill turns it on (audited `auto_posting.enabled`); admins can toggle it on the Migration page.
* **Migration page**: backfill runs, reconciliation with a Re-run button, documented benign differences, missing-journal detection and "Post missing" (`BackfillService::missing(true)`, idempotent, from the opening date), auto-posting switch. All audited.
* **Reconcile** (`erp:reconcile [--tenant=] [--json]`): runs the Reconciliation report from the opening date to today; exit 1 on any mismatch.

### Production runbook (run per tenant, in this order)
1. Deploy code, then `php artisan migrate --force` (creates ERP tables, seeds ERP permissions incl. central ones, adds `erp_backfill_runs`; existing tenants get `auto_posting_enabled = false`).
2. `php artisan permission:cache-reset` (or `optimize:clear`) so the new permissions are seen; assign them to roles as needed.
3. Dry run: `php artisan erp:backfill --tenant=SLUG --from=YYYY-MM-DD --dry-run` (nothing is written; review counts and the opening totals).
4. Real run: `php artisan erp:backfill --tenant=SLUG --from=YYYY-MM-DD` (rerunnable; on `partial` fix the failures in the audit log and rerun). Post a manual opening journal for cash/bank.
5. `php artisan erp:reconcile --tenant=SLUG` must exit 0; otherwise review the differences against the documented list on the Migration page.
6. Confirm auto-posting is ON (the completed backfill enables it; otherwise toggle it on the Migration page), then use "Detect/Post missing" to close any gap created between the backfill and enabling.
7. Schedule: `routes/console.php` already schedules `erp:depreciate`; optionally add `erp:reconcile` daily and alert on a non-zero exit. Never run the backfill with `--from` earlier than a closed period.


## 11. Phase 10 - security audit, integrity, performance, production readiness (done)
Full write-up: `docs/ERP_FINAL_REPORT.md`. Code: `Services/Erp/Reconciliation/LedgerVerifier` + `erp:verify`, migration `2026_12_16_100001_erp_integrity_constraints_and_indexes`, tests `ErpSecurityTest`, `ErpPerformanceTest`, `ErpProductionReadinessTest`.

**Final architecture** (unchanged since phase 9): one shared DB, `tenant_id` + `BelongsToTenant` on every ERP table; `JournalService` is the ONLY ledger/balance writer; posters/listeners are idempotent (`idempotency_key` unique per tenant); central panel = read-only `GROUP BY tenant_id` raw queries; commands run each tenant inside `TenantContext`.

**Security findings fixed in this phase**
1. Cross-tenant ids passed the bare `exists:branches,id` / `suppliers` / `tenant_admins` rules (journal, account, party, owner, asset forms): replaced by tenant-scoped `ErpController::tenantExists()` (422 instead of storing a foreign id).
2. `JournalService::persist` accepted a raw foreign / unknown account id (the controller checked, the service did not): every line account must resolve inside the current tenant.
3. Concurrent double-submit with the same idempotency key raised a unique-violation 500: `post()` now catches it and returns the winner's entry.
4. Period close / reopen were unlocked read-modify-write; posting could race a close. Close/reopen now run in a transaction on the locked period row, `postDraft` re-reads the period under a row lock, close is idempotent.
5. `PeriodService::close` looked for drafts by `period_id` (drafts have none, so the guard never fired): now matched by entry date.
6. Lines could be appended to a posted entry through Eloquent: `JournalLine` `creating` guard added.
7. Customer receipts / supplier payments / advance application read the open balance outside any lock: now executed under the tenant row lock (`JournalService::exclusive`).
8. CSV formula injection: tenant `ReportExporter` only neutralised cells typed `text` and ignored titles, labels and notes; every exporter now uses one `Csv::cell()` (also handles leading whitespace/newline).
9. `PostingGuard` leaked the switched-to tenant into the container when none was bound (queue workers); `erp:depreciate` used ad-hoc tenant switching: both use proper restore / `TenantContext`.
10. Unbounded pages: general ledger (SQL ordered, 5000-line page cap, true closing balance, truncation note; export 50k) and journal-entries export (50k) capped; periods page `year` validated (2000-2100).
Audited and found OK: permission gate on all 76 ERP routes (test iterates them), `{id}` resolution via tenant-scoped models, sort/orderBy whitelists (central overview), raw selects use bound values only, no `{!! !!}` with unescaped user data (`profits` `$row` helper escapes), attachments (private disk, `mimes:jpg,jpeg,png,webp,pdf`, 4 MB), distribution/owner/asset flows already row-locked and keyed.

**Accounting integrity**: posted/reversed entries and their lines are immutable through Eloquent (update/delete/append all throw). The DB additionally enforces CHECK constraints on `erp_journal_lines` (debit >= 0, credit >= 0, one side only) and `erp_journal_entries.status`. Raw SQL that edits amounts (not shape) or balances is NOT blocked by the database (no triggers): protection there is application policy, DB user privileges and `erp:verify` (see below).

**`erp:verify [--tenant=] [--json]`** per tenant (read-only, exit 1 on failure): unbalanced posted entries; cached `current_balance` vs balance recomputed from lines; orphan lines (missing entry/account or tenant mismatch); negative / two-sided lines; posted entries without lines; duplicate idempotency keys / numbers; posted entries without period. Scheduled weekly (Monday 04:10) next to `erp:reconcile` (business-record reconciliation, run daily if desired).

**Performance**: new composite indexes (lines: `(tenant,entry)`, `(tenant,account,branch)`, `(tenant,party_type,party_id,account)`; entries: `(tenant,entry_date)`, `(tenant,branch,entry_date)`, `(tenant,period)`, `(tenant,type,entry_date)`; audit `(tenant,created_at)`). `ErpPerformanceTest` seeds 1000 entries / 2000 lines and asserts bounded query counts and time for 15 reports, dashboard, hub, branches, profits and every central page, and that counts do not grow with data or with the number of tenants.

**Limitations / not implemented (optional)**: budgeting, payroll integration, loan amortisation, bad-debt provisioning, scheduled/emailed reports, PDF/Excel polish, global exchange-rate UI, per-invoice matching of receipts (FIFO ageing only), DB triggers against raw-SQL tampering, estimated tax is never posted. `BelongsToTenant` (app-wide trait) makes `tenant_id` mass-assignable: ERP code never mass-assigns request input, keep it that way.

**Deployment**: see the runbook in section 10, plus: `php artisan migrate --force` (adds the CHECK constraints when existing rows satisfy them, else logs a warning - run `erp:verify`), `php artisan erp:verify` must exit 0, `php artisan optimize` (config/route/view cache are safe: no `env()` outside config), ensure the scheduler cron runs `schedule:run` (depreciate monthly, verify weekly), and no extra env variables are required (optional `config('erp.large_manual_threshold')` defaults to 100000).


## 12. Accounting audit fixes - pass 1 (2026-11-04, docs/qa/ERP_AUDIT_FINDINGS.md)
Policy changes (all documented in docs/CALCULATIONS.md):
* **Revenue recognition** is a per-tenant setting `revenue_recognition` = `shipped` (default) | `delivered` | `confirmed` (legacy; tenants that already posted order journals are pinned to it by migration `2026_12_17_100001`). `RevenueRecognition` is the single source used by `SalesPoster`, the listener, `OpeningState`/backfill, `AnomalyService::ordersWithoutJournal`, `InventoryReport` and the Reconciliation report (which removes confirmed-but-not-recognised orders from the expected side). The rule "ERP profit == Net Profit report" now holds only for `confirmed`; otherwise the difference is the documented timing of in-flight orders.
* **COGS at the shipped cost**: `OrderStatusService::ship` re-values `order_items.unit_cost`/`orders.cogs` to the sale_out movement cost; the ledger uses it; legacy policy trues up (`order:{id}:cogs-trueup`); `delivered` parks it in `goods_in_delivery` (1320). New roles 1320 Goods out for delivery, 1330 Packaging supplies are topped up lazily (`Poster::LATE_ROLES`).
* **Returned to sender**: `shipped -> cancelled` restocks and reverses the recognition family; customers can never cancel after shipment.
* **Refunds** credit the account money is paid FROM (`refund_online` -> gateway, rest -> `refund_from` / default); cancel refunds use the same split (`Poster::refundCredits`).
* **Manual reversal of document auto entries is refused** (`DocumentPoster::DOCUMENT_SOURCES`); `POST erp/journals/{id}/repost` (erp.journals.reverse + post) reverses the entry family and re-posts from the document with a versioned key `{key}:v{n}` (`Poster::reposting`). "Post missing" checks LIVE keys and runs in repost mode. Delta postings (COGS true-up, commission) get counter keys so a later delta is never swallowed.
* **New flows**: affiliate payout paid -> `affpayout:{id}` Dr Affiliate payable / Cr `paid_from`; ambassador rewards -> `ambassador:{aff}:{ref}` Dr Marketing / Cr account; Cash & Bank card (`erp/cash-bank`, view erp.accounts.view, actions erp.journals.post): VAT payment, pay carrier (shipping payable), gateway payout / wallet sweep / POS register close (`SettlementService`); carrier settlement may land in bank; POS "paid at counter" (`CounterSaleService`, `orders.counter_sale`, cash into `pos_cash`, never carrier-settled).
* **Controls**: owner "distribution" transactions refused (ProfitDistributionService only); withdrawals and branch allocations need cover in the paying account; branch capital allocation is zero-sum (cash HQ->branch + owner capital -> branch capital reclass); year-end close also closes drawings to capital per owner; party receipts/advances never settle order-driven receivables (held as customer advance); party money forms need erp.journals.post; party openings only up to the opening date; manual/adjusting journals may not touch control accounts; optional maker-checker `journal_requires_second_poster`; posting failures notify `erp.settings.manage` holders (once per document/day), are listed on the Migration page with a Retry button (`posting.resolved` audit row clears the anomaly); expenses/PO receipts into a closed period are refused up-front (`PeriodGuard`); supplier debit notes default to purchase expenses; landed costs optionally capitalised (`landed_costs`); unpaid expenses may carry a supplier party; expense categories accept `erp_account_id`.
Tests: `tests/Feature/QaProbeErpAccountingTest.php` (REG ERP-001..011), `tests/Feature/ErpAuditFixesTest.php`.

## 13. QA pass 2 (2026-11-04, docs/qa/QA_TEST_REPORT.md + audit pass-2 items)
* **Input policy**: every ERP amount is a plain decimal (`App\Support\ErpRules::money`, <= 14 integer digits, 2 decimals), percentages 0..100 (4 decimals), entry dates 2000-01-01 .. today + 1 year. Stored settings are normalised; `ErpSettings::decimal` / `Money` tolerate legacy values.
* **Periods**: a period can be closed once it has started (current month = soft close); a fiscal year only after its end date.
* **Form tokens**: posting forms carry a hidden `ref`; the same ref with a different total is refused (`duplicate_ref`) instead of silently returning the first entry.
* **Chart of accounts**: only group accounts can be parents.
* **Permissions**: hub/sidebar open for any `erp.*` permission (gate `erp.access`); `erp.owners.view`, `erp.assets.view` (read-only); `erp.audit.view` = tenant audit page; `erp.expenses.manage` = expense-category ledger mapping.
* **UI**: expense paid/unpaid + paid-from + supplier; category ledger account; journal attachments; ERP KPIs on the main tenant dashboard.
