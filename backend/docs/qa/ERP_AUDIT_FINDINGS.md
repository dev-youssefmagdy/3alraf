# ERP Module – External QA / Accounting Audit (branch `ERP-MODULE`)

Auditor role: senior ERP/accounting reviewer + business analyst. Scope: `app/Services/Erp/**`, `app/Listeners/Erp`, `app/Events/Erp`, ERP controllers/views/migrations/tests, and the business services they hook into (orders, payments, returns/refunds, purchases, stock, expenses, carrier settlement, POS, affiliates). Ground truth for order math: `docs/CALCULATIONS.md`.
No application code was changed by the audit. Proof tests: `tests/Feature/QaProbeErpAccountingTest.php` (9 probes; all 9 failed = all 9 findings confirmed, run 2026-11-04). **Pass 1 fixes: see the Status section; the probes are now passing `REG ERP-00x` regression tests.**

## Summary

| ID | Sev | Category | Title | Proof |
|---|---|---|---|---|
| ERP-001 | **Critical** | accounting | Return/cancel refunds of COD orders are credited to *Carrier COD receivable* instead of the account that actually paid the money | probe 001 ✗ |
| ERP-002 | High | accounting / data-integrity | COGS & inventory credit booked at confirmation from the order cost snapshot, stock leaves at shipping at the branch average → GL inventory permanently drifts from stock valuation | probe 002 ✗ |
| ERP-003 | High | integration | Affiliate payouts (and ambassador rewards) marked paid post nothing: affiliate payable never cleared, cash never credited | probe 003 ✗ |
| ERP-004 | Medium | accounting | Full return after the commission was PAID reverses the commission expense (paid commissions are "never altered" in business module) | probe 004 ✗ |
| ERP-005 | High | accounting / control | Owner transaction type `distribution` pays owners straight out of retained earnings, bypassing the controlled distribution workflow | probe 005 ✗ |
| ERP-006 | High | accounting | Branch capital allocation `Dr Cash(branch) / Cr Branch capital` creates cash and equity out of nothing | probe 006 ✗ |
| ERP-007 | High | data-integrity | Manually reversing an auto entry is allowed; the idempotency key then blocks every re-post and "post missing" treats the document as posted | probe 007 ✗ |
| ERP-008 | Medium | accounting | Packaging cost credited to Inventory although packaging is never in the stock ledger | probe 008 ✗ |
| ERP-009 | Medium | accounting | Supplier debit note (default counter) credits Inventory with no stock movement | probe 009 ✗ |
| ERP-010 | High | accounting | COD with online/wallet prepaid shipping: whole goods refund is credited to Gateway/Wallet though only the shipping leg went through it | code |
| ERP-011 | High | accounting (policy) | Revenue, VAT and COGS recognised at admin *confirmation* (before shipment/delivery); no cancel/RTO path after shipping | code |
| ERP-012 | Medium | gap / accounting | Clearing liabilities/assets are never settled by any flow: shipping payable (prepaid orders, return shipping), VAT payable, gateway balance | code |
| ERP-013 | High | gap / ux | Expense UI exposes neither `is_paid`/`paid_from` nor category→account mapping: every expense = Dr Other expenses / Cr Cash | code |
| ERP-014 | Medium | gap / integration | POS is just an admin-entered pending order; no counter sale, `pos_cash` (1115) is never posted by any business flow | code |
| ERP-015 | Medium | data-integrity | Customer receipts recorded in the ERP party page do not touch `orders.paid_amount`: the later COD delivery posts the same money again | code |
| ERP-016 | Medium | data-integrity | `PostingGuard` swallows ledger failures (closed period, inactive account…) — the business document exists with no journal; only an anomaly row, no notification | code |
| ERP-017 | Medium | security / control | Manual journals: no maker-checker, can hit control accounts (AR/AP/Inventory/VAT/carrier) without party, attachment not supported | code |
| ERP-018 | Medium | permissions | Party actions (receipts, supplier payments, credit/debit notes, opening balances) post immediately with only `erp.journals.create` (bypass `erp.journals.post`) | code |
| ERP-019 | Medium | accounting (policy) | PO shipping/other landed costs are expensed instead of capitalised into inventory (IAS 2) | code |
| ERP-020 | Medium | data-integrity | Guest orders (customer_id NULL) post receivable lines with no party → customer sub-ledger/ageing never ties to AR | code |
| ERP-021 | Medium | gap | Requirements not delivered: Excel export, recurring expenses, expense/purchase approvals, bank reconciliation / Cash & Bank card, input VAT & per-country tax codes, real multi-currency/FX revaluation, global FX UI, opening balance on account creation, journal attachments, tenant audit UI, 18 of 22 reports | code |
| ERP-022 | Low | accounting | `sales_discounts` (4160) never used — P&L cannot show gross sales vs discounts | code |
| ERP-023 | Low | accounting | Gateway fee reversed on cancel (providers keep it); COD-method payment fee credited to carrier receivable that settlement never clears | code |
| ERP-024 | Low | accounting | Carrier settlement always debits `cash` (no bank), fee = min(shipping_cost, cod) leaves residual shipping payable | code |
| ERP-025 | Low | accounting | Ledger dated by event date, legacy Net Profit report by order `created_at` → structural reconciliation mismatches at every month boundary | code |
| ERP-026 | Low | gap | One refund per return; a short refund leaves an unexplained customer credit on AR | code |
| ERP-027 | Low | permissions / ux | Role editor shows ERP modules/actions without labels (EN/AR): "Erp.accounts", "Post", "Reverse", "Distribute" in English inside Arabic UI | code |
| ERP-028 | Low | permissions / ux | ERP sidebar + hub require `erp.dashboard.view`; a reports-only accountant cannot navigate; Owners/Assets cards have only `manage` perms (no view-only) | code |
| ERP-029 | Low | permissions | `erp.expenses.manage` and `erp.audit.view` are seeded but enforced nowhere (no tenant audit page) | code |
| ERP-030 | Low | ux / integration | No notifications for posting failures / high anomalies; main tenant dashboard has no ERP link/KPI | code |
| ERP-031 | Low | accounting | Owner drawings never closed to capital at year end; withdrawals have no cash-availability check | code |

