# ERP Module – QA/QC functional test report (branch `ERP-MODULE`)

Tester role: senior QA/QC, black-box + grey-box, **through HTTP only** (forms/admin routes as a user would use them), both panels, both locales (en / ar-RTL).
Scope complements the accounting audit in `docs/qa/ERP_AUDIT_FINDINGS.md` (ERP-001…ERP-031). Those findings are **not re-reported**; where a test touches one, it is referenced by ID.
No application code was changed. Run date 2026-11-04, MySQL test DB `3alraf_test`, each file run alone.

## Test files

| File | Tests | Pass | Fail (= QA-BUG) | Runtime |
|---|---|---|---|---|
| `tests/Feature/QaE2eErpTenantTest.php` | 27 | 17 | 10 | ~85 s |
| `tests/Feature/QaE2eErpI18nPermsTest.php` | 15 | 10 | 5 | ~70 s |
| `tests/Feature/QaE2eErpCentralTest.php` | 8 | 7 | 1 | ~66 s |
| **Total** | **50** | **34** | **16** (15 bugs; QA-010 has 2 tests) | |

```
php artisan test tests/Feature/QaE2eErpTenantTest.php
php artisan test tests/Feature/QaE2eErpI18nPermsTest.php
php artisan test tests/Feature/QaE2eErpCentralTest.php
```
Every failing test is named `QA-BUG-xxx …` and asserts the **correct** behaviour; it turns green when the bug is fixed. Every non-`QA-BUG` test passes today (regression coverage).

## Bug summary

| ID | Sev | Area | Title | Failing test |
|---|---|---|---|---|
| QA-005 | **High** | settings / profits | Saving settings with `reserve_percent=1e1` is accepted and bricks the Profit-distribution page with a 500 on every later visit | `QA-BUG-005 …` |
| QA-015 | **High** | periods | The running fiscal year (and future periods) can be closed before they end: year-end close in November locks December and every later posting | `QA-BUG-015 …` |
| QA-004 | Medium | validation | Amounts in scientific notation (`1e3`) crash 6 forms with 500 (bcmath `ValueError`) | `QA-BUG-004 …` |
| QA-013 | Medium | robustness | Double-submitting the manual journal form with "post now" creates two posted entries | `QA-BUG-013 …` |
| QA-012 | Medium | data-integrity / UX | Re-submitting a receipt/payment with the same form ref but a different amount is silently ignored (success flash, nothing posted) | `QA-BUG-012 …` |
| QA-002 | Medium | chart of accounts | A postable (non-group) account, e.g. Cash, is accepted as a parent account | `QA-BUG-002 …` |
| QA-006 | Medium | validation | Out-of-range journal amounts → 500 `QueryException` (no max rule) | `QA-BUG-006 …` |
| QA-007 | Medium | validation / periods | Manual journals dated 1900-01-01 / 9999-12-31 are posted, silently creating fiscal years the Periods page cannot display (year limited to 2000–2100) | `QA-BUG-007 …` |
| QA-011 | Medium | i18n | Arabic admin: ERP validation messages for `date`, `between`, `different` are English and field names are raw (`حقل lines مطلوب`) | `QA-BUG-011 …` |
| QA-001 | Low | chart of accounts | Account create without the `name_ar` key → 500 `Undefined array key "name_ar"` | `QA-BUG-001 …` |
| QA-003 | Low | validation / UX | Unknown parent account, non-expense tax account, unknown branch in a form → full 404 page instead of a field error | `QA-BUG-003 …` |
| QA-008 | Low | robustness | `GET /admin/erp/journals?q[]=x` → 500 `Array to string conversion` | `QA-BUG-008 …` |
| QA-009 | Low | robustness | Over-long numeric id in any ERP URL (`/journals/99999999999999999999`) → 500 `TypeError` | `QA-BUG-009 …` |
| QA-010 | Low | permissions / UX | Pages link to journal entries the viewer is not allowed to open (403) – GL report, dashboard "recent entries" | `QA-BUG-010 …`, `QA-BUG-010b …` |
| QA-014 | Low | central UX | Central ERP hub for an `erp_central.view`-only admin is an empty page with zero cards and no explanation | `QA-BUG-014 …` |

