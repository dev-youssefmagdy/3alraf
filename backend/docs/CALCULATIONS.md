# Order & Reporting Formulas — SINGLE SOURCE OF TRUTH
**Any change to order, cart, discount, shipping, payment-fee, commission, refund, stock-valuation or report math MUST first re-read this file, update it if the rule changes, and keep `App\Services\Orders\OrderCalculator` + tests in sync.** Round half-up to 2 decimals at the END of each labeled step only.

## Definitions (per order, tenant currency)
- `unit_price` = effective price at order time = flash-sale price if active & qty available, else tenant `sale_price` if set, else `price`.
- `line_subtotal = unit_price × qty`
- `subtotal = Σ line_subtotal`
- `unit_cost` = tenant cost snapshot (weighted-average cost from purchases; fallback product cost). `line_cost = unit_cost × qty`; `cogs = Σ line_cost`.

## Order totals (apply in this exact order)
1. **Discount** (single code per order): code scope base `B` = subtotal of eligible lines (all lines if scope=all).
   - percent: `d = B × pct/100`; fixed: `d = min(value, B)`.
   - `discount = min(d, max_discount_cap if set else d)`; requires `subtotal ≥ min_order`.
   - Free-shipping codes: `discount = 0` on goods, `shipping_discount = shipping` (step 2).
   - Discount is distributed to lines proportionally to `line_subtotal` (for returns/refund math): `line_discount = discount × line_subtotal/B` (eligible lines; last line takes the rounding remainder). **Cap: `line_discount ≤ line_subtotal` always** (a tiny last line never gets more than its own subtotal; any excess is moved to other eligible lines with room, so Σ line_discount = discount and no `return_value` is ever negative).
2. **Shipping** `shipping = price_list(company, city)`; if `subtotal − discount ≥ free_over` (when set) → 0. `shipping_discount` as above. `shipping_net = shipping − shipping_discount`. `shipping_cost` (what we pay the carrier) = `price_list.cost` if set else `shipping` (used in profit).
3. **Taxable/VAT**: tenant `settings.tax_rate` as a percent, e.g. 14 (default 0, `settings.prices_include_tax`, prices tax-exclusive unless `prices_include_tax`). with `r = tax_rate/100`: `tax = (subtotal − discount) × r` (if inclusive: `tax = (subtotal − discount) × r/(1+r)` and not added). Tax on returns: **exclusive** orders refund `tax_share = round(tax × return_value/(subtotal − discount))` on top of `return_value` (cap for the refund = `return_value + tax_share (+ shipping_net if refund_shipping)`); **inclusive** orders refund only `return_value` (tax is already inside it). Tax is always excluded from profit.
4. **Payment fee**: base `F = subtotal − discount + shipping_net + tax(exclusive only)`; `fee = F × fee_percent/100 + fee_fixed`. If `fee_bearer = customer`: `payment_fee_charged = fee` and added to total; if `merchant`: `payment_fee_charged = 0` (fee still a merchant cost). COD: fee per method config (usually 0).
5. **Total**: `total = subtotal − discount + shipping_net + tax(excl.) + payment_fee_charged`.
6. `paid_amount`, `due = total − paid_amount`.

## Profit (per order, recognized once an ADMIN has confirmed the order — decision 2026-10-21)
An order counts in sales/profit only when status ∈ {confirmed, processing, shipped, delivered} (i.e. confirmed by an admin; `pending` = merely submitted by the customer and never counts). Bucketed by the order's `created_at` (order date), NOT the delivery date. Cancelled orders never count; a cancel after confirmation removes it (and its commission).
Confirmation rules: COD orders need admin confirmation; offline/wallet orders need the customer's proof-of-payment file AND admin approval of that proof before confirmation; online-gateway orders confirm automatically only after a verified webhook, then still sit as `pending` until admin confirms unless `settings.auto_confirm_paid_online` is on.
`revenue_goods = subtotal − discount`
`gross_profit = revenue_goods − cogs`
`order_net_profit = gross_profit + shipping_net − shipping_cost − payment_fee_total − affiliate_commission`
where `payment_fee_total = fee` (always, whoever bears it) and `+ payment_fee_charged` income is included: `order_net_profit += payment_fee_charged`. (So bearer=customer ⇒ fee nets to 0.) Tax is pass-through and excluded.
Period **Net Profit** = `Σ order_net_profit − Σ (return_value − returned_cogs) − Σ return_shipping_cost − Σ expenses` (expenses by date, optional branch). `refund_net_loss` is defined ONCE: **return shipping cost only** (the kept payment fee is already inside `order_net_profit`, never subtract it again).