**Counts:** Critical 1 · High 8 · Medium 12 · Low 10 (total 31).


## Status (pass 1 - accounting / integration / data-integrity / permissions; pass 2 - UI / i18n / permissions, 2026-11-04)

| ID | Status | Notes |
|---|---|---|
| ERP-001 | **Fixed** | Refund credits the account paid FROM (`refund_online` → gateway, rest → `refund_from` / default cash for COD). Refund form has a "paid from" select. REG ERP-001; ErpPostingTest refund expectation corrected |
| ERP-002 | **Fixed** | `orders.cogs`/`unit_cost` re-valued at shipment to the sale_out cost; COGS posted at that value; legacy policy trues up; ledger inventory == stock valuation. REG ERP-002 (x2) |
| ERP-003 | **Fixed** | `AffiliatePayoutPaid` → Dr Affiliate payable / Cr `paid_from`; ambassador rewards → Dr Marketing / Cr account. REG ERP-003 |
| ERP-004 | **Fixed** | Paid commission rows keep their amount in `syncCommission`. REG ERP-004 |
| ERP-005 | **Fixed** | `distribution` refused for new owner transactions (history kept); ProfitDistributionService only. REG ERP-005 |
| ERP-006 | **Fixed** | Allocation = cash HQ/source → branch + owner capital → branch capital reclass, needs cover. REG ERP-006 |
| ERP-007 | **Fixed** | Manual reversal of document auto entries refused; "Repost from document" (versioned keys); post-missing detects reversed sources. REG ERP-007 |
| ERP-008 | **Fixed** | Packaging cost Cr Packaging supplies (1330). REG ERP-008 |
| ERP-009 | **Fixed** | Supplier debit note defaults to purchase expenses. REG ERP-009 |
| ERP-010 | **Fixed** | Same split as ERP-001 by leg (provider part / prepay-method leg / goods cash). ErpAuditFixesTest |
| ERP-011 | **Fixed** | Setting `revenue_recognition` shipped (default) / delivered / confirmed (legacy, pinned for tenants with history); shipped → cancelled (returned to sender) restocks and reverses. REG ERP-011 (x2) |
| ERP-012 | **Fixed** | Cash & Bank card: VAT payment, pay carrier, gateway payout / wallet sweep (statement import/bank reconciliation → Deferred, ERP-021) |
| ERP-013 | **Fixed** | Expense form: payment status (paid / unpaid bill), *paid from* (cash/bank/POS cash/wallet/gateway), supplier (required for unpaid bills → party on the payable line); expense-category form: ledger account select (needs `erp.expenses.manage`). Name-based default mapping → Deferred (would silently re-route existing categories) |
| ERP-014 | **Fixed** | POS `paid_at_counter`: completed at once, cash into POS cash, excluded from carrier settlement; register close via Cash & Bank payout |
| ERP-015 | **Fixed** | Party receipts/advances settle only non-order receivables; excess is a customer advance |
| ERP-016 | **Fixed** | Notification to `erp.settings.manage` holders, Migration page list + Retry, `posting.resolved` clears the anomaly; expenses / PO receipts into a closed period refused up-front |
| ERP-017 | **Fixed** | + pass 2: journal attachment upload (PDF/image ≤ 5 MB, private disk) on the journal form, download link on the entry page (`?download=attachment`, `erp.journals.view`) |
| ERP-018 | **Fixed** | Party actions need `erp.journals.post`; openings only up to the opening date |
| ERP-019 | **Fixed (configurable)** | `landed_costs` = expense (default, business costing unchanged) / capitalise (IAS 2 allocation into unit cost) |
| ERP-020 | Deferred | Guest AR already reconciles via the "without a party" line; auto-creating customers changes the storefront flow - needs a product decision |
| ERP-021 | Partially fixed / Deferred | Journal attachments and tenant audit UI delivered in pass 2; Excel export, recurring expenses/approvals, bank statement reconciliation, input VAT/tax codes, FX revaluation, extra reports remain Deferred (new features, product decisions) |
| ERP-022 | Deferred | Gross sales vs discount presentation; changes the reconciliation basis, net unchanged |
| ERP-023 | **Fixed** | Online gateway fee posted at payment, kept on cancel; COD payment fee credited to shipping payable |
| ERP-024 | **Fixed** | Carrier settlement `into` cash/bank/wallet; residual payable cleared via "Pay carrier" |
| ERP-025 | Deferred (documented) | Timing basis difference documented in CALCULATIONS (Net Profit report by created_at, ledger by event date) |
| ERP-026 | Deferred | One refund per return is a business-flow rule (ReturnService); short refund remainder stays a customer credit |
| ERP-027 | **Fixed** | `access.modules.erp_*`/`erp_central` + actions post/reverse/close/reopen/distribute/export/reports/audit in en (`resources/lang-erp/en/access.php`) and ar (`lang/ar/access.php`) |
| ERP-028 | **Fixed** | Hub + sidebar open for any ERP permission (gate `erp.access`); new `erp.owners.view` / `erp.assets.view` (migration `2026_12_18_100001` re-syncs permissions) - pages read-only without `.manage` |
| ERP-029 | **Fixed** | Tenant ERP audit trail page (`/admin/erp/audit`, hub card, `erp.audit.view`); `erp.expenses.manage` required to map expense categories to ledger accounts |
| ERP-030 | **Fixed** | + pass 2: main tenant dashboard shows month-to-date ERP KPIs (net revenue, net profit, cash & bank, receivables - same `DashboardService::kpis`) with a link, for `erp.dashboard.view` |
| ERP-031 | **Fixed** | Withdrawals need cash cover; year-end close moves drawings to capital per owner |