**Counts:** Critical 0 · High 2 · Medium 7 · Low 6 (total 15).

Already-known audit findings hit during testing (not re-reported): ERP-007 (UI offers *Reverse* on auto entries), ERP-016 (business postings into a closed period fail silently – amplifies QA-015), ERP-018 (party actions only need `erp.journals.create` – confirmed in the permission tests), ERP-027 (role editor shows ERP permissions unlabeled), ERP-028 (hub/sidebar need `erp.dashboard.view`: reports-only user gets 403 on the hub – asserted as current behaviour in `PERMS reports-only accountant …`), ERP-006 (branch capital allocation – route `owners.allocate` therefore not journey-tested).

## Status (pass 2 fixes, 2026-11-04)

Shared input rules: `App\Support\ErpRules` (money = plain decimal `^\d{1,14}(\.\d{1,2})?$` via `App\Rules\DecimalAmount`, `gt:0`/`min:0`, max 99,999,999,999,999.99 (fits decimal(18,4)); percent `0..100`, 4 decimals; rates 8 decimals; dates `2000-01-01 .. today+1 year`) applied to every ERP form (journals, parties, owners, assets, profits, cash & bank, branch transfer, settings, rates) and to `/admin/expenses`.

| ID | Status | Fix |
|---|---|---|
| QA-005 | **Fixed** | Settings validated with the strict percent/money rules and stored normalised; `ErpSettings::decimal()` + `Money::str()` turn any legacy stored `1e1`/garbage into a bcmath-safe value, so the profits page cannot be bricked |
| QA-015 | **Fixed** | `PeriodService::close` refuses periods that have not started; `closeYear` only after the fiscal year's end date (current month close stays allowed = soft close) |
| QA-004 / 006 / 007 | **Fixed** | ErpRules on every ERP amount/date; journal total capped; field errors instead of 500 |
| QA-013 | **Fixed** | Journal form carries a one-time hidden `ref` → `idempotency_key manual:{ref}` (drafts too); asset register form token (`App\Support\FormToken`) |
| QA-012 | **Fixed** | `JournalService::post` `strict_replay` + `assertSameReplay`: same token with a different total → `erp_acc.errors.duplicate_ref` (party forms, branch transfer, journals, cash & bank, owner transactions) |
| QA-002 | **Fixed** | Parent must be a group account (`erp_acc.errors.parent_group`) |
| QA-011 | **Fixed** | `lang/ar/validation.php` complete (all rules) + Arabic attributes; English ERP attributes in `resources/lang-erp/en/validation.php` (loader merges every path per group - verified in `FileLoader::loadPaths`) |
| QA-001 | **Fixed** | `name_ar` optional key |
| QA-003 | **Fixed** | Tenant-scoped `exists` rules (parent, tax account with type/group filter, transfer branches, note counter account). `ErpReportsTest` foreign-branch transfer now expects a field error (was 404) - intended |
| QA-008 | **Fixed** | Journal list filters sanitised (arrays/garbage ignored); view echoes sanitised values |
| QA-009 | **Fixed** | ERP route ids constrained to `[0-9]{1,18}` (tenant + central) → 404 |
| QA-010 | **Fixed** | `tenant/erp/_jlink` partial: entry number is a link only with `erp.journals.view` (GL, dashboard, party/owner statements, assets, profits); branches page report link behind `erp.reports.view` |
| QA-014 | **Fixed** | Central (and tenant) hub empty-state message |

Intended test-expectation updates: `QaE2eErpTenantTest` migration journey and E2E flow ship the order before expecting a posting (ERP-011 default = recognise at shipment); `QaE2eErpI18nPermsTest` view-only card list includes the pass-1 Cash & Bank card (`erp.accounts.view`).

---

## Bug details

