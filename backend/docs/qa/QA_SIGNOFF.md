# ERP Module – Final QA/QC Sign-off (branch `ERP-MODULE`)

> **Historical (pre single-store).** Written while the project was multi-tenant (central domain + per-country tenant subdomains, `tenant_id`, central/tenant panels). Since the 2026-11 single-store conversion there is one shop on `APP_URL`, one admin panel (`/admin`, guard `admin`) and settings via `setting()`; see `docs/REQUIREMENTS.md` §2 and `/var/www/3alraf/REMOVE_MULTI_TENANCY_PLAN.md`. Kept unchanged as history.

Role: lead QA/QC, final independent verification. Date: 2026-11-05. Inputs: `docs/qa/ERP_AUDIT_FINDINGS.md` (ERP-001…031), `docs/qa/QA_TEST_REPORT.md` (QA-001…015), `docs/ERP_PLAN.md` §12/§13, `docs/CALCULATIONS.md`, `docs/ERP_FINAL_REPORT.md`.
The status columns were **not trusted**: every "Fixed" item was re-checked by reading the code and by a test that runs (an existing one or a new one in `tests/Feature/QaSignoffErpTest.php`).

## 1. Scope

- Accounting engine and posters (`app/Services/Erp/**`, listeners, events), the business hooks they consume (orders, returns/refunds, POS, affiliates, expenses, purchases, carrier settlement), the ERP tenant and central UI, permissions, i18n (en / ar-RTL), input validation, period control.
- Out of scope: storefront, WhatsApp, growth modules (covered only by the regression run), real-DB migration (not run, see §8).

## 2. Environment

| Item | Value |
|---|---|
| Code | branch `ERP-MODULE`, working tree (uncommitted pass-1/pass-2 fixes + this sign-off), nothing committed or switched |
| PHP / DB | PHP 8.3, MySQL test DB `3alraf_test` (shared; one Pest file at a time, sequential, each file re-migrates ≈ 60 s) |
| Dev DB | **untouched** – only `migrate:status` (read-only) was run: `2026_12_17_100001` and `2026_12_18_100001` are **Pending** |
| Runner | `php artisan test <file>` per file, sequential, background batches (`timeout 1500` per file) |

## 3. Verification table

Legend: **Y** = verified by code reading + a passing test; evidence = test name (file).
Files: AF = `ErpAuditFixesTest`, PR = `QaProbeErpAccountingTest`, SO = `QaSignoffErpTest` (new), TN = `QaE2eErpTenantTest`, IP = `QaE2eErpI18nPermsTest`, CE = `QaE2eErpCentralTest`, PO = `ErpPostingTest`, RD = `ErpProductionReadinessTest`, POA = `ErpPartiesOwnersAssetsTest`, RP = `ErpReportsTest`.

### Accounting audit (ERP-xxx)