---

## A. Accounting correctness per event

### Trace of the as-built journals (for reference)

| Event | Code path | Journal produced | Verdict |
|---|---|---|---|
| Order confirm (any of confirmed/processing/shipped/delivered) | `OrderStatusService::change` → `OrderStatusChanged` (after commit) → `PostOrderEvents::onStatusChanged` → `SalesPoster::confirmed` (key `order:{id}:confirm`) | Dr AR total / Cr Sales (revenueGoods), Shipping rev (shipping_net), VAT (tax+packaging_tax), Fee income (payment_fee_charged+cod_fee), Other rev (packaging net); Dr COGS / Cr Inventory (`orders.cogs`); Dr Shipping exp / Cr Shipping payable; Dr Gateway fees / Cr settlement acct; Dr Affiliate exp / Cr Affiliate payable; Dr Packaging / Cr Inventory | Totals tie to `total` for inclusive & exclusive (verified algebraically against CALCULATIONS §Order totals / §Packaging). Discount, free-shipping code (shipping_net=0, cost still expensed), flash sale and bundles (expanded lines) are correct because they only change stored order columns. Timing and cost basis wrong → ERP-002, ERP-008, ERP-011 |
| Wallet proof approved / online webhook paid | `OrderPaymentService::approveProof` / `settleGateway` → `OrderPaymentUpdated(proof_approved|paid)` → `PaymentPoster::sync` (key `order:{id}:pay:{cumulative}`) | Dr Wallet / Gateway / Cr AR (customer credit before confirm) | Correct; webhook duplicates de-duplicated twice (payment_events + key) |
| COD prepaid shipping leg | `PaymentPoster::sync` lines 33-37 | Dr prepay-method acct (leg) + Dr Carrier receivable (rest) / Cr AR | Correct for receipts; refunds wrong → ERP-010 |
| COD delivered | `OrderStatusService::deliver` sets paid_amount=total → sync | Dr Carrier COD receivable / Cr AR (Dr Cash if no carrier) | Correct |
| Carrier settlement | `CarrierSettlementService::settle` → `CarrierSettled` → `PaymentPoster::carrierSettled` | Dr Cash (cod−fee) + Dr Shipping payable (fee) / Cr Carrier receivable | OK; see ERP-024 |
| Cancel before confirm | `cancelled()` reverses nothing; `cancelRefund` Dr AR / Cr paying acct | Correct |
| Cancel after confirm | reverse `confirm` + `commission:*` family; cancelRefund | Correct except ERP-023 (gateway fee) and the COD account choice (ERP-001) |
| Cancel after ship | impossible (`OrderStatus::next`: shipped→delivered only) | Gap ERP-011 (no RTO / failed delivery) |
| Return approved | `ReturnService::approve` → `ReturnPoster::approved` (`return:{id}:goods`, `:ship`) | Dr Sales returns (net) + Dr VAT / Cr AR; Dr Inventory or Inventory loss / Cr COGS; Dr Shipping exp / Cr Shipping payable; commission delta | Contra-revenue treatment, cumulative VAT and damaged treatment are correct |
| Refund | `ReturnPoster::refunded` (`return:{id}:refund`) | Dr AR (+Dr Shipping rev for shipping part) / Cr `settlementRole(payingMethod, carrier)` | **Wrong account for COD** → ERP-001/010 |
| Affiliate commission | at confirm (accrual), deltas on returns | OK as accrual; payout not posted (ERP-003); paid-then-returned (ERP-004) |
| Purchase receive | `PurchaseService::receive` → `PurchaseReceived` | Dr Inventory (items) + Dr Purchase expenses (landed) / Cr AP(supplier) | Consistent with stock ledger; IAS 2 issue ERP-019. No partial receive exists in the business module (only draft→received); cancel only for drafts → nothing to reverse (correct) |
| Supplier payment | ERP party page → `PartyService::pay` | Dr AP (+Dr Supplier advances) / Cr cash-like | Correct (FIFO, no per-PO allocation). `PurchasePoster::payment()` is dead code |
| Stock adjustment | `StockAdjustmentService::create` → `StockPoster::adjusted` | Dr/Cr Inventory vs Inventory loss at movement unit_cost | Correct |
| Transfer | sent: Dr Inventory in transit / Cr Inventory; received: reverse with target branch | Correct (zero consolidated effect) |
| Expense create/edit/delete | `ExpenseService` → `ExpensePoster` (hash keys, reverse-and-repost) | Dr category acct (fallback other_expense) / Cr paid_from or AP | Engine correct; UI never supplies inputs → ERP-013 |
| Fixed assets / depreciation / disposal | `AssetService` | per ERP_PLAN §Phase 5 | Spot-checked, no defect found |
| Owners | `OwnerService::transact` | contribution / withdrawal OK; **distribution** ERP-005; branch allocation ERP-006 |
| Profit distribution | `ProfitDistributionService` | Dr IS / Cr RE transfer, Dr RE / Cr Distributions payable, pay Dr payable / Cr cash with balance check | Sound (profit ≠ cash honoured) — but bypassable (ERP-005) |
| Year-end close | `PeriodService::closeYear` | P&L → Income summary → Retained earnings, per branch | Correct; drawings not closed (ERP-031) |
| FX | none — all journals in tenant base currency; `original_amount/currency` never filled by any poster | Gap ERP-021 |