### QA-005 — High — `reserve_percent=1e1` bricks the Profit distribution page
**Steps:** `PUT /admin/erp/settings` `{reserve_percent: "1e1", distribution_min_cash_buffer: "1e2", tax_rate: 0}` → 302 "saved". Then `GET /admin/erp/profits`.
**Expected:** validation error on the save (or the value normalised to `10`), profits page 200.
**Actual:** save accepted (`numeric` accepts exponent notation, stored as string `1e1`); every later `GET /admin/erp/profits` → **500** `ValueError: bcmul(): Argument #2 ($num2) is not well-formed` at `app/Support/Money.php:42` ← `app/Services/Erp/Equity/ProfitService.php:85-86`. The page stays broken until someone edits the settings again (distribution create/approve/post also use `compute()`).
**Suspected:** `app/Http/Controllers/Tenant/Erp/SettingsController.php:25-32` stores the raw string.
**Fix:** use `decimal:0,4` (or `regex:/^\d+(\.\d+)?$/`) instead of `numeric` for every money/percent field, and normalise before storing (`number_format`/`bcadd($v,'0',4)`); defensively normalise in `ErpSettings::get` for numeric keys.

### QA-015 — High — premature fiscal-year / future period close
**Steps (2026-11-04):** close FY 2025 (`POST /admin/erp/periods/years/{fy2025}/close` – ok), then `POST /admin/erp/periods/years/{fy2026}/close`; also `POST /admin/erp/periods/{2027-01}/close`.
**Expected:** refused – a fiscal year can be closed only after its end date, and a period only once it has started/ended (at least: not before `starts_on`).
**Actual:** 302 success; FY 2026 `closed`, 0 open periods; year-end closing entry moves a partial year's P&L to retained earnings; `qaJournal` for today → "الفترة المحاسبية 2026-11 مقفلة"; every automatic order/expense posting for the rest of the year now fails silently (ERP-016). Future period 2027-01 closes too.
**Suspected:** `app/Services/Erp/Accounting/PeriodService.php:44` (`close`) and `:91` (`closeYear`) only check ordering, not dates.
**Fix:** in `closeYear` require `now() > $fy->ends_on`; in `close` require `now() >= $period->ends_on` (or a soft-close setting) and refuse periods with `starts_on > today`.

### QA-004 — Medium — scientific-notation amounts → 500
**Steps / payloads (each → 500 `ValueError: bccomp(): Argument #1 ($num1) is not well-formed`):**
- `POST /admin/erp/journals` lines `debit: "1e3"`, `credit: "1e3"` (`JournalService.php:162` `bccomp` in `persist`)
- `POST /admin/erp/parties/customer/{id}/settle` `amount: "1e2"`
- `POST /admin/erp/parties/customer/{id}/note` `amount: "1e1"`
- `POST /admin/erp/owners` `ownership_percent: "1e1"`
- `POST /admin/erp/owners/{id}/transactions` `amount: "1e2"`
- `POST /admin/erp/assets` `cost: "1e3"`
**Expected:** 302 back with a field error. **Actual:** 500.
**Suspected:** every ERP controller uses `numeric` (accepts `1e3`, `0x`-free but exponent forms) and passes the raw string to bcmath (`JournalController.php:37`, `PartyController`, `OwnerController::rules`, `AssetController::store`).
**Fix:** a shared `Money` rule (`decimal:0,4` + `max`) for all ERP amount inputs, or normalise with `number_format((float)…)` before bcmath. (`BranchController::transfer` already normalises and does not crash.)

### QA-013 — Medium — manual journal double submit duplicates postings
**Steps:** `GET /admin/erp/journals/create` (no idempotency token in the form) then `POST /admin/erp/journals` twice with the same payload incl. `post_now=1`.
**Expected:** one posted entry (form token / idempotency key like the party, owner and branch-transfer forms already use). **Actual:** 2 posted entries (`JE-…1`, `JE-…2`), balances moved twice.
**Suspected:** `resources/views/tenant/erp/journal-form.blade.php` (no hidden `ref`), `JournalController::store` (no `idempotency_key`).
**Fix:** add `<input type="hidden" name="ref" value="{{ Str::uuid() }}">` and pass `idempotency_key => 'manual:'.$ref` (for drafts too, return the existing entry).