| ID | Claimed | Verified | Evidence | Edge cases tried at sign-off |
|---|---|---|---|---|
| ERP-001 | Fixed | **Y** | PR `REG ERP-001`; SO `VERIFY ERP-001` | refund **before** carrier settlement → default cash, carrier receivable untouched; `refund_from=bank`; explicit `carrier_receivable` (carrier refunded before settling); bogus role (`receivable`, `sales_revenue`) refused by service and HTTP (422) |
| ERP-002 | Fixed | **Y** | PR `REG ERP-002`, `REG ERP-002/011` | cost change between confirm and ship (both policies) |
| ERP-003 | Fixed | **Y** | PR `REG ERP-003`; SO `VERIFY ERP-003` | payout **paid twice** → 2nd call refused (`payout_closed`), retry/"post missing" replay posts nothing more, one journal; invalid `paid_from` refused |
| ERP-004 | Fixed | **Y** | PR `REG ERP-004` | – |
| ERP-005 | Fixed | **Y** | PR `REG ERP-005` | – |
| ERP-006 | Fixed | **Y** | PR `REG ERP-006` | no HQ cash → refused |
| ERP-007 | Fixed | **Y** | PR `REG ERP-007`; SO `VERIFY ERP-007` | **repost of an entry whose period is closed**: reversal + new version dated today in the open period, closed period untouched; repost while the **current** period is also closed → `AccountingException`, transaction rolled back (entry count and balances unchanged) |
| ERP-008 | Fixed | **Y** | PR `REG ERP-008` | – |
| ERP-009 | Fixed | **Y** | PR `REG ERP-009` | – |
| ERP-010 | Fixed | **Y** | AF `ERP-010` | (code read: `Poster::refundCredits` split provider / prepay leg / goods) |
| ERP-011 | Fixed | **Y** | PR `REG ERP-011` ×2, `REG ERP-002/011`; SO `VERIFY ERP-011` ×2 | **policy switched mid-life** shipped→delivered→shipped and confirmed→delivered with in-flight orders: no double revenue/COGS, goods-out-for-delivery returns to 0, `erp:verify` checks pass; **RTS under `delivered`** restocks and clears goods-out-for-delivery; **delivered + partially returned order cannot be RTS-cancelled** (`invalid_transition`) |
| ERP-012 | Fixed | **Y** | AF `ERP-012/024` | VAT payment replay, overpayment refused |
| ERP-013 | Fixed | **Y** | AF `ERP-013` | – |
| ERP-014 | Fixed | **Y** | AF `ERP-014`; SO `VERIFY ERP-014` | **POS counter sale + return/refund** → refund out of POS cash, carrier/cash untouched |
| ERP-015 | Fixed | **Y** | AF `ERP-015` | – |
| ERP-016 | Fixed | **Y** (+1 gap fixed) | AF `ERP-016`; SO `VERIFY QA-015/ERP-016`, `QA-BUG-101` | **backdated expense after closing last month** → field error (HTTP 422 `expense_date`); editing an open-period expense back into the closed month refused; *delete* while today's period is closed was **not** guarded → QA-BUG-101 (fixed) |
| ERP-017 | Fixed | **Y** | AF `ERP-017/018` | – |
| ERP-018 | Fixed | **Y** | AF `ERP-017/018`, IP permission matrix | – |
| ERP-019 | Fixed (configurable) | **Y** | AF `ERP-019` | – |
| ERP-020 | Deferred | n/a | – | see §7 |
| ERP-021 | Partially / Deferred | partial **Y** (attachments, tenant audit UI) | TN + ErpSecurityTest journal attachment, SO `VERIFY ERP-028/029/030` | rest deferred, §7 |
| ERP-022 | Deferred | n/a | – | §7 |
| ERP-023 | Fixed | **Y** | AF `ERP-023` | – |
| ERP-024 | Fixed | **Y** | AF `ERP-012/024` | – |
| ERP-025 | Deferred (documented) | n/a | CALCULATIONS §ERP | §7 |
| ERP-026 | Deferred | n/a | – | §7 |
| ERP-027 | Fixed | **Y** | SO `VERIFY ERP-027` (new: every `erp*` permission module + action has en **and** ar label) | – |
| ERP-028 | Fixed | **Y** | SO `VERIFY ERP-028/029/030`; POA view perms; IP card lists | reports-only user reaches the hub (200); `erp.owners.view` = page 200, create 403 |
| ERP-029 | Fixed | **Y** | SO `VERIFY ERP-028/029/030` | `/admin/erp/audit` 403 without / 200 with `erp.audit.view` |
| ERP-030 | Fixed | **Y** | SO `VERIFY ERP-028/029/030`; AF `ERP-016` (notification) | main dashboard shows `data-erp-kpis` only with `erp.dashboard.view` |
| ERP-031 | Fixed | **Y** | AF `ERP-031` | – |

### Functional QA (QA-xxx)