---

### ERP-001 — Critical — accounting — COD refunds booked against the carrier
**Evidence:** `app/Services/Erp/Posting/ReturnPoster.php:74` and `PaymentPoster.php:58` use `self::settlementRole($o->payingMethod(), (bool) $o->shipping_company_id)`; for a COD order with a carrier this is `carrier_receivable` (`Poster.php:70-78`). Business side: `RefundService::onlinePortion` returns 0 for COD, i.e. *the merchant* returns the cash manually (CALCULATIONS: "Wallet/COD refunds are manual money moves").
Journal produced on refunding 100 for a delivered & settled COD order: `Dr AR 100 / Cr Carrier COD receivable 100` → carrier receivable −100, cash unchanged.
**Probe:** `QAPROBE ERP-001` → *Failed asserting that -100.0 is identical to 0.0*. The existing test `ErpPostingTest › posts the refund cash leg…` asserts `carrier_receivable = total − 100`, i.e. it codifies the bug.
**Impact:** Every COD return (the dominant payment method) overstates cash and understates/negatives what carriers owe; the carrier receivable reconciliation (vs `cod_amount` delivered-unsettled) breaks; distributable cash is overstated.
**Fix:** Refund credit account must be the account the refund is paid FROM, not the account the sale was collected into. Add a `refund_from` (cash/bank/wallet/gateway) choice on the refund form (default: gateway only for the provider-refunded portion, wallet for wallet orders, otherwise cash) and post `Dr AR / Cr <refund_from>` split by portion (`RefundService::onlinePortion` → gateway, remainder → chosen account). Same for `PaymentPoster::cancelRefund`. Only a refund executed *by the carrier before settlement* may credit carrier receivable — make that an explicit option. Fix the existing test expectation.