### QA-012 — Medium — same ref, different amount silently ignored
**Steps:** `POST /admin/erp/parties/customer/{id}/settle` `{amount: 40, account: cash, ref: r-1}` then the same with `amount: 90` (browser *Back*, change amount, submit).
**Expected:** an error ("this form was already submitted – reload the page") because the content differs. **Actual:** 302 with "posted" flash, nothing posted (the existing 40 entry is returned by `JournalService::post`, `JournalService.php:37`). The user believes 90 was recorded.
**Fix:** when an idempotency key already exists, compare total/accounts; if different, throw `AccountingException(__('erp.errors.duplicate_ref'))`. Same for owner transactions and branch transfers.

### QA-002 — Medium — postable account accepted as parent
**Steps:** `POST /admin/erp/accounts` `{code: 1199, name_en, name_ar, type: asset, parent_id: <Cash 1110>}`.
**Expected:** validation error `parent_id` (parent must be a group/header account). **Actual:** created under Cash; Cash now has a child but is still postable and carries its own balance → hierarchical roll-ups/trial balance by group double count or hide balances; Cash can no longer be archived (`has_children`).
**Suspected:** `app/Http/Controllers/Tenant/Erp/AccountController.php:29-31` checks only the type.
**Fix:** `if ($parent && ! $parent->is_group) → withErrors(['parent_id' => __('erp.errors.parent_group')])`.

### QA-006 — Medium — out-of-range amount → 500
**Steps:** `POST /admin/erp/journals` `post_now=1`, lines `debit/credit: 99999999999999999999`.
**Actual:** 500 `SQLSTATE[22003] Out of range value for column 'total'`. **Expected:** field error.
**Fix:** `max:99999999999999` on `lines.*.debit|credit` (column `decimal(18,4)`), and on every other ERP amount (party/owner/asset/profit overrides/retain_amount).

### QA-007 — Medium — absurd journal dates accepted
**Steps:** `POST /admin/erp/journals` with `entry_date: 1900-01-01` / `9999-12-31`, `post_now=1`.
**Actual:** both posted; `PeriodService` auto-creates FY 1900 / FY 9999, which `GET /admin/erp/periods?year=…` refuses to show (`between:2000,2100`) → they can never be closed/reviewed; the entries land in reports with huge date ranges.
**Fix:** `entry_date` → `after_or_equal:2000-01-01|before_or_equal:` (today + N days, configurable); same for party/owner/asset dates.

### QA-011 — Medium — Arabic validation messages partly English
**Steps:** admin locale ar (`GET /admin/locale/ar`); `POST /admin/erp/journals` `{entry_date: 2026-02-30, lines: []}`; `GET /admin/erp/periods?year=1999`; `POST /admin/erp/settings/rates` `{from=to=EGP, effective_date: x}`.
**Actual messages:** "The entry date field must be a valid date.", "حقل lines مطلوب.", "The year field must be between 2000 and 2100.", "The to currency field and from currency must be different.", "The effective date field must be a valid date."
**Cause:** `lang/ar/validation.php` has only 19 rules (no `date`, `between`, `different`, `alpha`, `decimal`, `exists`, `after`…), `attributes` is empty.
**Fix:** complete `lang/ar/validation.php` and add ERP attribute names (`entry_date` → "تاريخ القيد", `lines` → "سطور القيد", `reason` → "السبب", …).

### QA-001 — Low — `name_ar` missing → 500
`POST /admin/erp/accounts` `{code: 6950, name_en: "No Arabic", type: expense}` (no `name_ar` key; API/AJAX or a trimmed form) → 500 `ErrorException: Undefined array key "name_ar"` at `AccountController.php:33`. Fix: `$d['name_ar'] ?? null ?: $d['name_en']`.

### QA-003 — Low — unknown ids → 404 page instead of field error
- `POST /admin/erp/accounts` `parent_id: 999999` → 404 (`AccountController.php:29` `findOrFail`)
- `PUT /admin/erp/settings` `tax_account_id: <Cash>` (not an expense account) → 404 (`SettingsController.php:28`)
- `POST /admin/erp/branches/transfer` `to_branch: 999999` → 404 (`BranchTransferService.php:29`)
Expected 302 + error on the field (the user loses the whole form). Fix: tenant-scoped `exists` rules (`$this->tenantExists(...)` already exists) with extra `where` for type/is_group.

