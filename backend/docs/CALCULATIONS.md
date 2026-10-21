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
   - Discount is distributed to lines proportionally to `line_subtotal` (for returns/refund math): `line_discount = discount × line_subtotal/B` (eligible lines; last line takes rounding remainder).
2. **Shipping** `shipping = price_list(company, city)`; if `subtotal − discount ≥ free_over` (when set) → 0. `shipping_discount` as above. `shipping_net = shipping − shipping_discount`. `shipping_cost` (what we pay the carrier) = `price_list.cost` if set else `shipping` (used in profit).
3. **Taxable/VAT**: tenant `settings.tax_rate` as a percent, e.g. 14 (default 0, `settings.prices_include_tax`, prices tax-exclusive unless `prices_include_tax`). with `r = tax_rate/100`: `tax = (subtotal − discount) × r` (if inclusive: `tax = (subtotal − discount) × r/(1+r)` and not added). On returns the tax of the returned value is refunded pro-rata (`tax × return_value/(subtotal − discount)`) and excluded from profit as always.
4. **Payment fee**: base `F = subtotal − discount + shipping_net + tax(exclusive only)`; `fee = F × fee_percent/100 + fee_fixed`. If `fee_bearer = customer`: `payment_fee_charged = fee` and added to total; if `merchant`: `payment_fee_charged = 0` (fee still a merchant cost). COD: fee per method config (usually 0).
5. **Total**: `total = subtotal − discount + shipping_net + tax(excl.) + payment_fee_charged`.
6. `paid_amount`, `due = total − paid_amount`.

## Profit (per order, recognized once an ADMIN has confirmed the order — decision 2026-10-21)
An order counts in sales/profit only when status ∈ {confirmed, processing, shipped, delivered} (i.e. confirmed by an admin; `pending` = merely submitted by the customer and never counts). Bucketed by the order's `created_at` (order date). Cancelled orders never count; a cancel after confirmation removes it (and its commission).
Confirmation rules: COD orders need admin confirmation; offline/wallet orders need the customer's proof-of-payment file AND admin approval of that proof before confirmation; online-gateway orders confirm automatically only after a verified webhook, then still sit as `pending` until admin confirms unless `settings.auto_confirm_paid_online` is on.
`revenue_goods = subtotal − discount`
`gross_profit = revenue_goods − cogs`
`order_net_profit = gross_profit + shipping_net − shipping_cost − payment_fee_total − affiliate_commission`
where `payment_fee_total = fee` (always, whoever bears it) and `+ payment_fee_charged` income is included: `order_net_profit += payment_fee_charged`. (So bearer=customer ⇒ fee nets to 0.) Tax is pass-through and excluded.
Period **Net Profit** = `Σ order_net_profit − Σ refunds_net_loss − Σ expenses` (expenses by date, optional branch).

## Payment methods & COD split (decision 2026-10-21)
- Every payment method (COD, wallets such as Vodafone Cash / E& Cash / InstaPay, online: Paymob, FawryPay, …) has an independent `is_active` toggle per tenant; COD exists by default.
- **COD**: the customer pays the delivery fee upfront (`prepaid = shipping_net`, via an active online/wallet method, with proof if wallet); the carrier collects the rest on delivery: `cod_amount = total − prepaid_shipping`. Switchable with `settings.cod_collect_shipping_upfront` (default true; false ⇒ carrier collects the full `total`). Carrier owes the merchant `cod_amount` (tracked per shipping company for settlement; carrier fee `shipping_cost` is deducted in settlement).
- `due = total − paid_amount` where `paid_amount` includes the prepaid shipping; `cod_amount` is stored on the order.

## Affiliate commission
Base = `subtotal − discount` (goods after discount, excl. shipping/tax). Tier chosen by affiliate's **approved lifetime delivered sales amount** BEFORE this order (tier table: `min_sales → percent`, highest min ≤ sales wins; per-tenant). `commission = base × percent/100`. Tier basis (implemented): `lifetime sales = Σ(subtotal − discount) − Σ return_value` of the affiliate's orders with status `delivered` (cancelled never count; **returns reduce lifetime sales and the commission**, decision 2026-10-21); tiers are per tenant (`affiliate_tiers`), none ⇒ 0%. Attribution: last-click cookie `aff` (30 days) set by `?utm_source=affiliate&aff={uuid}`; the order stores `affiliate_id`; self-referral (same email) and non-approved affiliates are ignored. One `affiliate_commissions` row per order (`base`, `percent`, `amount`).
Status: pending at order → approved by `affiliates:approve-commissions` (daily) once the order is `delivered` and `settings.affiliate_return_window_days` (default 14) elapsed → paid when admin marks the affiliate's payout paid. A payout request takes ALL approved, un-requested commissions (`amount` = their sum, recomputed at payment); rejecting it frees them again. Cancelled / fully returned ⇒ voided (amount 0); partial return ⇒ `amount −= amount × return_value / base` (mirrored in `orders.affiliate_commission`). Paid commissions are never altered. Cancelled/fully-returned order ⇒ commission voided; partial return ⇒ commission reduced pro-rata on returned `line_subtotal − line_discount`.

## Returns & refunds
- Return line: `return_value = (unit_price × qty_returned) − line_discount × qty_returned/qty` (never refund shipping unless whole order cancelled before shipping or `refund_shipping` flag).
- Stock: restocked (if `restock=true`) at the returned branch; add movement `return_in` at original `unit_cost`.
- `refund_amount` ≤ paid; payment fee is NOT refunded (merchant loss, counted in `refund_net_loss`).
- Reports: **Returns** = value/qty of return documents; **Refunds** = money actually paid back. `refund_net_loss = return_shipping_cost` (the kept payment fee is already inside order profit; do not subtract twice — clarified 2026-10-21); the returned goods' profit is reversed: sales/profit reports subtract `return_value` and add back `returned cogs`.

## Flash sale
Price used while `now ∈ [start,end]`, `sold < qty_limit`; per-order qty clamp = remaining. Sold counter increments on order placement, decrements on cancel.

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
- Net profit = Σ delivered `(subtotal−discount) − cogs + shipping_net − shipping_cost − (payment_fee − payment_fee_charged) − affiliate_commission` − Σ(`return_value − returned_cogs`) − Σ`return_shipping_cost` − Σ expenses. The kept payment fee is already inside the order profit, so it is NOT subtracted again as refund loss (`refund_net_loss` = return shipping cost).
- Top products/categories: qty/revenue/cost/profit are net of returned qty (`line_subtotal − line_discount` pro-rated by `(qty−returned)/qty`); a product in several categories counts in each. Low stock: `on_hand − reserved ≤ settings.low_stock_threshold` (default 5).
- Central aggregation: every money SUM is `ROUND(SUM(col × tenants.currency_rate), 2)` in SQL, shown in `config('tenancy.base_currency')`; tenant context is never converted.

## Worked example (tests MUST include)
Items: 2 × 100 (cost 60) + 1 × 50 (cost 30). subtotal=250, cogs=150. Code 10% (cap 20, min 100): d=25→discount=20. Shipping 30 (cost 25), free_over 500 → 30. tax 0. Method card fee 2.5% + 1 fixed, bearer=customer: F=250−20+30=260 → fee=7.50; total=260+7.50=267.50. Affiliate tier 5%: base 230 → commission 11.50.
order_net_profit = (230−150) + 30 − 25 − 7.50 − 11.50 + 7.50 = **73.50** (corrected from 63.50, an arithmetic slip; verified in tests/Unit/OrderCalculatorTest.php).