### ERP-002 — High — accounting/data-integrity — COGS timing & cost basis vs stock ledger
**Evidence:** `SalesPoster.php:55` posts `Dr COGS / Cr Inventory` with `orders.cogs` (tenant-wide listing cost snapshot at order time, CALCULATIONS §Definitions) at confirmation; `OrderStatusService::ship` → `StockLedger::ship` → `record(SALE_OUT)` values the outflow at the **branch** average at **shipping** time (`StockLedger.php:47`). Nothing trues up the difference. CALCULATIONS §ERP inventory reconciliation admits the timing gap ("committed") but not the valuation gap.
**Probe:** `QAPROBE ERP-002` → order cogs 120 vs stock sale_out value 180 (a purchase at a new cost between confirm and ship).
**Impact:** Ledger inventory ≠ stock valuation forever (grows with every cost change, every multi-branch tenant); COGS/gross margin misstated; the "inventory" reconciliation line will be red in production for normal operations.
**Fix (treatment):** Recognise COGS when control transfers (shipment for own-fleet/carrier delivery, or delivery), at the value the stock ledger actually removed. Implement: dispatch an `OrderShipped`/stock event from `OrderStatusService::ship` (or a `StockMoved` event from `StockLedger::record`) and post `Dr COGS / Cr Inventory` = Σ sale_out movement value; remove COGS from the confirm entry (or post confirm-time COGS to an "inventory committed" clearing and true-up at shipping). Returns should then reverse at the original sale_out value (already matches `return_in` at `unit_cost` only if `unit_cost` = sale_out cost — align `order_items.unit_cost` to the shipped cost at ship time).

### ERP-003 — High — integration — Affiliate payouts / ambassador rewards not posted
**Evidence:** `AffiliateService::markPaid` (app/Services/Affiliates/AffiliateService.php:107-117) fires no event; no ERP listener; `AffiliatePayoutController.php:41` also settles ambassador rewards with no ledger effect. Only accrual exists (`SalesPoster` affiliate lines).
**Probe:** `QAPROBE ERP-003` → affiliate_payable still 11.50 after payout paid.
**Impact:** Affiliate payable grows forever, cash overstated by all payouts, distributable cash overstated → owners can be paid money that was already given to affiliates.
**Fix:** Fire `AffiliatePayoutPaid($payout)` in `markPaid` (after commit) and post `Dr Affiliate payable / Cr <paid_from>` (add a paid-from selector to the payout form), key `affpayout:{id}`; ambassador rewards: `Dr Marketing/affiliate expense / Cr cash` (or accrue on approval).

### ERP-004 — Medium — accounting — paid commission reversed on full return
**Evidence:** `ReturnService.php:118` sets `orders.affiliate_commission = 0` on full return regardless of the commission row status; `SalesPoster::syncCommission` posts the delta `Dr Affiliate payable / Cr Affiliate expense`. `AffiliateCommissionService::syncOrder` explicitly leaves PAID rows untouched.
**Probe:** `QAPROBE ERP-004` → affiliate_expense 0 although the 5.00 commission is `paid`.
**Impact:** Expense understated, affiliate payable becomes a debit (a phantom receivable from the affiliate that no process collects).
**Fix:** In `syncCommission` take the target from the commission row (`paid` rows keep their amount) or, if a clawback policy exists, post the debit to an explicit "Affiliate clawback receivable" with a collection flow.

### ERP-005 — High — control — owner distribution bypasses controlled workflow
**Evidence:** `OwnerService.php:88` `'distribution' => Dr Retained earnings / Cr cash-like`; exposed by `OwnerController::transact` (`erp.owners.manage`). No distributable-profit, cash-buffer, approval, or period-closure check (all of which `ProfitDistributionService` enforces).
**Probe:** `QAPROBE ERP-005` → 5000 distributed with zero profit and zero cash accepted (cash goes negative).
**Impact:** Defeats the owner's core requirement "controlled profit distribution (profit ≠ cash)"; illegal dividend risk, negative cash.
**Fix:** Remove `distribution` from `OwnerTransaction::TYPES` for new transactions (keep for history) — distributions only via `ProfitDistributionService::pay`; withdrawals stay as drawings (with a cash-availability check, see ERP-031).

### ERP-006 — High — accounting — branch capital allocation fabricates cash
**Evidence:** `OwnerService::allocateToBranch` lines 103-117: `Dr Cash (branch) / Cr Branch capital (branch)`; no corresponding credit to HQ cash or owner capital.
**Probe:** `QAPROBE ERP-006` → consolidated cash +5000 with no receipt.
**Impact:** Balance sheet and cash overstated; distributable cash overstated.
**Fix:** An internal allocation is a transfer: `Dr Cash(branch B) / Cr Cash(HQ)` (or via `branch_transfer` clearing, as `BranchTransferService` does); the equity side, if branch capital reporting is wanted, is a memo reclass `Dr Owner capital (HQ) / Cr Branch capital (B)`. Fresh owner money for a branch must go through `transact('contribution', branch=B)`.