### QA-008 — Low — array query parameter → 500
`GET /admin/erp/journals?q[]=x` → 500 `Array to string conversion` at `JournalController.php:18`. Fix: `$r->validate(['q' => 'nullable|string|max:100', 'status' => 'nullable|in:draft,posted,reversed', 'from|to' => 'nullable|date', 'branch_id' => 'nullable|integer'])`.

### QA-009 — Low — id overflow → 500
`GET /admin/erp/journals/99999999999999999999` (also `/owners/…`, every `whereNumber` route with an `int $id` controller arg) → 500 `TypeError … must be of type int, string given`. Fix: route constraint `[0-9]{1,18}` (or a global `Route::pattern('id', '[0-9]{1,18}')`).

### QA-010 — Low — links to forbidden pages
User with only `erp.reports.view`: `GET /admin/erp/reports/general-ledger?account_id=<cash>` renders `<a href=".../erp/journals/{id}">` (`report.blade.php:25`) → click = 403. User with only `erp.dashboard.view`: "recent entries" links (`dashboard.blade.php:24`). Fix: wrap with `@can('erp.journals.view')`, otherwise print the number as text. (Also applies to `branches.blade.php:9` → reports for a `branches.view`-only user.)

### QA-014 — Low — central hub empty for view-only admin
Central admin with only `erp_central.view`: `GET http://3alraf.test/admin/erp` shows the header text and **no card and no message** (all cards need `erp_central.reports|audit`). Fix: `@if($cards->isEmpty())` empty-state text "You have no ERP reports permissions – ask a super admin", or make `erp_central.view` grant at least one card.

---

## What was verified and works (passing coverage)
- **Smoke, tenant:** every `tenant.erp.*` GET route (incl. all 18 reports, 18×CSV, 18×PDF exports, customer/supplier pages) → 200 for the owner with realistic data (orders via POS, deliver/confirm/cancel, return+refund, PO received, adjustment, expense, owners, asset, draft journal, distribution) in **en**; same in **ar** with `dir="rtl"`; and on a **brand-new tenant** (no CoA, no periods, no data – the CoA is seeded lazily, id routes 404). No raw translation keys (`erp.*`, `erp_rep.*`, `erp_ext.*`, `erp_dist.*`, `erp_central.*`) in any page. EN/AR ERP lang files have identical key sets (809 keys).
- **Exports:** CSV = `text/csv` attachment, non-empty, formula cells neutralised; PDF = `application/pdf` starting `%PDF` in en and ar (Arabic shaping does not crash).
- **Odd filters:** from>to (swapped), invalid dates (validation redirect), 1900–2999 ranges, non-numeric/foreign branch/account ids (404), unknown report (404), `page=-3`, `status[]=`, `year=abc` – no 500 except QA-008/009.
- **Journeys (HTTP forms):** accounts create/child/duplicate/wrong type/archive-with-activity/delete-unused/restore/system-account protection; journals draft→post (double post idempotent)→reverse (reason required, double reverse = one reversal), unbalanced/one-line/bad type = validation error, delete draft, posted not deletable; periods close (double click), reopen with/without reason, posting & receipts into a closed period refused with a message; customers opening (double submit), partial receipt (double submit), over-payment → advance, debit/credit notes, apply advance, statement, ageing, receivables report; suppliers credit purchase (via `/admin/purchases`), partial payment, advance-only, apply advance (double submit), payables; owners 100 % cap (create/update), contribution (double submit), withdrawal, statement; profit distribution create (open-period ack, future range refused, retain %) → overlap refused → second-approver rule (creator refused, other admin OK) → approve/post double clicks → pay (double submit = 1 payment journal) → cancel refused after payment; cancel of posted unpaid run reverses journals and frees the period; fixed asset register → `erp:depreciate` (catch-up, idempotent re-run) → UI depreciate idempotent → transfer branch → dispose (double submit); branch cash transfer (form ref, double submit = 1 entry, same-branch refused); settings save/limits/exchange rates add-delete; migration toggle auto-posting off → order not posted → detect → post missing (double submit) → reconcile.
- **Business flows from the existing admin UI** (`/admin/purchases`, `/admin/pos/orders`, `/admin/orders/{id}/status`, `/admin/orders/{id}/returns`, `/admin/returns/{id}/approve|refund`, `/admin/stock-adjustments`, `/admin/stock-transfers` send/receive, `/admin/expenses` create/update/delete) all reflected in the journal list; trial balance balanced; **dashboard KPIs `net_profit` and `net_revenue` equal the P&L statement**; P&L page shows the same net.
- **Permissions:** reports-only, maker (create w/o post: no *post now* option, 403 on post, draft deletable), checker (post w/o create/reverse), view-only (accounts, parties, periods, profits, branches) – hub shows exactly the allowed cards, forbidden actions 403 and their forms are absent from the HTML; role editor lists all ERP permissions and saves an ERP role.
- **Central:** all 15 `central.erp.*` routes 200 in en and ar (rtl) with multi-currency tenants, and on a platform with no ERP data; odd filters never 500; CSV exports; drill-down; tenant-supplied XSS (journal description/memo) escaped in central pages; `erp_central.view`-only admin gets 403 everywhere except the hub; sidebar item active.
- **Panel connection:** tenant sidebar ERP item present and `active` on ERP pages; all 19 hub cards open (200); central sidebar + all hub cards open.
- **Robustness:** every POST form on 12 ERP pages carries `_token`; XSS payloads in account name/description, journal description/memo, owner, supplier, debit note, asset name/category/note escaped on 21 pages and neutralised in CSV; 500-char Arabic description / 300-char memo accepted and rendered, over-limit = field error; a 201-line journal posts; 5 repeated receipts with the same ref = one posting.