| ID | Claimed | Verified | Evidence | Edge cases tried at sign-off |
|---|---|---|---|---|
| QA-001 | Fixed | **Y** | TN `QA-BUG-001` | – |
| QA-002 | Fixed | **Y** | TN `QA-BUG-002` | – |
| QA-003 | Fixed | **Y** | TN `QA-BUG-003` | – |
| QA-004 | Fixed | **Y** | TN `QA-BUG-004`; SO `VERIFY QA-004/006` | `ErpRules` boundaries: `99999999999999.99` accepted **and posts** (balance `99999999999999.9900` fits decimal(18,4)); `100000000000000`, 3 decimals, `-1`, `1e3`, `0x1A`, `' 12'`, `1,000`, **Arabic-Indic `١٢٣`**, arrays → field errors; `0` refused for `gt:0`, accepted for `min:0`; percent `100` ok / `100.0001`, `1e1`, 5 decimals refused; dates 1999-12-31 / today+1y+1d refused, 2000-01-01 ok; `١٢٣` on the journal and expense forms = field error, never 500 |
| QA-005 | Fixed | **Y** | TN `QA-BUG-005`; SO `VERIFY QA-005` | **legacy garbage already stored** (`1e1`, `abc`, `١٤`) → profits page still 200 |
| QA-006 | Fixed | **Y** | TN `QA-BUG-006`; SO `VERIFY QA-004/006` | max total |
| QA-007 | Fixed | **Y** | TN `QA-BUG-007` | – |
| QA-008 | Fixed | **Y** | TN `QA-BUG-008` | – |
| QA-009 | Fixed | **Y** | TN `QA-BUG-009` | – |
| QA-010 | Fixed | **Y** | IP `QA-BUG-010`, `QA-BUG-010b` | – |
| QA-011 | Fixed | **Y** | IP `QA-BUG-011` | – |
| QA-012 | Fixed | **Y** | IP `QA-BUG-012`; SO `VERIFY QA-012/013` | **duplicate ref draft → post**: same ref re-submitted with "post now" returns the existing draft (no 2nd entry, not silently posted); changed amount → `duplicate_ref`; after posting the draft, a replay is a no-op |
| QA-013 | Fixed | **Y** | IP `QA-BUG-013`; SO `VERIFY QA-012/013` | – |
| QA-014 | Fixed | **Y** | CE `QA-BUG-014` | – |
| QA-015 | Fixed | **Y** | TN `QA-BUG-015`; SO `VERIFY QA-015/ERP-016` | next month and running year cannot be closed; current month can (soft close, see O-1) |

**Result: 26 of 26 "Fixed" ERP findings and 15 of 15 QA bugs verified; 0 regressions of a claimed fix.**

## 4. New bugs found at sign-off

| ID | Sev | Title | Status |
|---|---|---|---|
| QA-BUG-101 | Medium | Deleting (or editing) an expense while **today's** period is closed (soft close of the running month) deleted the expense, but its reversal journal (dated today) failed inside `PostingGuard` → the ledger kept an expense whose document no longer exists (ERP-016 gap; create/update only checked the expense dates) | **Fixed**: `ExpenseService::delete` and `::update` also call `PeriodGuard::assertOpen(today)` → 422 field error `expense_date`. Test `QA-BUG-101 …` (SO) fails without the fix (HTTP 200, journal orphaned), passes with it |

Observations (not defects, product decisions – documented, not changed):