## Payment methods & COD split (decision 2026-10-21, implemented sprint 10)
- Every payment method (COD, wallets such as Vodafone Cash / E& Cash / InstaPay / any admin-defined wallet, online: Paymob, FawryPay, …) has an independent `is_active` toggle per tenant; COD exists by default per tenant, cannot be deleted (may be deactivated). Checkout offers only active methods whose online driver is implemented.
- **COD**: with `settings.cod_collect_shipping_upfront` (default true) the customer prepays `prepaid_shipping = shipping_net` via an active online/wallet method (`orders.prepay_method_id`; wallet needs proof + admin approval, online needs a verified webhook) and the carrier collects the rest: `cod_amount = total − prepaid_shipping`. If `shipping_net = 0` nothing is prepaid. With the setting false: `prepaid_shipping = 0`, `cod_amount = total`. Non-COD orders: `cod_amount = 0`, `prepaid_shipping = 0`. The prepay leg carries NO extra fee (the fee config of the COD method applies to the order as before; gateway cost of the prepay leg is not modelled).
- **COD extra fee hook (NOT implemented yet)**: a future per-tenant "COD extra fee" is a customer-charged amount added in `OrderCalculator::codExtraFee()` (step 4, same base `F`); it is then part of `total`, `cod_amount` (carrier collects it) and income in profit (`+ cod_fee`), with no other formula changing. Today the hook returns `0.00`.
- `paid_amount` = money actually received (prepaid shipping once settled; COD cash is booked as paid on delivery, then `paid_amount = total`). `due = total − paid_amount`. Payment status `partially_paid` = prepaid shipping received, COD balance outstanding.
- **Carrier settlement report** (per shipping company, COD orders, order date): `collected = Σ cod_amount` of `delivered` orders (cash the carrier holds), `in_transit = Σ cod_amount` of `shipped` orders, `carrier_fee = Σ shipping_cost` of delivered orders, `owed_to_merchant = collected − carrier_fee`. "Mark carrier settled" (`orders.carrier_settled_at`, action on the report; permission `orders.payments`) moves delivered COD orders of one carrier + date range out of `collected`/`carrier_fee`/`owed` (outstanding only) and into `settled = Σ(cod_amount − shipping_cost)`; `in_transit` (shipped) is never affected.
- **Confirmation gates**: COD (upfront) needs the prepay settled (`paid`), wallet needs proof APPROVED (payment `paid`), online needs payment `paid` (verified webhook); then an admin confirms (or `settings.auto_confirm_paid_online` auto-confirms a paid ONLINE order). Approving a proof does NOT confirm the order.
- **Refunds** go through the provider for online payments; the order is marked refunded ONLY when the provider reports success (a failed refund leaves `refunded_amount`/status untouched and the return stays `approved`). Wallet/COD refunds are manual money moves.
- Webhooks: signature verified with the provider scheme, then amount, currency, provider status and order reference are cross-checked against OUR order (mismatch rejected, nothing settled); each provider event is processed once (`payment_events` unique provider+event_id).

**`orders.net_profit` lifecycle**: `0` while `pending`; computed by `OrderStatusService` when an admin confirms (formula above from the stored order columns); after each approved return it is recomputed as `formula − Σ(return_value − returned_cogs + return_shipping_cost)` over the order's approved/refunded returns (commission already mirrored from the cumulative-returns rule); set back to `0` on cancel/fully-returned. The period Net Profit report keeps using the component formula (returns subtracted by return date), `orders.net_profit` is the per-order running figure used by the Sales report.