## Coverage matrix (76 routes)

Legend: ✔ tested & passes · ✘ bug found (ID) · — not exercised end-to-end.

| Route | Method | Tested | Result |
|---|---|---|---|
| tenant.erp.hub | GET | yes (smoke en/ar/empty, perms, panel) | ✔ |
| tenant.erp.dashboard | GET | yes (smoke, filters, KPI = P&L) | ✔ / ✘ QA-010 |
| tenant.erp.accounts.index | GET | yes | ✔ |
| tenant.erp.accounts.store | POST | yes | ✘ QA-001, QA-002, QA-003 |
| tenant.erp.accounts.destroy | DELETE | yes | ✔ |
| tenant.erp.accounts.restore | POST | yes | ✔ |
| tenant.erp.journals.index | GET | yes (filters) | ✘ QA-008 |
| tenant.erp.journals.create | GET | yes | ✔ |
| tenant.erp.journals.store | POST | yes | ✘ QA-004, QA-006, QA-007, QA-013 |
| tenant.erp.journals.show | GET | yes | ✘ QA-009 |
| tenant.erp.journals.post | POST | yes (double) | ✔ |
| tenant.erp.journals.reverse | POST | yes (double) | ✔ (ERP-007) |
| tenant.erp.journals.destroy | DELETE | yes | ✔ |
| tenant.erp.periods.index | GET | yes | ✔ |
| tenant.erp.periods.close | POST | yes | ✘ QA-015 |
| tenant.erp.periods.reopen | POST | yes | ✔ |
| tenant.erp.periods.year-close | POST | yes | ✘ QA-015 |
| tenant.erp.periods.year-reopen | POST | yes (probe) | ✔ |
| tenant.erp.parties.index | GET | yes (customer, supplier) | ✔ |
| tenant.erp.parties.ageing | GET | yes | ✔ |
| tenant.erp.parties.show | GET | yes | ✔ |
| tenant.erp.parties.settle | POST | yes | ✘ QA-004, QA-012 |
| tenant.erp.parties.apply-advance | POST | yes | ✔ |
| tenant.erp.parties.note | POST | yes | ✘ QA-004 |
| tenant.erp.parties.opening | POST | yes | ✔ |
| tenant.erp.owners.index | GET | yes | ✔ |
| tenant.erp.owners.store | POST | yes | ✘ QA-004 |
| tenant.erp.owners.show | GET | yes | ✘ QA-009 |
| tenant.erp.owners.update | PUT | yes | ✔ |
| tenant.erp.owners.transact | POST | yes | ✘ QA-004 |
| tenant.erp.owners.allocate | POST | — (accounting defect ERP-006) | — |
| tenant.erp.profits.index | GET | yes | ✘ QA-005 |
| tenant.erp.profits.store | POST | yes | ✔ |
| tenant.erp.profits.show | GET | yes | ✔ |
| tenant.erp.profits.approve | POST | yes (second approver) | ✔ |
| tenant.erp.profits.post | POST | yes | ✔ |
| tenant.erp.profits.pay | POST | yes (double) | ✔ |
| tenant.erp.profits.cancel | POST | yes | ✔ |
| tenant.erp.profits.report | GET | yes | ✔ |
| tenant.erp.profits.statement | GET | yes | ✔ |
| tenant.erp.assets.index | GET | yes | ✔ |
| tenant.erp.assets.store | POST | yes | ✘ QA-004 |
| tenant.erp.assets.show | GET | yes | ✔ |
| tenant.erp.assets.depreciate | POST | yes | ✔ |
| tenant.erp.assets.depreciate-all | POST | yes (probe) | ✔ |
| tenant.erp.assets.dispose | POST | yes | ✔ |
| tenant.erp.assets.transfer | POST | yes | ✔ |
| tenant.erp.branches.index | GET | yes | ✔ |
| tenant.erp.branches.transfer | POST | yes | ✘ QA-003 |
| tenant.erp.reports.index | GET | yes | ✔ |
| tenant.erp.reports.show (18 reports) | GET | yes | ✔ / ✘ QA-010 (GL) |
| tenant.erp.reports.export (18 × CSV/PDF) | GET | yes (en + ar) | ✔ |
| tenant.erp.settings.edit | GET | yes | ✔ |
| tenant.erp.settings.update | PUT | yes | ✘ QA-005, QA-003 |
| tenant.erp.settings.rates.store | POST | yes | ✔ (QA-011 messages) |
| tenant.erp.settings.rates.destroy | DELETE | yes | ✔ |
| tenant.erp.migration.index | GET | yes | ✔ |
| tenant.erp.migration.detect | POST | yes | ✔ |
| tenant.erp.migration.post-missing | POST | yes (double) | ✔ |
| tenant.erp.migration.reconcile | POST | yes | ✔ |
| tenant.erp.migration.toggle | PUT | yes | ✔ |
| central.erp.hub | GET | yes (perms, panel) | ✘ QA-014 |
| central.erp.overview | GET | yes | ✔ |
| central.erp.overview.export | GET | yes | ✔ |
| central.erp.tenant | GET | yes | ✔ |
| central.erp.performance | GET | yes | ✔ |
| central.erp.consolidated | GET | yes | ✔ |
| central.erp.transactions | GET | yes | ✔ |
| central.erp.transactions.show | GET | yes | ✔ |
| central.erp.parties | GET | yes | ✔ |
| central.erp.inventory | GET | yes | ✔ |
| central.erp.periods | GET | yes | ✔ |
| central.erp.anomalies | GET | yes | ✔ |
| central.erp.anomalies.export | GET | yes | ✔ |
| central.erp.audit | GET | yes | ✔ |
| central.erp.migration | GET | yes | ✔ |

Cross-cutting: locale switch `tenant.locale` / central `locale` (✔, QA-011), role editor `tenant.roles.create|store` (✔, labels = ERP-027), business routes `tenant.pos.store`, `tenant.orders.status`, `tenant.orders.returns.store`, `tenant.returns.approve|refund`, `tenant.purchases.store|receive`, `tenant.stock-adjustments.store`, `tenant.stock-transfers.store|send|receive`, `tenant.expenses.store|update|destroy` (✔).

## Notes / limitations
- CSRF is asserted structurally (every POST form contains `_token`); Laravel disables the CSRF middleware under `runningUnitTests()`, so a 419 cannot be produced in-process.
- "Concurrent" submits are sequential repeats in one process (no parallel HTTP); row locks are covered by `ErpSecurityTest`.
- Contra accounts (`owner_drawings`, `accumulated_depreciation`) are stored with the parent type's sign (negative); tests compare magnitudes – not a defect.