- **O-1 Soft close of the running month.** `PeriodService::close` allows closing the current month before it ends (by design, QA-015 fix). Every automatic posting dated today (orders shipped/delivered, payments, refunds, POS) then fails into `PostingGuard` → anomaly + notification + Migration-page retry. Business risk Medium: an accountant closing "November" on 5 Nov silently parks a month of postings. Recommend refusing `ends_on >= today` like `closeYear`, or a confirmation dialog.
- **O-2 POS counter sale is not atomic.** `PosController::store` places the order, then `CounterSaleService::complete` runs four status changes outside one transaction; a failure mid-way (e.g. stock) leaves a pending/confirmed order and an error message. No ledger corruption (posting follows status), but the cashier may retry and create a second order. Low.
- **O-3 RTS reverses the shipping expense.** Returned-to-sender reverses the whole recognition family, incl. `Dr Shipping expense / Cr Shipping payable`; carriers usually still charge a failed delivery. Low – record it with a manual/carrier entry until a "failed-delivery fee" field exists.
- **O-4 `revenue_recognition` switch mid-life.** Allowed and audited (`settings.updated`); verified safe for in-flight orders (SO). Orders already in confirmed/processing when switching *to* `confirmed` are recognised only at their next status change (Reconciliation "missing" / "post missing" covers them). Recommend switching only at a period boundary (IAS 8 consistency); no code change.
- **O-5 `sales_returns` is a contra-revenue account** with revenue (credit) normal balance – a debit shows as a negative `current_balance` (e.g. −100). Reports present it correctly; only raw balance consumers must know the convention.
- **O-6 Non-ERP refund amount input** (`ReturnController::refund`, `amount` `numeric`) still accepts `1e3`; `Money::str` normalises it safely (no 500), but it is not covered by `ErpRules`. Low.

## 5. Test results

### ERP / QA files (run 1 = the 15 existing files before the sign-off fix, + the new file; each file alone; identical 194/194 again in run 2)

| File | Tests | Passed |
|---|---|---|
| ErpAuditFixesTest | 10 | 10 |
| ErpBackfillTest | 11 | 11 |
| ErpCentralTest | 12 | 12 |
| ErpCoreTest | 8 | 8 |
| ErpPartiesOwnersAssetsTest | 9 | 9 |
| ErpPerformanceTest | 5 | 5 |
| ErpPostingTest | 20 | 20 |
| ErpProductionReadinessTest | 6 | 6 |
| ErpProfitDistributionTest | 13 | 13 |
| ErpReportsTest | 12 | 12 |
| ErpSecurityTest | 13 | 13 |
| QaE2eErpCentralTest | 8 | 8 |
| QaE2eErpI18nPermsTest | 15 | 15 |
| QaE2eErpTenantTest | 27 | 27 |
| QaProbeErpAccountingTest | 12 | 12 |
| QaSignoffErpTest (new) | 13 | 13 |
| **Total** | **194** | **194** |

### Whole suite (run 2, after the fix, every file alone, `tests/Unit` + `tests/Feature`)

79 files (74 Feature + 5 Unit), each in its own process, sequential, after the QA-BUG-101 fix and the readiness-test extension.

| | Tests | Passed | Failed | Errors | Assertions |
|---|---|---|---|---|---|
| Phase-10 baseline (single process) | 760 | 709 | 4 | 47 | 20705 |
| **Sign-off, raw per-file run** | **863** | **772** | **4** | **87** | 21520 |
| Sign-off, after re-running the 3 Growth files together (see G) | 863 | **813** | 4 | **46** | – |
| of which ERP/QA files (16) | 194 | **194** | 0 | 0 | 2482 |

+103 tests vs baseline = the ERP pass-1/pass-2/QA test files and this sign-off file. **No ERP-caused regression; no new non-passing test.**

Classification of every non-passing test:

| File | Non-passing | Class | Cause |
|---|---|---|---|
| WhatsappChatApiTest | 24 errors | environment (baseline) | `storage/framework/testing/disks/chat-test` owned by `b`, mode 700 → `FilesystemIterator` permission denied |
| WhatsappChatIngestTest | 17 errors | environment (baseline) | same |
| MediaTest | 4 errors | environment (baseline) | `storage/framework/testing/disks/public/products` owned by `b`, mode 700 |
| CitySeederTest | 1 error | pre-existing (baseline) | `Attempt to read property "id" on null` (non-ERP seeder data) |
| BlogTest | 1 failure | pre-existing (baseline) | content-sanitising assertion |
| HardeningTest | 1 failure | pre-existing (baseline) | Arabic 404 / store-unavailable page text |
| QrCodeTest | 1 failure | pre-existing (baseline) | logo PNG check |
| StorefrontTest | 1 failure | pre-existing (baseline) | sitemap Content-Type with charset |
| GrowthGapFixesTest | 12 errors | **G: run-mode artifact** | uses `growthClient()`/`ownerOf()` defined in `GrowthFoundationTest.php`; undefined when the file runs alone |
| GrowthMetricsTest | 29 errors | **G: run-mode artifact** | `require_once GrowthFoundationTest.php` imports that file's `beforeEach` (creates tenant `uae`) → duplicate `uae` when run alone |