### ERP-007 — High — data-integrity — manual reversal of auto entries
**Evidence:** `JournalController::reverse` → `JournalService::reverse` accepts any posted entry incl. `type=auto`. Afterwards `Poster::post` (Poster.php:41) and `JournalService::post` return the existing (reversed) entry for the same key; `BackfillService::key()` (line 178-181) counts a reversed key as "posted", so *post missing* never repairs it.
**Probe:** `QAPROBE ERP-007` → confirmed order with receivable 0 after re-post + post-missing.
**Impact:** A single click silently removes revenue/AR of a live order with no recovery path; ledger diverges from operations permanently.
**Fix:** Forbid manual reversal of `type=auto` entries whose source document is still in the state that produced them (reverse only via the business action, e.g. cancel); or make posters treat a *reversed* key as "re-postable" with a suffixed key (`…:r{n}` like `ExpensePoster`), and make `BackfillService::key()` check `status='posted'`.

### ERP-008 — Medium — accounting — packaging credited to inventory
**Evidence:** `SalesPoster.php:59` `Dr Packaging / Cr Inventory packaging_cost`; packaging options are not stock items (CALCULATIONS §Special packaging — no stock movement).
**Probe:** `QAPROBE ERP-008` → inventory −64 vs −60 expected.
**Fix:** Credit a `packaging_supplies` asset (if bought into stock via expense/PO) or accrue to `other_liabilities`/AP; simplest correct option: book packaging purchases as expense and do not post `packaging_cost` again at sale (avoid double expense), or post `Dr Packaging / Cr Packaging supplies`.

### ERP-009 — Medium — accounting — supplier debit note hits inventory
**Evidence:** `PartyService.php:173` default counter for a supplier debit note = `inventory`; no stock movement, no PO link.
**Probe:** `QAPROBE ERP-009`.
**Fix:** Default to `purchase_expenses`/"Purchase returns & allowances" (new contra account); goods returned to a supplier must go through a stock document (purchase return) that posts both stock and GL.

### ERP-010 — High — accounting — COD + prepaid shipping refunds credited to gateway/wallet
**Evidence:** `ReturnPoster.php:74` uses `payingMethod()`, which is the **prepay** method for COD-upfront orders (`Order::payingMethod`), so the whole goods refund is `Cr Gateway/Wallet`; but `RefundService::onlinePortion` sends at most `prepaid_shipping` (and only when shipping is refunded) to the provider; the goods part (collected by the carrier) is refunded manually.
**Impact:** Gateway balance understated (eventually negative), cash overstated. **Fix:** same split as ERP-001 (provider portion → gateway, rest → chosen account).

### ERP-011 — High — accounting policy — revenue at confirmation
**Evidence:** `SalesPoster` header + `ErpSettings::DEFAULTS['revenue_policy']`; confirm happens before picking/shipping (`OrderStatus::next`). Also `OrderStatus::SHIPPED => [DELIVERED]` only — a failed-delivery/returned-to-sender COD parcel cannot be cancelled, so its revenue, VAT and COD receivable stay forever.
**Impact:** IFRS 15 / most GAAP recognise revenue when control passes (shipment/delivery); confirmed-but-unshipped orders inflate revenue, VAT payable and AR at period end (cut-off error), and VAT is declared too early. The choice was made to mirror the legacy Net Profit report, but an ERP should not inherit a management-report rule.
**Fix:** Make the recognition point a setting (`revenue_on = shipped|delivered|confirmed`, default `shipped`); before that point book nothing (or a memo/backlog report). Add an RTO flow `shipped → returned_to_sender` that restocks and reverses. Update CALCULATIONS.md.

### ERP-012 — Medium — clearing accounts never settled
**Evidence:** grep: `shipping_payable` is debited only in `PaymentPoster::carrierSettled` (COD orders); prepaid (online/wallet) orders and return shipping (`ReturnPoster.php:54`) credit it with no settlement. `vat_payable` has no remittance flow; `gateway` has no payout-to-bank flow (no bank reconciliation).
**Impact:** Liabilities balloon; `ProfitService::compute` subtracts `shipping_payable`+`vat_payable` (LIABILITY_ROLES) so distributable cash collapses; gateway balance never matches the provider statement.
**Fix:** Add "Pay carrier" (Dr Shipping payable / Cr cash|bank, per carrier party), "VAT payment" (Dr VAT payable / Cr bank), "Gateway payout" (Dr Bank + Dr Gateway fees / Cr Gateway) screens under a Cash & Bank card, with statement import/reconciliation.