## Affiliate commission
Base = `subtotal − discount` (goods after discount, excl. shipping/tax; `affiliate_commissions.base`). Tier chosen by affiliate's **approved lifetime delivered sales amount** BEFORE this order (tier table: `min_sales → percent`, highest min ≤ sales wins; per-tenant). `commission = base × percent/100`. Tier basis (implemented): `lifetime sales = Σ(subtotal − discount) − Σ return_value` of the affiliate's orders with status `delivered` (cancelled never count; **returns reduce lifetime sales and the commission**, decision 2026-10-21); tiers are per tenant (`affiliate_tiers`), none ⇒ 0%. Attribution: last-click cookie `aff` (30 days) set by `?utm_source=affiliate&aff={uuid}`; the order stores `affiliate_id`; self-referral (same email) and non-approved affiliates are ignored. One `affiliate_commissions` row per order (`base`, `percent`, `amount`).
Status: pending at order → approved by `affiliates:approve-commissions` (daily) once the order is `delivered` and `settings.affiliate_return_window_days` (default 14) elapsed → paid when admin marks the affiliate's payout paid. A payout request takes ALL approved, un-requested commissions (`amount` = their sum, recomputed at payment); rejecting it frees them again. Cancelled / fully returned ⇒ voided (amount 0). **Partial return — ONE formula, always recomputed from the cumulative returned value (no drift over several partial returns):** `commission = round((base − Σ return_value of the order's approved/refunded returns, floored at 0) × percent/100)` using the `base`/`percent` stored on the commission row; mirrored in `orders.affiliate_commission`. Paid commissions are never altered.

## Returns & refunds
- Return line: `return_value = (unit_price × qty_returned) − line_discount × qty_returned/qty` (never refund shipping unless whole order cancelled before shipping or `refund_shipping` flag).
- Stock: restocked (if `restock=true`) at the returned branch; add movement `return_in` at original `unit_cost`.
- `refund_amount` ≤ paid; the payment fee is NOT refunded (the merchant keeps bearing it; it stays inside the order's profit, not in `refund_net_loss`). Refund cap = `return_value + tax_share` (+ `shipping_net` when `refund_shipping`), see Tax on returns.
- Reports: **Returns** = value/qty of return documents; **Refunds** = money actually paid back. `refund_net_loss = return_shipping_cost` (the kept payment fee is already inside order profit; do not subtract twice — clarified 2026-10-21); the returned goods' profit is reversed: sales/profit reports subtract `return_value` and add back `returned cogs`.

## Flash sale
Price used while `now ∈ [start,end]`, `sold < qty_limit`. **Split, never reject:** if a line asks for more units than remain, the line is SPLIT into two order items — `remaining` units at the flash price and the rest at the regular price (extra qty is not refused). Sold counter increments on order placement (flash units only), decrements on cancel.

## Stock
`on_hand = Σ movements` per (branch, product/variant). Types: purchase_in, sale_out, return_in, transfer_out, transfer_in, adjustment_in/out. Reserve at order placement (`reserved`), deduct at shipped. `available = on_hand − reserved`. Never negative unless tenant `allow_backorder`.
Weighted-avg cost on purchase_in: `new_avg = (on_hand×avg + qty×cost)/(on_hand+qty)`.
Stock valuation = `Σ on_hand × avg_cost`.
Implementation (`App\Services\Stock\StockLedger`, the only writer of stock): avg is kept per (branch, product, variant) in `stock_levels`; incoming types (purchase_in, transfer_in, return_in, adjustment_in) re-average, outgoing types are valued at current avg and leave it unchanged; if `on_hand ≤ 0` before an incoming row, avg = incoming cost. Transfers move stock at the source avg (snapshotted on the transfer item). After `purchase_in`, tenant listing `cost` (tenant_products / tenant_product_variants) = `Σ(on_hand×avg)/Σ on_hand` across branches (this is the order `unit_cost` snapshot). PO shipping/other costs are NOT allocated into unit cost (only in PO `total_cost` for the Purchases report). Negative stock refused unless tenant `settings.allow_backorder`.

## Reports (date range on `created_at` of the document unless stated; sales AND profit use admin-confirmed orders: status ∈ {confirmed, processing, shipped, delivered} — updated 2026-10-21)
- **Purchases**: total_cost = Σ purchase_items qty×cost (+ shipping/other costs on PO); cards: #POs, qty, avg cost, top suppliers.
- **Sales**: total = Σ total; costs = Σ cogs; discounts = Σ discount; shipping = Σ shipping_net; fees = Σ fee; net profit per Profit formula.
- **Discounts**: Σ discount + usage per code. **Shipping costs**: Σ shipping_cost vs Σ shipping_net collected per company. **Payment fees**: Σ fee by method (+ bearer split).
- **Net profit** period formula above. **Returns/Refunds** per definitions. **Expenses**: Σ amount by category.
- **Central aggregate**: convert each tenant's amounts `× tenant.currency_rate_to_base` then sum.

### Reports as implemented (Phase 6, `App\Services\Reports\ReportService` + `ReportRepository`)
- Date range = `created_at` of the document (expenses: `expense_date`; refunds: `refunded_at`/`cancelled_at`); stock is a snapshot. Sales statuses = confirmed, processing, shipped, delivered; **profit from all admin-confirmed orders (same set) created in the range** — CHANGED 2026-10-21 from delivered-only (`orders.net_profit` snapshot must be computed at confirmation; returns reduce it).
- Purchases = RECEIVED POs only (`total_cost` incl. shipping/other). Returns = documents in status approved|refunded.
- Net profit = Σ admin-confirmed (confirmed|processing|shipped|delivered) `(subtotal−discount) − cogs + shipping_net − shipping_cost − (payment_fee − payment_fee_charged) − affiliate_commission` − Σ(`return_value − returned_cogs`) − Σ`return_shipping_cost` − Σ expenses. The kept payment fee is already inside the order profit, so it is NOT subtracted again as refund loss (`refund_net_loss` = return shipping cost).
- Top products/categories: qty/revenue/cost/profit are net of returned qty (`line_subtotal − line_discount` pro-rated by `(qty−returned)/qty`); a product in several categories counts in each. Low stock: `on_hand − reserved ≤ settings.low_stock_threshold` (default 5).
- Central aggregation: every money SUM is `ROUND(SUM(col × tenants.currency_rate), 2)` in SQL, shown in `config('tenancy.base_currency')`; tenant context is never converted.

## Worked example (tests MUST include)
Items: 2 × 100 (cost 60) + 1 × 50 (cost 30). subtotal=250, cogs=150. Code 10% (cap 20, min 100): d=25→discount=20. Shipping 30 (cost 25), free_over 500 → 30. tax 0. Method card fee 2.5% + 1 fixed, bearer=customer: F=250−20+30=260 → fee=7.50; total=260+7.50=267.50. Affiliate tier 5%: base 230 → commission 11.50.
order_net_profit = (230−150) + 30 − 25 − 7.50 − 11.50 + 7.50 = **73.50** (corrected from 63.50, an arithmetic slip; verified in tests/Unit/OrderCalculatorTest.php).