G: `vendor/bin/pest tests/Feature/GrowthFoundationTest.php tests/Feature/GrowthGapFixesTest.php tests/Feature/GrowthMetricsTest.php` in one process → **43/43 passed**. Files unchanged since `c83acc7` (not ERP). `GrowthOffersTest` (baseline error) now passes.
45 environment errors + CitySeeder = 46 errors (baseline 47 = these + GrowthOffers).

## 6. Static checks

| Check | Result |
|---|---|
| `php -l` on every changed / new PHP file (`git status`, 129 files) | 0 errors |
| `php artisan route:list --name=erp` | OK, 83 routes |
| `php artisan view:cache` then `view:clear` | OK (all Blade compiles) |
| `dd(` / `dump(` / `var_dump(` / `print_r(` / `ray(` / `fwrite(STDERR` in `app/`, `resources/views`, `routes/`, ERP migrations | none |
| Every `__('erp…')` key resolves in en + ar | independent scan of **all** `app/`, `resources/views`, `routes/`, `config/`: 1015 references, 0 missing; groups `erp`, `erp_acc`, `erp_central`, `erp_dist`, `erp_ext`, `erp_rep` have identical en/ar key sets (94 / 219 / 149 / 104 / 136 / 207 keys) |
| `ErpProductionReadinessTest` | **extended**: `erp_acc` added to the en/ar sync groups; the key-resolution test now scans the whole `app/` + `resources/views` (ERP keys used by expenses, returns, POS, affiliate payouts, `ErpRules`, `DecimalAmount`); debug-code / blade-compile checks keep their ERP-only scope |

## 7. Deferred items and business risk

| ID | Item | Business risk |
|---|---|---|
| ERP-020 | Guest orders post AR without a party | Low–Medium: customer sub-ledger/ageing excludes guests; control account still reconciles through the "without a party" line. Needs product decision (auto-create customer by phone) |
| ERP-021 | Excel export, recurring expenses, expense/PO approvals, bank-statement reconciliation, input VAT / tax codes, FX revaluation, extra reports | Medium: VAT return is output-only (input VAT on purchases not recoverable in the ledger); multi-currency tenants are reported in base currency only; no bank reconciliation → cash/bank balances must be checked manually against statements |
| ERP-022 | Sales discounts not presented gross | Low: presentation only, net revenue correct |
| ERP-025 | Ledger by event date vs legacy Net Profit report by `created_at` | Low: expected month-boundary differences in `erp:reconcile`; documented |
| ERP-026 | One refund per return; short refund remainder stays as customer credit on AR | Low–Medium: unexplained credit balances on AR for partially refunded returns |
| O-1…O-6 | see §4 | O-1 Medium, others Low |

## 8. Pending deploy steps (real DB – NOT run during QA)

1. Back up the production DB. Deploy code and run migrations **in the same release window** – the code reads the new columns (`orders.counter_sale`, `order_returns.refund_from/refund_online`, `affiliate_payouts.paid_from`, `expenses.supplier_id`):
   `php artisan migrate --force` → runs **`2026_12_17_100001_erp_accounting_audit_fixes`** (columns + pins `revenue_recognition=confirmed` for every tenant that already has order recognition journals, so existing ledgers are never re-timed) and **`2026_12_18_100001_seed_erp_view_permissions`** (adds `erp.owners.view`, `erp.assets.view`, re-syncs ERP permissions to `tenant-owner`).