### ERP-013 — High — gap/ux — expense ↔ ledger mapping not reachable
**Evidence:** `resources/views/tenant/expenses/form.blade.php` has no `is_paid`/`paid_from` inputs and no expense-category form/ERP view sets `expense_categories.erp_account_id` (grep: only migration/model). `ExpenseRequest` accepts the fields (API-only). Result in `ExpensePoster.php:32-33`: always `Cr cash`, category unmapped → `other_expense`.
**Impact:** P&L shows all opex in "Other business expenses" (salaries, rent, marketing accounts stay at 0); cash goes negative for bank/wallet-paid expenses; unpaid bills cannot be recorded; expense reports by account meaningless. When `is_paid=false` the credit goes to AP **without a supplier party** and there is no "pay expense" action.
**Fix:** Add "Paid?" + "Paid from" (cash/bank/wallet/…) + optional supplier to the expense form; add "ERP account" select to the expense-category form (seed sensible defaults by name); unpaid expenses must carry `party_type=supplier` and be payable via the supplier page.

### ERP-014 — Medium — POS not a counter sale
**Evidence:** `PosController::store` → `OrderPlacementService::place` creates a normal `pending` COD/online order; no cash tender, no immediate confirm/deliver; `pos_cash` role is only selectable manually (`PartyService::CASH_ROLES`).
**Impact:** Walk-in sales need 4 manual status changes; the cash drawer is never debited; COD-to-carrier logic applies to counter sales without carrier (`Dr cash` only if no shipping company).
**Fix:** POS "paid at counter" mode: place → confirm → deliver atomically with `paid_amount=total`, settlement role `pos_cash` (per register/branch), receipt print; daily register close (Dr bank / Cr pos_cash).

