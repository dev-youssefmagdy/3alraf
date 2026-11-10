# Affiliate sales bonus — plan

Owner request (2026-11-10): affiliates earn a **200 EGP bonus for every 10,000 EGP** of completed (delivered) sales, repeated for each further 10,000.

## Business rules (confirmed by the owner)

1. **What counts toward the 10,000:** goods after discount, excluding shipping and VAT, minus returned goods —
   the same `revenue_goods − returned value excl. VAT` figure already used for affiliate tiers (`docs/CALCULATIONS.md` §Affiliate commission).
2. **When an order counts:** status `delivered` **and** `delivered_at` older than the return window
   (`settings.affiliate_return_window_days`, default 14) — the moment its commission becomes approved. Cancelled and fully returned orders never count.
   Returns approved before that point are already subtracted. A bonus, once given, is never cancelled by a later return; a late return only delays the next one.
3. **Only new sales:** orders placed on or after the launch date (`settings.affiliate_bonus_started_at`, set by the migration) count. No back-pay.
4. **Counted regardless of commission:** an order counts even when the affiliate's tier gives 0 % commission (there is no commission row for such orders).
5. **Approved affiliates only:** a suspended or pending affiliate gets nothing while not approved; the next run after re-approval catches up (the count is cumulative).
6. **Switching the bonus off pauses it (confirmed 2026-11-10):** orders PLACED while it is off never count, ever; counting restarts when it is switched back on.
   Orders placed while it was on still count when they mature (one maturing during a pause is awarded after re-enable). Stored as pause periods
   `settings.affiliate_bonus_paused_periods = [{from, to}]` (open period while off). "Placed" = `orders.placed_at` (fallback `created_at`).
7. **Voided bonuses forfeit their sales:** `consumed` counts every bonus, voided included, so a void is never re-awarded.

## Calculation

```
eligible_sales(a) = Σ over a's orders with status=delivered, delivered_at ≤ now − window, placed ≥ started_at, placed not in a pause period
                    of (revenue_goods − returnNetOfTax(Σ approved/refunded return_value)), floored at 0 per order
consumed(a)       = Σ threshold of all a's bonuses (voided included)
while eligible_sales(a) − consumed(a) ≥ current threshold:
    create bonus #n (threshold = current threshold, amount = current amount, sales_at_award = eligible_sales), consumed += threshold
```

Storing the threshold on each bonus row means an admin can change 10,000 / 200 later without re-awarding or losing history:
earned bonuses keep their values, and the next bonus uses the new settings.

## Settings (admin editable, defaults)

| key | default | rule |
|---|---|---|
| `affiliate_bonus_enabled` | `1` | off ⇒ pause: no new bonuses, orders placed while off never count (earned ones stay payable) |
| `affiliate_bonus_threshold` | `10000` | ≥ 1000 |
| `affiliate_bonus_amount` | `200` | > 0 and < threshold |
| `affiliate_bonus_paused_periods` | `[]` | internal, written on on↔off switches |
| `affiliate_bonus_started_at` | migration time | read-only in the UI |

## Data — `affiliate_bonuses`

`id, affiliate_id (fk cascade), payout_id (fk affiliate_payouts, null on delete), milestone (int, 1..n per affiliate), threshold decimal(14,2),
amount decimal(14,2), sales_at_award decimal(14,2), status (approved|paid|voided, reuse CommissionStatus), timestamps`;
`unique(affiliate_id, milestone)` is the database guard against double awards. Index `(affiliate_id, status, payout_id)`.

## Money flow

- Awarded by `affiliates:approve-commissions` (daily 03:00), right after commissions are approved, inside a transaction with the affiliate row locked.
- A bonus is born `approved`, so it is withdrawable at once.
- **Payout request** takes all approved, un-requested commissions **and** bonuses; **paid** marks both paid (amount = Σ of both);
  **rejected** frees both. The split is stored on `affiliate_payouts.commission_amount / bonus_amount` (kept on rejected payouts).
- **ERP:** on award, `Dr affiliate expense / Cr affiliate payable` (sub-ledger affiliate, idempotent key `affbonus:{id}`), after commit.
  The payout entry already clears the payable. Bonuses and paid payouts are in the ERP backfill / post-missing scan.
- Order-level profit reports do not include bonuses (they belong to no single order); the ERP P&L does.

## Screens

- **Affiliate dashboard (web + mobile API `affiliate/dashboard`)**: progress toward the next bonus (`x / 10,000 EGP`, bar), bonuses earned (count + total),
  bonus list; available balance includes bonuses.
- **Admin**: bonus settings; affiliates list shows bonuses earned; payouts page shows the commission / bonus split.
- **Affiliate register page (public)**: one line advertising the bonus, built from the live settings (SEO: real text, translated meta description).

## Team (run one by one)

1. Senior database designer + senior backend developer — table, model, repository, service, command, payout changes, ERP posting, API, settings.
2. Senior frontend developer + SEO — affiliate dashboard, admin screens, register page copy, ar + en, mobile and desktop.
3. Senior QA tester — feature tests for every rule above, regression of affiliate / payout / ERP tests, browser check.
4. Problem solver — independent review of the diff for money bugs, races and edge cases.

## Status log

- Spec written and rules confirmed (2026-11-10).
- Backend done (2026-11-10): table, model, repository, AffiliateBonusService, command hook, payouts, ERP posting (affbonus:{id}), API, settings in Settings → General, tests/Feature/AffiliateBonusTest.php.
- Frontend + SEO done (2026-11-10): dashboard bonus card (rule sentence, accessible progress bar, earned count/total, recent bonuses) + payout commission/bonus split; Settings → General bonus section; register page bonus line, translated title/meta, page now indexable (robots Allow + sitemap); ar + en; view tests in AffiliateBonusTest.
- Review fixes (2026-11-10): amount > 0 and < threshold, threshold ≥ 1000, pause periods, placed_at, voided bonuses consumed, payout split stored, ERP backfill of bonuses + payouts, per-affiliate error isolation.
- QA sign-off (2026-11-10): ship with notes. Full suite 752 tests, 744 pass, 8 known pre-existing failures. Open: SalesPoster commission lines lack the affiliate sub-ledger party.
- Delete guard (2026-11-10): an affiliate with pending/approved commissions, approved bonuses or a requested payout cannot be deleted (422, suspend instead), so no orphan ERP payable is left.