2. `php artisan permission:cache-reset`; review custom roles (new view-only perms, `erp.journals.post` now required for party money forms, `erp.expenses.manage` for category→account mapping).
3. Per tenant (runbook, `docs/ERP_PLAN.md` §10): `php artisan erp:backfill --tenant=SLUG --from=YYYY-MM-DD --dry-run` → review → real run → `php artisan erp:reconcile --tenant=SLUG` (exit 0 or explained ERP-025 timing lines) → `php artisan erp:verify --tenant=SLUG` (exit 0) → confirm auto-posting is on (Migration page) → "Post missing" once, then check the posting-failures list is empty.
4. Per tenant: decide `revenue_recognition` (pinned tenants stay `confirmed` until the accountant switches at a period boundary, O-4) and `landed_costs`; map expense categories to ledger accounts (ERP-013).
5. `php artisan optimize`; scheduler running (`erp:depreciate` monthly, `erp:verify` weekly; optionally `erp:reconcile` daily with alerting).
6. Ops instruction (O-1): do not close the running month until it has ended.

## 9. Known environment issues (test machine)

- `storage/framework/testing/disks/chat-test` and `storage/framework/testing/disks/public/products` are owned by user `b`, mode `drwx--S---` → the test runner (`joe`) cannot write there (WhatsApp chat / media tests error). `lang/en` is owned by `b`, mode `drwxr-sr-x` (not group-writable). Fix:
  ```
  sudo chown -R joe:www-data /var/www/3alraf/backend/storage/framework/testing /var/www/3alraf/backend/lang/en
  sudo chmod -R ug+rwX /var/www/3alraf/backend/storage/framework/testing /var/www/3alraf/backend/lang/en
  sudo find /var/www/3alraf/backend/storage/framework/testing -type d -exec chmod g+s {} +
  ```
- Shared MySQL test DB: run Pest files one at a time (parallel runs corrupt each other's `RefreshDatabase`).

## 10. Changes made during sign-off

| File | Change |
|---|---|
| `app/Services/ExpenseService.php` | QA-BUG-101: `PeriodGuard::assertOpen(today)` in `delete()` and `update()` |
| `tests/Feature/QaSignoffErpTest.php` | new: 12 `VERIFY …` + 1 `QA-BUG-101 …` test |
| `tests/Feature/ErpProductionReadinessTest.php` | `erp_acc` in the en/ar sync groups; key-resolution scan covers all of `app/` + `resources/views` (`erpKeyFiles()`) |
| `docs/qa/QA_SIGNOFF.md` | this report |

## 11. Recommendation

**GO-WITH-CONDITIONS.**

All 26 fixed accounting findings and all 15 QA bugs are independently verified; the only new defect (QA-BUG-101, Medium) is fixed and covered; ERP/QA suite 194/194; whole suite has no regression against the Phase-10 baseline (every non-passing test is environment, pre-existing non-ERP, or a per-file run artifact).

Conditions (before / at go-live):
1. Run the two **pending migrations** (`2026_12_17_100001`, `2026_12_18_100001`) in the same release as the code, after a DB backup; then `permission:cache-reset` and role review (§8.1–8.2). The code is **not** safe to serve before step 1 (new columns).
2. Per-tenant **backfill → reconcile → verify** runbook with exit 0 (or explained ERP-025 timing lines) before the ERP numbers are used for profit distribution (§8.3).
3. Accountants instructed **not to close the running month** before it ends (O-1) until a guard is added; decide `revenue_recognition` per tenant and switch only at a period boundary (O-4).
4. Commit the QA-BUG-101 fix and the sign-off tests together with the rest of the branch; fix test-machine permissions (§9) so the full suite runs clean.
5. Accept the deferred items' business risk (§7), notably no input VAT and no bank-statement reconciliation (manual bank checks) — product owner sign-off.