### ERP-015 — Medium — party receipts vs orders.paid_amount
**Evidence:** `PartyService::receive` credits AR but never updates any order; `OrderStatusService::deliver` later sets `paid_amount = total` and `PaymentPoster::sync` posts the full amount again (`Dr carrier receivable / Cr AR`).
**Impact:** Double-counted receipts, negative AR, carrier receivable overstated.
**Fix:** Either allocate receipts to orders (update `paid_amount` and use the order's pay key), or block party receipts for customers that have open order-driven balances and offer "record payment" on the order page instead.

### ERP-016 — Medium — silent posting failures
**Evidence:** `PostingGuard::run` catches every `Throwable`, logs `posting.failed`; e.g. an expense dated into a closed period is saved but not posted; nothing tells the user.
**Fix:** For user-initiated documents (expenses, POs, adjustments) validate the period up-front and refuse the business save (FormRequest rule "period open for date"); keep the guard only for asynchronous/webhook events; notify `erp.settings.manage` holders on `posting.failed`.

### ERP-017 — Medium — manual journal controls
**Evidence:** `JournalController::store` validates `type in manual,adjusting,opening`, any `account_id`, no `party_type/party_id`, no `attachment`, `post_now` by the same user. `JournalService::post` does not stop control/system accounts.
**Fix:** Mark control accounts (`receivable`, `payable`, `inventory`, `vat_payable`, `carrier_receivable`, `affiliate_payable`, advances) `allow_manual=false` or require a party; enforce creator ≠ poster when a setting is on; add attachments (column exists).

### ERP-018 — Medium — party actions skip the post permission
**Evidence:** `PartyController::settle|applyAdvance|note|opening` → `allow('erp.journals.create')` only, but `PartyService` posts immediately (`JournalService::post`).
**Fix:** Require `erp.journals.post` (or a dedicated `erp.parties.manage` + approval for notes/openings above a threshold). Openings should only be allowed on/before the opening date.

### ERP-019 — Medium — landed costs expensed
**Evidence:** `PurchasePoster.php:24` `Dr purchase_expenses (shipping_cost+other_cost)`; CALCULATIONS §Stock: not allocated into unit cost.
**Fix:** IAS 2: allocate freight/duties to items (by value/qty) in `PurchaseService::receive` → `purchase_in` unit cost, post all to inventory. Policy change → update CALCULATIONS.md.

### ERP-020 — Medium — guest orders without party
**Evidence:** `OrderPlacementService.php:62` `customer_id => $d->customerId` (nullable); all AR lines get `party_id NULL` (ERP_PLAN acknowledges "without a party").
**Fix:** Auto-create/attach a customer by phone at placement, or post guest orders to a "Walk-in / guest customers" party so the sub-ledger ties to the control account.

### ERP-021 — Medium — requirements gaps (BA view)
| Requirement | Status |
|---|---|
| 22 financial reports | **18** in `ReportCatalog::META` (trial balance, GL, P&L, BS, cash flow, journal entries, receivables, payables, sales, purchases, sales returns, expenses, COGS, inventory valuation, branch profitability, owner equity, tax, reconciliation) + pages (party ageing, owner statement, distribution report, asset register). Missing as proper reports: cash/bank book, VAT return incl. input VAT, fixed-asset depreciation schedule export, statement of changes in equity, budget vs actual, customer/supplier statement export |
| Excel export | missing (CSV + PDF only — `ReportController.php:110`) |
| Expense / purchase approval workflows, recurring expenses | missing (ERP_PLAN §"Implement (essential)" listed them) |
| Bank / gateway reconciliation, Cash & Bank hub card | missing (ERP-012) |
| Country-configurable tax | single `tenants.settings.tax_rate` on goods+packaging; no tax codes, no input VAT on purchases, shipping/fees never taxed; ERP `tax_rate` is an income-tax *estimate* only. No Egypt hardcoding found (good) |
| Multi-currency | journals always in base currency; `original_amount/currency/exchange_rate` never populated; no FX revaluation; global exchange-rate UI missing (tenant-only rates) |
| Opening balance on account creation | missing (`AccountController::store` has no amount) |
| Journal attachments | DB column only, no upload |
| Audit history UI (tenant) | missing (`erp.audit.view` unused); central audit page exists |
| Credit/debit notes | implemented (party page) – see ERP-009/018 |
| Supplier payment UI | implemented (party page, FIFO, no PO allocation) |

### ERP-022 … ERP-031 (Low)
- **ERP-022** `sales_discounts` never posted; revenue booked net (`Order::revenueGoods`). Post gross `subtotal` to Sales and `discount` to 4160 for presentation (net unchanged).
- **ERP-023** `SalesPoster` reverses `payment_fee` on cancel though gateways keep it → post fee at payment time (key `pay`), not in the confirm family; COD-method `payment_fee` is credited to `carrier_receivable` (`Poster::settlementRole(COD)`) while `carrierSettled` clears only `cod_amount` → residual.
- **ERP-024** `PaymentPoster::carrierSettled` always `Dr cash`; offer bank; when `shipping_cost > cod_amount` the remainder stays in shipping payable.
- **ERP-025** Ledger by event date vs legacy report by `created_at` — reconciliation flags normal month-end orders; reconcile on the same basis or document the timing column.
- **ERP-026** `ReturnService::refund` moves the return to `refunded` after the first (possibly partial) refund; the unrefunded remainder sits as an unlabeled AR credit. Allow multiple refunds (key `return:{id}:refund:{cumulative}`) or post the remainder to `customer_advances` (store credit).
- **ERP-027** `lang/{en,ar}/access.php` has no `modules.erp_*` / `erp_central` nor actions `post, reverse, close, reopen, distribute, export, reports, audit` → `Str::headline` fallback ("Erp.accounts") in both languages.
- **ERP-028** `components/layouts/admin.blade.php:56` and `HubController::index` gate on `erp.dashboard.view`; give the hub its own `erp.view`/any-erp check; add view-only perms for owners/assets.
- **ERP-029** `erp.expenses.manage`, `erp.audit.view` seeded, never checked.
- **ERP-030** no ERP notifications; tenant main dashboard has no ERP KPIs/link (only the Finance sidebar group).
- **ERP-031** `closeYear` closes P&L only; `owner_drawings` should be closed to capital/RE; withdrawals need a cash check like `ProfitDistributionService::pay`.

## B. Hub-of-cards UX
Tenant hub (`HubController::CARDS`, 19 cards) and central hub (9 cards): every card has a route and is filtered by permission — no dead cards, no card visible without permission (good). Cards reuse the admin Tailwind card pattern and `x-layouts.admin` (RTL via layout). Issues: no Cash & Bank card (ERP-012/021); expenses card leads to the legacy expenses page that lacks the ERP fields (ERP-013); hub reachable only with `erp.dashboard.view` (ERP-028).

## C. Panel connections
- Tenant sidebar: `tenant.erp.hub` (perm `erp.dashboard.view`) + Finance group; central sidebar: `central.erp.hub` (perm `erp_central.view`). OK.
- `PermissionSeeder` syncs all `erp.*` to `tenant-owner` and `erp_central.*` to `super-admin` (migrations `2026_12_10_100002`, `2026_12_14_100002`). OK. Custom roles see the perms but unlabeled (ERP-027).
- Central consolidated views group by `tenants.currency_code` (`CentralErpService::groups`) — no cross-currency sums found.
- Notifications / dashboard links: none (ERP-030).

## Probe tests
`tests/Feature/QaProbeErpAccountingTest.php` — run alone: `php artisan test tests/Feature/QaProbeErpAccountingTest.php` → 9 failed (expected; each failure message states the defect). Delete after the fixes land, or convert to regression tests with the corrected expectations.
